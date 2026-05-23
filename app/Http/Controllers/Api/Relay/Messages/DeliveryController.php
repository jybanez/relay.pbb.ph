<?php

namespace App\Http\Controllers\Api\Relay\Messages;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessRelayDelivery;
use App\Models\HubRelayClient;
use App\Models\HubRelayDelivery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeliveryController extends Controller
{
    /**
     * List deliveries
     *
     * Query parameters:
     * - status: queued, delivered, failed, dead
     * - target_hq_hub_id: filter by target hub
     * - limit: page size
     */
    public function index(Request $request): JsonResponse
    {
        $client = $this->relayClient($request);
        $query = HubRelayDelivery::query()
            ->whereHas('message', fn ($messageQuery) => $messageQuery->where('hub_relay_client_id', $client->id));

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->has('target_hq_hub_id')) {
            $query->where('target_hq_hub_id', $request->input('target_hq_hub_id'));
        }

        $deliveries = $query->latest('created_at')
            ->paginate($request->input('limit', 25));

        return response()->json([
            'data' => $deliveries->items(),
            'pagination' => [
                'total' => $deliveries->total(),
                'per_page' => $deliveries->perPage(),
                'current_page' => $deliveries->currentPage(),
                'last_page' => $deliveries->lastPage(),
            ],
        ]);
    }

    /**
     * Get details of a specific delivery
     */
    public function show(Request $request, HubRelayDelivery $delivery): JsonResponse
    {
        $this->abortIfNotOwnedByClient($delivery, $this->relayClient($request));

        return response()->json([
            'delivery' => $delivery,
            'message' => $delivery->message()->select(
                'id', 'relay_id', 'source_system', 'message_type', 'priority', 'created_at'
            )->first(),
        ]);
    }

    /**
     * Retry a failed delivery
     */
    public function retry(Request $request, HubRelayDelivery $delivery): JsonResponse
    {
        $this->abortIfNotOwnedByClient($delivery, $this->relayClient($request));

        if (!in_array($delivery->status, [HubRelayDelivery::STATUS_FAILED, HubRelayDelivery::STATUS_DEAD])) {
            return response()->json([
                'success' => false,
                'error' => 'Can only retry failed or dead deliveries',
            ], 422);
        }

        // Reset to queued for retry
        $delivery->update([
            'status' => HubRelayDelivery::STATUS_QUEUED,
            'attempt_count' => 0,
            'last_error' => null,
            'next_retry_at' => null,
            'delivered_at' => null,
        ]);

        ProcessRelayDelivery::dispatch($delivery->id)
            ->onQueue((string) config('relay.delivery.queue', 'relay-deliveries'));

        return response()->json([
            'success' => true,
            'delivery' => $delivery,
        ]);
    }

    /**
     * Cancel a delivery
     */
    public function cancel(Request $request, HubRelayDelivery $delivery): JsonResponse
    {
        $this->abortIfNotOwnedByClient($delivery, $this->relayClient($request));

        if ($delivery->status === HubRelayDelivery::STATUS_DELIVERED) {
            return response()->json([
                'success' => false,
                'error' => 'Cannot cancel an already delivered message',
            ], 422);
        }

        $delivery->update([
            'status' => HubRelayDelivery::STATUS_DEAD,
            'last_error' => 'Cancelled by local application',
        ]);

        return response()->json([
            'success' => true,
            'delivery' => $delivery,
        ]);
    }

    private function relayClient(Request $request): HubRelayClient
    {
        return $request->attributes->get('relay_client');
    }

    private function abortIfNotOwnedByClient(HubRelayDelivery $delivery, HubRelayClient $client): void
    {
        abort_unless($delivery->message?->hub_relay_client_id === $client->id, 404);
    }
}
