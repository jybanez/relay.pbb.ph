<?php

namespace App\Relay\Outbound;

use App\DTO\RelayEnvelopeDTO;
use App\Jobs\ProcessRelayDelivery;
use App\Models\HubRelayClient;
use App\Models\HubRelayDelivery;
use App\Models\HubRelayMessage;
use App\Relay\Envelope\RelayEnvelopeValidator;

/**
 * RelaySubmissionService
 *
 * Handles submissions from local applications.
 * Creates the message record and queues deliveries for each target hub.
 */
class RelaySubmissionService
{
    public function __construct(
        private RelayEnvelopeValidator $validator,
        private RelayForwardingTopologyService $topology,
    ) {}

    /**
     * Submit a message for relay to upstream hubs
     *
     * @return array{message: HubRelayMessage, deliveries: HubRelayDelivery[]}
     * @throws \InvalidArgumentException
     */
    public function submit(RelayEnvelopeDTO $envelope, HubRelayClient $client): array
    {
        // Validate the envelope
        $this->validator->validate($envelope);

        // Calculate content hash for idempotency
        $envelope->content_hash = $envelope->calculateContentHash();

        // Create the message record
        $message = HubRelayMessage::create([
            'hub_relay_client_id' => $client->id,
            'relay_id' => $envelope->relay_id,
            'origin_hq_hub_id' => $envelope->origin_hq_hub_id,
            'source_hub_id' => $envelope->source_hub_id,
            'source_system' => $envelope->source_system,
            'targets' => $envelope->targets,
            'hop_trace' => $envelope->hop_trace,
            'message_type' => $envelope->message_type,
            'payload_format' => $envelope->payload_format,
            'payload_version' => $envelope->payload_version,
            'reference_type' => $envelope->reference_type,
            'reference_id' => $envelope->reference_id,
            'content_hash' => $envelope->content_hash,
            'payload' => $envelope->payload,
            'tags' => $envelope->tags,
            'priority' => $envelope->priority,
            'attachments_count' => $envelope->attachments_count,
            'correlation_id' => $envelope->correlation_id,
            'occurred_at' => $envelope->occurred_at,
        ]);

        // Create a delivery record for each eligible next-hop relay.
        $deliveries = [];
        $nextHopHubIds = $this->topology->nextHopHubIds(
            $envelope->visitedHubIds(),
            null,
            $envelope->targetHqHubIds(),
        );

        foreach ($nextHopHubIds as $targetHubId) {
            $delivery = HubRelayDelivery::create([
                'hub_relay_message_id' => $message->id,
                'target_hub_id' => (string) $targetHubId,
                'target_hq_hub_id' => (string) $targetHubId,
                'target_system' => null,
                'status' => HubRelayDelivery::STATUS_QUEUED,
            ]);
            $deliveries[] = $delivery;

            ProcessRelayDelivery::dispatch($delivery->id)
                ->onQueue((string) config('relay.delivery.queue', 'relay-deliveries'));
        }

        return [
            'message' => $message,
            'deliveries' => $deliveries,
        ];
    }
}
