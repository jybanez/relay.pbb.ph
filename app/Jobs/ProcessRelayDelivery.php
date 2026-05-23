<?php

namespace App\Jobs;

use App\Models\HubRelayDelivery;
use App\Relay\Outbound\RelayDeliveryService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessRelayDelivery implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $deliveryId,
        public ?string $expectedNextRetryAt = null,
    ) {
        $this->onQueue((string) config('relay.delivery.queue', 'relay-deliveries'));
    }

    public function handle(RelayDeliveryService $deliveryService): void
    {
        $delivery = HubRelayDelivery::query()->find($this->deliveryId);

        if ($delivery === null) {
            return;
        }

        if ($this->expectedNextRetryAt !== null) {
            $currentNextRetryAt = $delivery->next_retry_at?->toIso8601String();

            if ($currentNextRetryAt !== $this->expectedNextRetryAt) {
                return;
            }
        }

        $result = $deliveryService->process($delivery);

        if ($result['should_retry'] && $result['next_retry_at'] !== null) {
            static::dispatch($delivery->id, $result['next_retry_at']->toIso8601String())
                ->delay($result['next_retry_at']);
        }
    }
}
