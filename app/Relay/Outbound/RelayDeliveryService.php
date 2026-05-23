<?php

namespace App\Relay\Outbound;

use App\Models\HubRelayDelivery;
use App\Relay\Delivery\RelayRetryPolicy;
use App\Relay\Registry\RelayNodeIdentityService;
use App\Relay\Transport\RelayHttpSender;
use Throwable;

class RelayDeliveryService
{
    public function __construct(
        private RelayHttpSender $sender,
        private RelayRetryPolicy $retryPolicy,
        private RelayNodeIdentityService $nodeIdentity,
    ) {}

    /**
     * @return array{status: string, should_retry: bool, next_retry_at: \Carbon\CarbonImmutable|null, error: ?string}
     */
    public function process(HubRelayDelivery $delivery): array
    {
        $delivery->loadMissing('message');

        if ($delivery->message === null) {
            return $this->markDead($delivery, 'Delivery message record is missing.');
        }

        if (in_array($delivery->status, [HubRelayDelivery::STATUS_DELIVERED, HubRelayDelivery::STATUS_DEAD], true)) {
            return [
                'status' => $delivery->status,
                'should_retry' => false,
                'next_retry_at' => null,
                'error' => $delivery->last_error,
            ];
        }

        $attemptCount = $delivery->attempt_count + 1;

        $delivery->forceFill([
            'status' => HubRelayDelivery::STATUS_SENDING,
            'attempt_count' => $attemptCount,
            'last_attempt_at' => now(),
            'last_error' => null,
        ])->save();

        try {
            $response = $this->sender->send(
                $delivery->target_hq_hub_id ?: $delivery->target_hub_id,
                $this->buildPayload($delivery)
            );

            if ($response->successful()) {
                $delivery->forceFill([
                    'status' => HubRelayDelivery::STATUS_DELIVERED,
                    'delivered_at' => now(),
                    'last_error' => null,
                    'next_retry_at' => null,
                ])->save();

                return [
                    'status' => HubRelayDelivery::STATUS_DELIVERED,
                    'should_retry' => false,
                    'next_retry_at' => null,
                    'error' => null,
                ];
            }

            return $this->handleFailure(
                $delivery,
                sprintf('Remote hub responded with HTTP %d.', $response->status()),
                $attemptCount
            );
        } catch (Throwable $e) {
            return $this->handleFailure($delivery, $e->getMessage(), $attemptCount);
        }
    }

    private function buildPayload(HubRelayDelivery $delivery): array
    {
        $message = $delivery->message;
        $localHqId = $this->nodeIdentity->localHqId() ?: $message->source_hub_id;

        return [
            'relay_id' => $message->relay_id,
            'origin_hq_hub_id' => $message->origin_hq_hub_id ?: $message->source_hub_id,
            'source_hub_id' => $localHqId,
            'source_system' => $message->source_system,
            'target_hq_hub_id' => $delivery->target_hq_hub_id ?: $delivery->target_hub_id,
            'target_systems' => $message->target_systems ?? [],
            'hop_trace' => $message->hop_trace ?? [],
            'message_type' => $message->message_type,
            'payload_format' => $message->payload_format,
            'payload_version' => $message->payload_version,
            'reference_type' => $message->reference_type,
            'reference_id' => $message->reference_id,
            'content_hash' => $message->content_hash,
            'payload' => $message->payload,
            'tags' => $message->tags,
            'priority' => $message->priority,
            'attachments_count' => $message->attachments_count,
            'correlation_id' => $message->correlation_id,
            'occurred_at' => optional($message->occurred_at)?->toIso8601String(),
            'created_at' => optional($message->created_at)?->toIso8601String(),
        ];
    }

    /**
     * @return array{status: string, should_retry: bool, next_retry_at: \Carbon\CarbonImmutable|null, error: string}
     */
    private function handleFailure(HubRelayDelivery $delivery, string $error, int $attemptCount): array
    {
        if (! $this->retryPolicy->shouldRetry($attemptCount)) {
            return $this->markDead($delivery, $error);
        }

        $nextRetryAt = $this->retryPolicy->nextRetryAt($attemptCount);

        $delivery->forceFill([
            'status' => HubRelayDelivery::STATUS_FAILED,
            'last_error' => $error,
            'next_retry_at' => $nextRetryAt,
        ])->save();

        return [
            'status' => HubRelayDelivery::STATUS_FAILED,
            'should_retry' => true,
            'next_retry_at' => $nextRetryAt,
            'error' => $error,
        ];
    }

    /**
     * @return array{status: string, should_retry: bool, next_retry_at: null, error: string}
     */
    private function markDead(HubRelayDelivery $delivery, string $error): array
    {
        $delivery->forceFill([
            'status' => HubRelayDelivery::STATUS_DEAD,
            'last_error' => $error,
            'next_retry_at' => null,
        ])->save();

        return [
            'status' => HubRelayDelivery::STATUS_DEAD,
            'should_retry' => false,
            'next_retry_at' => null,
            'error' => $error,
        ];
    }
}
