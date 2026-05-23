<?php

namespace App\Http\Controllers;

use App\Models\HubRelayClient;
use App\Models\HubRelayHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RelayClientAdminController extends Controller
{
    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $this->abortUnlessAdmin($request);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'system_code' => ['required', 'string', 'max:100', Rule::unique('hub_relay_clients', 'system_code')],
            'description' => ['nullable', 'string'],
        ]);

        $apiKey = HubRelayClient::generateApiKey();

        $client = HubRelayClient::create([
            'name' => $validated['name'],
            'system_code' => $validated['system_code'],
            'description' => $validated['description'] ?? null,
            'api_key' => $apiKey,
            'is_active' => true,
        ]);

        $message = 'Client created. Save the generated API key now; it will not be shown in full again automatically.';

        if ($this->wantsJson($request)) {
            return response()->json([
                'success' => true,
                'status_message' => $message,
                'generated_api_key' => $apiKey,
                'redirect_url' => '/relay/client/'.$client->id,
            ]);
        }

        return redirect('/relay/client/'.$client->id)
            ->with('status', $message)
            ->with('generated_api_key', $apiKey);
    }

    public function rotateKey(Request $request, HubRelayClient $client): RedirectResponse|JsonResponse
    {
        $this->abortUnlessAdmin($request);

        $apiKey = HubRelayClient::generateApiKey();

        $client->forceFill([
            'api_key' => $apiKey,
            'is_active' => true,
        ])->save();

        if ($this->wantsJson($request)) {
            return response()->json([
                'success' => true,
                'status_message' => 'API key rotated. The previous key is no longer valid.',
                'generated_api_key' => $apiKey,
            ]);
        }

        return redirect('/relay/client/'.$client->id)
            ->with('status', 'API key rotated. The previous key is no longer valid.')
            ->with('generated_api_key', $apiKey);
    }

    public function toggleActive(Request $request, HubRelayClient $client): RedirectResponse|JsonResponse
    {
        $this->abortUnlessAdmin($request);

        $client->forceFill([
            'is_active' => ! $client->is_active,
        ])->save();

        $message = $client->is_active ? 'Client reactivated.' : 'Client deactivated.';

        if ($this->wantsJson($request)) {
            return response()->json([
                'success' => true,
                'status_message' => $message,
                'is_active' => (bool) $client->is_active,
            ]);
        }

        return redirect('/relay/client/'.$client->id)
            ->with('status', $message);
    }

    public function storeHandler(Request $request, HubRelayClient $client): RedirectResponse|JsonResponse
    {
        $this->abortUnlessAdmin($request);

        $validated = $this->validateHandler($request);

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

        if ($this->wantsJson($request)) {
            return response()->json([
                'success' => true,
                'status_message' => 'Handler created.',
                'handler_id' => (string) $handler->id,
            ]);
        }

        return redirect('/relay/client/'.$client->id)
            ->with('status', 'Handler created.');
    }

    public function updateHandler(Request $request, HubRelayClient $client, HubRelayHandler $handler): RedirectResponse|JsonResponse
    {
        $this->abortUnlessAdmin($request);
        $this->abortUnlessOwnedByClient($client, $handler);

        $validated = $this->validateHandler($request);

        $handler->forceFill([
            'name' => $validated['name'],
            'endpoint_url' => $validated['endpoint_url'],
            'message_type_pattern' => $validated['message_type_pattern'] ?? '*',
            'source_system' => $validated['source_system'] ?? null,
            'source_hub_id' => $validated['source_hub_id'] ?? null,
            'auth_token' => $validated['auth_token'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
        ])->save();

        if ($this->wantsJson($request)) {
            return response()->json([
                'success' => true,
                'status_message' => 'Handler updated.',
                'handler_id' => (string) $handler->id,
            ]);
        }

        return redirect('/relay/client/'.$client->id)
            ->with('status', 'Handler updated.');
    }

    public function toggleHandlerActive(Request $request, HubRelayClient $client, HubRelayHandler $handler): RedirectResponse|JsonResponse
    {
        $this->abortUnlessAdmin($request);
        $this->abortUnlessOwnedByClient($client, $handler);

        $handler->forceFill([
            'is_active' => ! $handler->is_active,
        ])->save();

        if ($this->wantsJson($request)) {
            return response()->json([
                'success' => true,
                'status_message' => $handler->is_active ? 'Handler reactivated.' : 'Handler deactivated.',
                'handler_id' => (string) $handler->id,
            ]);
        }

        return redirect('/relay/client/'.$client->id)
            ->with('status', $handler->is_active ? 'Handler reactivated.' : 'Handler deactivated.');
    }

    private function abortUnlessAdmin(Request $request): void
    {
        abort_unless($request->user()?->isRelayAdmin(), 403);
    }

    private function abortUnlessOwnedByClient(HubRelayClient $client, HubRelayHandler $handler): void
    {
        abort_unless($handler->hub_relay_client_id === $client->id, 404);
    }

    private function validateHandler(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'endpoint_url' => ['required', 'url', 'max:2048'],
            'message_type_pattern' => ['nullable', 'string', 'max:120'],
            'source_system' => ['nullable', 'string', 'max:120'],
            'source_hub_id' => ['nullable', 'string', 'max:120'],
            'auth_token' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
        ]);
    }

    private function wantsJson(Request $request): bool
    {
        return $request->expectsJson()
            || $request->wantsJson()
            || $request->isXmlHttpRequest();
    }
}
