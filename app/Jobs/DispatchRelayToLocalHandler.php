<?php

namespace App\Jobs;

use App\Models\HubRelayHandler;
use App\Models\HubRelayHandlerDispatch;
use App\Models\HubRelayMessage;
use App\Models\HubRelayReceipt;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class DispatchRelayToLocalHandler implements ShouldQueue
{
    use InteractsWithQueue;
    use Queueable;

    public int $tries;
    public array $backoff;

    public function __construct(
        public string $dispatchId,
    ) {
        $this->onQueue((string) config('relay.local_handlers.queue', 'relay-handlers'));
        $this->tries = (int) config('relay.local_handlers.max_attempts', 3);
        $this->backoff = array_values((array) config('relay.local_handlers.backoff_seconds', [30, 120, 600]));
    }

    public function handle(): void
    {
        $dispatch = HubRelayHandlerDispatch::query()->find($this->dispatchId);

        if ($dispatch === null) {
            return;
        }

        $handler = HubRelayHandler::query()->find($dispatch->hub_relay_handler_id);
        $message = HubRelayMessage::query()->find($dispatch->hub_relay_message_id);
        $receipt = HubRelayReceipt::query()->find($dispatch->hub_relay_receipt_id);

        if ($handler === null || $message === null || $receipt === null || !$handler->is_active) {
            return;
        }

        $attempt = $this->attempts();

        $dispatch->forceFill([
            'status' => HubRelayHandlerDispatch::STATUS_SENDING,
            'attempt_count' => $attempt,
            'last_attempt_at' => now(),
            'next_retry_at' => $attempt < $this->tries ? $this->nextRetryAt($attempt) : null,
            'last_error' => null,
        ])->save();

        $request = Http::acceptJson()
            ->timeout((int) config('relay.local_handlers.timeout_seconds', 10))
            ->withHeaders([
                'X-Relay-Event' => 'relay.message.received',
                'X-Relay-Handler-Id' => $handler->id,
                'X-Relay-Receipt-Id' => $receipt->id,
                'X-Relay-Relay-Id' => $message->relay_id,
            ]);

        if (is_string($handler->auth_token) && $handler->auth_token !== '') {
            $request = $request->withToken($handler->auth_token);
        }

        $response = $request->post($handler->endpoint_url, [
            'event' => 'relay.message.received',
            'message' => [
                'id' => $message->id,
                'relay_id' => $message->relay_id,
                'origin_hq_hub_id' => $message->origin_hq_hub_id,
                'source_hub_id' => $message->source_hub_id,
                'source_system' => $message->source_system,
                'targets' => $message->canonicalTargets(),
                'hop_trace' => $message->hop_trace,
                'message_type' => $message->message_type,
                'payload_format' => $message->payload_format,
                'payload_version' => $message->payload_version,
                'payload' => $message->payload,
                'attachments_count' => $message->attachments_count,
                'correlation_id' => $message->correlation_id,
                'priority' => $message->priority,
                'occurred_at' => $message->occurred_at?->toIso8601String(),
                'received_at' => $message->created_at?->toIso8601String(),
            ],
            'receipt' => [
                'id' => $receipt->id,
                'status' => $receipt->status,
                'received_at' => $receipt->received_at?->toIso8601String(),
                'processed_at' => $receipt->processed_at?->toIso8601String(),
            ],
        ]);

        if ($response->failed()) {
            $dispatch->forceFill([
                'status' => $attempt < $this->tries ? HubRelayHandlerDispatch::STATUS_FAILED : HubRelayHandlerDispatch::STATUS_DEAD,
                'last_response_status' => $response->status(),
                'last_error' => 'Local handler rejected relay message with status '.$response->status().'.',
                'failed_at' => now(),
                'next_retry_at' => $attempt < $this->tries ? $this->nextRetryAt($attempt) : null,
            ])->save();

            throw new RuntimeException('Local handler rejected relay message with status '.$response->status().'.');
        }

        $dispatch->forceFill([
            'status' => HubRelayHandlerDispatch::STATUS_SUCCEEDED,
            'last_response_status' => $response->status(),
            'succeeded_at' => now(),
            'failed_at' => null,
            'next_retry_at' => null,
            'last_error' => null,
        ])->save();

        $handler->forceFill([
            'last_dispatched_at' => now(),
            'last_succeeded_at' => now(),
            'last_error' => null,
        ])->save();
    }

    public function failed(?Throwable $exception): void
    {
        $dispatch = HubRelayHandlerDispatch::query()->find($this->dispatchId);

        if ($dispatch === null) {
            return;
        }

        $handler = HubRelayHandler::query()->find($dispatch->hub_relay_handler_id);

        if ($handler === null) {
            return;
        }

        $dispatch->forceFill([
            'status' => HubRelayHandlerDispatch::STATUS_DEAD,
            'failed_at' => now(),
            'next_retry_at' => null,
            'last_error' => $exception?->getMessage(),
        ])->save();

        $handler->forceFill([
            'last_dispatched_at' => now(),
            'last_failed_at' => now(),
            'last_error' => $exception?->getMessage(),
        ])->save();
    }

    private function nextRetryAt(int $attempt): ?\Illuminate\Support\Carbon
    {
        $backoff = $this->backoff;
        $index = max(0, $attempt - 1);
        $seconds = $backoff[$index] ?? end($backoff);

        if (!is_int($seconds) || $seconds < 1) {
            return null;
        }

        return now()->addSeconds($seconds);
    }
}
