<?php

namespace App\Http\Controllers\Api\Relay\Handlers;

use App\Http\Controllers\Controller;
use App\Models\HubRelayClient;
use App\Models\HubRelayHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HandlerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $client = $this->relayClient($request);

        return response()->json([
            'data' => HubRelayHandler::query()
                ->where('hub_relay_client_id', $client->id)
                ->latest('created_at')
                ->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $client = $this->relayClient($request);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'endpoint_url' => 'required|url|max:2048',
            'message_type_pattern' => 'nullable|string|max:120',
            'source_system' => 'nullable|string|max:120',
            'source_hub_id' => 'nullable|string|max:120',
            'auth_token' => 'nullable|string|max:255',
            'is_active' => 'nullable|boolean',
        ]);

        $handler = HubRelayHandler::query()->create([
            'hub_relay_client_id' => $client->id,
            'name' => $validated['name'],
            'endpoint_url' => $validated['endpoint_url'],
            'message_type_pattern' => $validated['message_type_pattern'] ?? '*',
            'source_system' => $validated['source_system'] ?? null,
            'source_hub_id' => $validated['source_hub_id'] ?? null,
            'auth_token' => $validated['auth_token'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return response()->json([
            'success' => true,
            'handler' => $handler,
        ], 201);
    }

    public function update(Request $request, HubRelayHandler $handler): JsonResponse
    {
        $client = $this->relayClient($request);
        $this->abortIfNotOwnedByClient($handler, $client);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'endpoint_url' => 'sometimes|url|max:2048',
            'message_type_pattern' => 'sometimes|string|max:120',
            'source_system' => 'nullable|string|max:120',
            'source_hub_id' => 'nullable|string|max:120',
            'auth_token' => 'nullable|string|max:255',
            'is_active' => 'sometimes|boolean',
        ]);

        $handler->fill($validated)->save();

        return response()->json([
            'success' => true,
            'handler' => $handler->fresh(),
        ]);
    }

    public function destroy(Request $request, HubRelayHandler $handler): JsonResponse
    {
        $client = $this->relayClient($request);
        $this->abortIfNotOwnedByClient($handler, $client);

        $handler->forceFill([
            'is_active' => false,
        ])->save();

        return response()->json([
            'success' => true,
            'handler' => $handler->fresh(),
        ]);
    }

    private function relayClient(Request $request): HubRelayClient
    {
        return $request->attributes->get('relay_client');
    }

    private function abortIfNotOwnedByClient(HubRelayHandler $handler, HubRelayClient $client): void
    {
        abort_unless($handler->hub_relay_client_id === $client->id, 404);
    }
}
