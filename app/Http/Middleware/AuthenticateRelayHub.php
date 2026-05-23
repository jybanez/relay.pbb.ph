<?php

namespace App\Http\Middleware;

use App\Relay\Auth\RelayHubSignature;
use App\Relay\Auth\RelayHubTransportAuth;
use App\Relay\Registry\RelayPeerResolver;
use Closure;
use Illuminate\Support\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateRelayHub
{
    public function __construct(
        private RelayHubSignature $signature,
        private RelayHubTransportAuth $transportAuth,
        private RelayPeerResolver $peerResolver,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $hubId = $this->resolveHubId($request);
        if (!is_string($hubId) || $hubId === '') {
            return $this->unauthorized('Missing source hub identity for relay request');
        }

        $hubConfig = $this->peerResolver->resolveInbound($hubId);

        if (! is_array($hubConfig) || ! $this->peerResolver->acceptsInboundHub($hubId)) {
            return $this->unauthorized("Unknown relay hub [{$hubId}]");
        }

        if ($this->transportAuth->requiresClientCertificate()) {
            if (! $this->verifyClientCertificate($request, $hubConfig)) {
                return $this->unauthorized('Invalid relay hub client certificate');
            }
        }

        $hubKey = $request->header('X-Relay-Hub-Key');

        if ($this->transportAuth->usesHmac()) {
            if (! isset($hubConfig['token']) || ! is_string($hubConfig['token']) || $hubConfig['token'] === '') {
                return $this->unauthorized("Unknown relay hub [{$hubId}]");
            }

            if (!is_string($hubKey) || $hubKey === '') {
                return $this->unauthorized('Missing X-Relay-Hub-Key header');
            }

            if (! $this->verifyHmac($request, $hubConfig['token'])) {
                return $this->unauthorized('Invalid relay hub signature');
            }
        } elseif ($this->transportAuth->mode() === 'shared_key') {
            if (! isset($hubConfig['token']) || ! is_string($hubConfig['token']) || $hubConfig['token'] === '') {
                return $this->unauthorized("Unknown relay hub [{$hubId}]");
            }

            if (!is_string($hubKey) || $hubKey === '') {
                return $this->unauthorized('Missing X-Relay-Hub-Key header');
            }

            if (!hash_equals($hubConfig['token'], $hubKey)) {
                return $this->unauthorized('Invalid relay hub credentials');
            }
        }

        $request->attributes->set('relay_hub_id', $hubId);

        return $next($request);
    }

    private function resolveHubId(Request $request): ?string
    {
        $path = trim($request->path(), '/');

        if (str_ends_with($path, 'v1/receive')) {
            $hubId = $request->input('source_hub_id');
            return is_string($hubId) && $hubId !== '' ? $hubId : null;
        }

        if (str_ends_with($path, 'v1/receive-batch')) {
            $messages = $request->input('messages');

            if (!is_array($messages) || $messages === []) {
                return null;
            }

            $hubIds = collect($messages)
                ->pluck('source_hub_id')
                ->filter(fn ($value) => is_string($value) && $value !== '')
                ->unique()
                ->values();

            if ($hubIds->count() !== 1) {
                return null;
            }

            return $hubIds->first();
        }

        $headerHubId = $request->header('X-Relay-Hub-Id');

        return is_string($headerHubId) && $headerHubId !== '' ? $headerHubId : null;
    }

    private function unauthorized(string $message): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => $message,
        ], 401);
    }

    private function verifyHmac(Request $request, string $secret): bool
    {
        $timestamp = $request->header('X-Relay-Timestamp');
        $signature = $request->header('X-Relay-Signature');

        if (!is_string($timestamp) || $timestamp === '' || !is_string($signature) || $signature === '') {
            return false;
        }

        try {
            $requestTime = Carbon::parse($timestamp);
        } catch (\Throwable) {
            return false;
        }

        $tolerance = (int) config('relay.hub_auth.timestamp_tolerance_seconds', 300);

        if (abs(now()->diffInSeconds($requestTime, true)) > $tolerance) {
            return false;
        }

        return $this->signature->verify($timestamp, (string) $request->getContent(), $secret, $signature);
    }

    private function verifyClientCertificate(Request $request, array $hubConfig): bool
    {
        $expected = $this->transportAuth->expectedFingerprint($hubConfig);
        $presented = $this->transportAuth->resolvePresentedFingerprint($request);

        if ($expected === null || $presented === null) {
            return false;
        }

        return hash_equals($expected, $presented);
    }
}
