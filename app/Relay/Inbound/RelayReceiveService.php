<?php

namespace App\Relay\Inbound;

use App\DTO\RelayEnvelopeDTO;
use App\Models\HubRelayClient;
use App\Models\HubRelayMessage;
use App\Models\HubRelayReceipt;
use App\Relay\Envelope\RelayEnvelopeValidator;
use App\Relay\Handlers\LocalHandlerDispatchService;
use App\Relay\Outbound\RelayForwardingTopologyService;
use App\Relay\Registry\RelayNodeIdentityService;
use App\Models\HubRelayDelivery;
use App\Jobs\ProcessRelayDelivery;

/**
 * RelayReceiveService
 *
 * Handles inbound messages from upstream hubs (hub-to-hub delivery).
 * Validates envelopes, checks idempotency, and acknowledges receipt.
 */
class RelayReceiveService
{
    public function __construct(
        private RelayEnvelopeValidator $validator,
        private RelayIdempotencyService $idempotency,
        private LocalHandlerDispatchService $localHandlers,
        private RelayForwardingTopologyService $topology,
        private RelayNodeIdentityService $nodeIdentity,
    ) {}

    /**
     * Receive an inbound message from a remote hub
     *
     * Returns receipt with status and whether it's a duplicate
     * @throws \InvalidArgumentException
     */
    public function receive(RelayEnvelopeDTO $envelope): array
    {
        // Validate the envelope
        $this->validator->validate($envelope);

        // Check if this is a duplicate (idempotency)
        $isDuplicate = $this->idempotency->isDuplicate($envelope->relay_id);

        if ($isDuplicate) {
            // Return success for duplicate - don't reprocess
            $previousReceipt = $this->idempotency->markAsDuplicate($envelope->relay_id);
            return [
                'success' => true,
                'status' => 'duplicate',
                'relay_id' => $envelope->relay_id,
                'receipt' => $previousReceipt,
            ];
        }

        // First time seeing this message - record receipt
        $receipt = $this->idempotency->markAsReceived(
            $envelope->relay_id,
            $envelope->source_hub_id,
            $envelope->message_type,
            $envelope->calculateContentHash(),
        );

        $localHqId = $this->localHqId();
        if ($localHqId !== null && (string) $envelope->target_hq_hub_id !== $localHqId) {
            throw new \InvalidArgumentException('Message does not target this relay.');
        }

        if ($localHqId !== null && in_array($localHqId, $envelope->visitedHubIds(), true)) {
            $receipt = $this->idempotency->markAsRejected(
                $receipt,
                'Loop detected for relay hop '.$localHqId
            );

            return [
                'success' => false,
                'status' => HubRelayReceipt::STATUS_REJECTED,
                'relay_id' => $envelope->relay_id,
                'receipt' => $receipt,
            ];
        }

        $storedEnvelope = $localHqId !== null
            ? $envelope->withHop($localHqId, 'received')
            : $envelope;

        $message = HubRelayMessage::query()->firstOrCreate(
            [
                'relay_id' => $envelope->relay_id,
                'source_hub_id' => $storedEnvelope->source_hub_id,
            ],
            [
                'origin_hq_hub_id' => $storedEnvelope->origin_hq_hub_id,
                'source_system' => $storedEnvelope->source_system,
                'target_hub_ids' => [],
                'targets' => [],
                'target_system' => $storedEnvelope->targetSystems()[0] ?? '',
                'target_systems' => $storedEnvelope->targetSystems(),
                'hop_trace' => $storedEnvelope->hop_trace,
                'message_type' => $storedEnvelope->message_type,
                'payload_format' => $storedEnvelope->payload_format,
                'payload_version' => $storedEnvelope->payload_version,
                'reference_type' => $storedEnvelope->reference_type,
                'reference_id' => $storedEnvelope->reference_id,
                'content_hash' => $storedEnvelope->calculateContentHash(),
                'payload' => $storedEnvelope->payload,
                'tags' => $storedEnvelope->tags,
                'priority' => $storedEnvelope->priority,
                'attachments_count' => $storedEnvelope->attachments_count,
                'correlation_id' => $storedEnvelope->correlation_id,
                'occurred_at' => $storedEnvelope->occurred_at,
            ],
        );

        $knownTargetSystems = HubRelayClient::query()
            ->whereIn('system_code', $storedEnvelope->targetSystems())
            ->pluck('system_code')
            ->values();

        $forwardedCount = $this->queueForwardingDeliveries($message, $storedEnvelope);

        if ($knownTargetSystems->isEmpty() && $forwardedCount === 0) {
            $receipt = $this->idempotency->markAsUndeliverable(
                $receipt,
                'No registered local client for target systems: '.collect($storedEnvelope->targetSystems())
                    ->implode(', ')
            );

            return [
                'success' => true,
                'status' => HubRelayReceipt::STATUS_UNDELIVERABLE,
                'relay_id' => $envelope->relay_id,
                'receipt' => $receipt,
            ];
        }

        $matchedHandlers = $this->localHandlers->dispatchForInboundMessage($message, $receipt);

        $receipt = $this->idempotency->markAsProcessed(
            $receipt,
            $this->processingNotes($storedEnvelope->targetSystems(), $knownTargetSystems->all(), $matchedHandlers, $forwardedCount),
        );

        return [
            'success' => true,
            'status' => 'received',
            'relay_id' => $envelope->relay_id,
            'receipt' => $receipt,
        ];
    }

    /**
     * Batch receive multiple messages
     *
     * Useful for catching up after outages/reconnects
     */
    public function receiveBatch(array $envelopes): array
    {
        $results = [];
        foreach ($envelopes as $envelope) {
            try {
                $result = $this->receive($envelope);
                $results[] = [
                    'relay_id' => $envelope->relay_id,
                    'success' => true,
                    'status' => $result['status'],
                ];
            } catch (\Exception $e) {
                $results[] = [
                    'relay_id' => $envelope->relay_id,
                    'success' => false,
                    'error' => $e->getMessage(),
                ];
            }
        }
        return $results;
    }

    private function processingNotes(array $targetSystems, array $knownTargetSystems, int $matchedHandlers, int $forwardedCount): string
    {
        $notes = $matchedHandlers > 0
            ? 'Queued for '.$matchedHandlers.' local handler(s).'
            : 'No matching local handlers. Message is available through inbox APIs.';

        if ($forwardedCount > 0) {
            $notes .= ' Forwarded to '.$forwardedCount.' next-hop relay(s).';
        }

        $unknownTargetSystems = collect($targetSystems)
            ->unique()
            ->reject(fn ($system) => in_array($system, $knownTargetSystems, true))
            ->values();

        if ($unknownTargetSystems->isNotEmpty()) {
            $notes .= ' Unknown target systems ignored: '.$unknownTargetSystems->implode(', ').'.';
        }

        return $notes;
    }

    private function queueForwardingDeliveries(HubRelayMessage $message, RelayEnvelopeDTO $envelope): int
    {
        $nextHopHubIds = $this->topology->nextHopHubIds(
            $envelope->visitedHubIds(),
            $envelope->source_hub_id
        );

        if ($nextHopHubIds === []) {
            return 0;
        }

        $count = 0;
        foreach ($nextHopHubIds as $targetHubId) {
            $delivery = HubRelayDelivery::query()->firstOrCreate([
                'hub_relay_message_id' => $message->id,
                'target_hq_hub_id' => (string) $targetHubId,
            ], [
                'target_hub_id' => (string) $targetHubId,
                'status' => HubRelayDelivery::STATUS_QUEUED,
            ]);

            if ($delivery->wasRecentlyCreated) {
                $count++;
                ProcessRelayDelivery::dispatch($delivery->id)
                    ->onQueue((string) config('relay.delivery.queue', 'relay-deliveries'));
            }
        }

        $message->forceFill([
            'target_hub_ids' => collect($nextHopHubIds)->values()->all(),
        ])->save();

        return $count;
    }

    private function localHqId(): ?string
    {
        return $this->nodeIdentity->localHqId();
    }
}
