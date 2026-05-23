<?php

namespace App\Http\Middleware;

use App\Models\HubRelayClient;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateRelayClient
{
    public function handle(Request $request, Closure $next): Response
    {
        $apiKey = $request->header('X-Relay-Key');

        if (!is_string($apiKey) || $apiKey === '') {
            return $this->unauthorized('Missing X-Relay-Key header');
        }

        $client = HubRelayClient::query()
            ->where('api_key', $apiKey)
            ->where('is_active', true)
            ->first();

        if ($client === null) {
            return $this->unauthorized('Invalid relay client credentials');
        }

        $client->forceFill([
            'last_used_at' => now(),
        ])->save();

        $request->attributes->set('relay_client', $client);

        return $next($request);
    }

    private function unauthorized(string $message): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => $message,
        ], 401);
    }
}
