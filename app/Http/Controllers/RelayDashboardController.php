<?php

namespace App\Http\Controllers;

use App\Models\HubRelayClient;
use App\Models\HubRelayDelivery;
use App\Models\HubRelayHandler;
use App\Models\HubRelayHandlerDispatch;
use App\Models\HubRelayMessage;
use App\Models\HubRelayReceipt;
use App\Models\HubRelayUploadSession;
use App\Relay\Diagnostics\RelayDiagnosticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Contracts\View\View;

class RelayDashboardController extends Controller
{
    public function __construct(
        private RelayDiagnosticsService $diagnostics,
    ) {}

    public function __invoke(): View
    {
        return view('relay.dashboard', [
            'appName' => config('app.name'),
            'appUrl' => config('app.url'),
            'dataUrl' => '/relay/data/dashboard',
        ]);
    }

    public function data(): JsonResponse
    {
        return response()->json($this->payload());
    }

    private function payload(): array
    {
        $diagnostics = $this->diagnostics->getDiagnostics();
        $recentMessages = HubRelayMessage::query()
            ->latest('created_at')
            ->limit(8)
            ->get();

        $recentDeliveries = HubRelayDelivery::query()
            ->with('message:id,relay_id,message_type,priority')
            ->latest('updated_at')
            ->limit(10)
            ->get();

        $recentReceipts = HubRelayReceipt::query()
            ->latest('received_at')
            ->limit(8)
            ->get();

        $recentUploads = HubRelayUploadSession::query()
            ->with('attachment:id,name')
            ->latest('updated_at')
            ->limit(8)
            ->get();

        $clients = HubRelayClient::query()
            ->latest('last_used_at')
            ->limit(8)
            ->get();

        $handlers = HubRelayHandler::query()
            ->with('client:id,name,system_code')
            ->latest('updated_at')
            ->limit(8)
            ->get();

        $handlerDispatches = HubRelayHandlerDispatch::query()
            ->with([
                'handler:id,name,hub_relay_client_id',
                'message:id,relay_id,message_type',
            ])
            ->latest('updated_at')
            ->limit(10)
            ->get();

        $hubStatus = HubRelayDelivery::query()
            ->selectRaw('target_hub_id')
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as queued_count', [HubRelayDelivery::STATUS_QUEUED])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as failed_count', [HubRelayDelivery::STATUS_FAILED])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as dead_count', [HubRelayDelivery::STATUS_DEAD])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as delivered_count', [HubRelayDelivery::STATUS_DELIVERED])
            ->selectRaw('MAX(delivered_at) as last_delivered_at')
            ->groupBy('target_hub_id')
            ->orderBy('target_hub_id')
            ->get();

        return [
            'appName' => config('app.name'),
            'appUrl' => config('app.url'),
            'health' => [
                'status' => $diagnostics['health']['status'] ?? 'healthy',
            ],
            'timestamp' => $diagnostics['timestamp'] ?? now()->toIso8601String(),
            'diagnostics' => $diagnostics,
            'metrics' => [
                'queuedDeliveries' => (int) ($diagnostics['queue_status']['total_queued'] ?? 0),
                'failedDeliveries' => (int) ($diagnostics['queue_status']['failed_deliveries'] ?? 0),
                'deadDeliveries' => (int) ($diagnostics['queue_status']['dead_letter_deliveries'] ?? 0),
                'inboundReceipts' => (int) ($diagnostics['inbox_summary']['total_receipts'] ?? 0),
            ],
            'hubStatus' => $hubStatus->map(fn ($hub) => [
                'target_hub_id' => $hub->target_hub_id,
                'queued_count' => (int) $hub->queued_count,
                'failed_count' => (int) $hub->failed_count,
                'dead_count' => (int) $hub->dead_count,
                'delivered_count' => (int) $hub->delivered_count,
                'last_delivered_at_human' => $hub->last_delivered_at ? \Illuminate\Support\Carbon::parse($hub->last_delivered_at)->diffForHumans() : 'Never',
            ])->values(),
            'recentDeliveries' => $recentDeliveries->map(fn ($delivery) => [
                'relay_id' => $delivery->message?->relay_id ?? $delivery->hub_relay_message_id,
                'message_type' => $delivery->message?->message_type ?? 'Unknown type',
                'target_hub_id' => $delivery->target_hub_id,
                'status' => $delivery->status,
                'attempt_count' => (int) $delivery->attempt_count,
                'updated_at_human' => optional($delivery->updated_at)->diffForHumans(),
            ])->values(),
            'recentUploads' => $recentUploads->map(fn ($upload) => [
                'attachment_name' => $upload->attachment_name,
                'session_id' => $upload->id,
                'direction' => $upload->direction,
                'transfer_status' => $upload->transfer_status,
                'progress_percent' => number_format((float) $upload->transfer_progress_percent, 2).'%',
            ])->values(),
            'recentMessages' => $recentMessages->map(fn ($message) => [
                'message_type' => $message->message_type,
                'relay_id' => $message->relay_id,
                'source' => $message->source_system.' from '.$message->source_hub_id,
                'received_at_human' => optional($message->created_at)->diffForHumans(),
            ])->values(),
            'recentReceipts' => $recentReceipts->map(fn ($receipt) => [
                'message_type' => $receipt->message_type,
                'source_hub_id' => $receipt->source_hub_id,
                'status' => $receipt->status,
                'relay_id' => $receipt->relay_id,
            ])->values(),
            'clients' => $clients->map(fn ($client) => [
                'name' => $client->name,
                'system_code' => $client->system_code,
                'last_used' => $client->last_used_at ? 'Last used '.$client->last_used_at->diffForHumans() : 'No usage yet',
            ])->values(),
            'handlers' => $handlers->map(fn ($handler) => [
                'name' => $handler->name,
                'client' => $handler->client?->system_code ?? 'Unknown client',
                'message_type_pattern' => $handler->message_type_pattern,
                'status' => $handler->last_succeeded_at
                    ? 'Last success '.$handler->last_succeeded_at->diffForHumans()
                    : ($handler->last_failed_at ? 'Last failure '.$handler->last_failed_at->diffForHumans() : 'No dispatches yet'),
            ])->values(),
            'handlerDispatches' => $handlerDispatches->map(fn ($dispatch) => [
                'handler_name' => $dispatch->handler?->name ?? 'Unknown handler',
                'relay_id' => $dispatch->message?->relay_id ?? $dispatch->hub_relay_message_id,
                'message_type' => $dispatch->message?->message_type ?? 'Unknown type',
                'status' => $dispatch->status,
                'attempt_count' => (int) $dispatch->attempt_count,
                'next_retry_at' => $dispatch->next_retry_at?->diffForHumans() ?? 'Not scheduled',
                'last_error' => $dispatch->last_error ?: 'None',
            ])->values(),
        ];
    }
}
