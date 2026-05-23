<?php

namespace App\Relay\Handlers;

use App\Jobs\DispatchRelayToLocalHandler;
use App\Models\HubRelayHandler;
use App\Models\HubRelayHandlerDispatch;
use App\Models\HubRelayMessage;
use App\Models\HubRelayReceipt;
use Illuminate\Support\Str;

class LocalHandlerDispatchService
{
    public function dispatchForInboundMessage(HubRelayMessage $message, HubRelayReceipt $receipt): int
    {
        $targetSystems = collect($message->target_systems ?? [])
            ->filter(fn ($systemCode) => is_string($systemCode) && $systemCode !== '')
            ->values();

        if ($targetSystems->isEmpty()) {
            return 0;
        }

        $handlers = HubRelayHandler::query()
            ->whereHas('client', fn ($clientQuery) => $clientQuery->whereIn('system_code', $targetSystems->all()))
            ->where('is_active', true)
            ->get()
            ->filter(fn (HubRelayHandler $handler): bool => $this->matches($handler, $message))
            ->values();

        foreach ($handlers as $handler) {
            $dispatch = HubRelayHandlerDispatch::query()->create([
                'hub_relay_handler_id' => $handler->id,
                'hub_relay_message_id' => $message->id,
                'hub_relay_receipt_id' => $receipt->id,
                'status' => HubRelayHandlerDispatch::STATUS_QUEUED,
                'queued_at' => now(),
            ]);

            DispatchRelayToLocalHandler::dispatch($dispatch->id);
        }

        return $handlers->count();
    }

    private function matches(HubRelayHandler $handler, HubRelayMessage $message): bool
    {
        if (!Str::is($handler->message_type_pattern ?: '*', $message->message_type)) {
            return false;
        }

        if ($handler->source_system !== null && $handler->source_system !== $message->source_system) {
            return false;
        }

        if ($handler->source_hub_id !== null && $handler->source_hub_id !== $message->source_hub_id) {
            return false;
        }

        return true;
    }
}
