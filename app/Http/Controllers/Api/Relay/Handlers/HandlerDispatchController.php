<?php

namespace App\Http\Controllers\Api\Relay\Handlers;

use App\Http\Controllers\Controller;
use App\Jobs\DispatchRelayToLocalHandler;
use App\Models\HubRelayClient;
use App\Models\HubRelayHandlerDispatch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HandlerDispatchController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $client = $this->relayClient($request);

        $query = HubRelayHandlerDispatch::query()
            ->with(['handler:id,hub_relay_client_id,name,message_type_pattern', 'message:id,relay_id,message_type'])
            ->whereHas('handler', fn ($handlerQuery) => $handlerQuery->where('hub_relay_client_id', $client->id))
            ->latest('updated_at');

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('handler_id')) {
            $query->where('hub_relay_handler_id', $request->string('handler_id'));
        }

        $dispatches = $query->paginate($request->integer('limit', 25));

        return response()->json([
            'data' => $dispatches->items(),
            'pagination' => [
                'total' => $dispatches->total(),
                'per_page' => $dispatches->perPage(),
                'current_page' => $dispatches->currentPage(),
                'last_page' => $dispatches->lastPage(),
            ],
        ]);
    }

    public function show(Request $request, HubRelayHandlerDispatch $dispatch): JsonResponse
    {
        $client = $this->relayClient($request);
        $this->abortIfNotOwnedByClient($dispatch, $client);

        return response()->json([
            'dispatch' => $dispatch->load([
                'handler:id,hub_relay_client_id,name,endpoint_url,message_type_pattern,source_system,source_hub_id',
                'message:id,relay_id,source_hub_id,source_system,message_type,occurred_at',
                'receipt:id,relay_id,status,received_at,processed_at',
            ]),
        ]);
    }

    public function retry(Request $request, HubRelayHandlerDispatch $dispatch): JsonResponse
    {
        $client = $this->relayClient($request);
        $this->abortIfNotOwnedByClient($dispatch, $client);

        if (!in_array($dispatch->status, [
            HubRelayHandlerDispatch::STATUS_FAILED,
            HubRelayHandlerDispatch::STATUS_DEAD,
        ], true)) {
            return response()->json([
                'success' => false,
                'error' => 'Can only retry failed or dead handler dispatches',
            ], 422);
        }

        $dispatch->forceFill([
            'status' => HubRelayHandlerDispatch::STATUS_QUEUED,
            'next_retry_at' => null,
            'failed_at' => null,
            'succeeded_at' => null,
            'last_error' => null,
            'last_response_status' => null,
            'queued_at' => now(),
        ])->save();

        DispatchRelayToLocalHandler::dispatch($dispatch->id)
            ->onQueue((string) config('relay.local_handlers.queue', 'relay-handlers'));

        return response()->json([
            'success' => true,
            'dispatch' => $dispatch->fresh(),
        ]);
    }

    private function relayClient(Request $request): HubRelayClient
    {
        return $request->attributes->get('relay_client');
    }

    private function abortIfNotOwnedByClient(HubRelayHandlerDispatch $dispatch, HubRelayClient $client): void
    {
        abort_unless($dispatch->handler?->hub_relay_client_id === $client->id, 404);
    }
}
