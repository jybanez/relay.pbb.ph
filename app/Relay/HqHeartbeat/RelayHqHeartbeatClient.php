<?php

namespace App\Relay\HqHeartbeat;

use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class RelayHqHeartbeatClient
{
    public function __construct(
        private HttpFactory $http,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array{hub:array<string,mixed>,snapshot_version:?string,snapshot_hash:?string}
     */
    public function send(array $payload): array
    {
        $baseUrl = config('relay.hq_registry.base_url');
        $token = config('relay.hq_registry.token');

        if (! is_string($baseUrl) || trim($baseUrl) === '' || ! is_string($token) || trim($token) === '') {
            throw new RuntimeException('HQ heartbeat is not configured.');
        }

        $path = (string) config('relay.hq_heartbeat.path', '/api/hubs/heartbeat');
        $url = rtrim($baseUrl, '/').'/'.ltrim($path, '/');

        $request = $this->http
            ->acceptJson()
            ->asJson()
            ->withToken($token)
            ->connectTimeout((int) config('relay.hq_heartbeat.connect_timeout_seconds', 3))
            ->timeout((int) config('relay.hq_heartbeat.timeout_seconds', 8));

        $verify = $this->verifyOption();
        if ($verify !== true) {
            $request = $request->withOptions(['verify' => $verify]);
        }

        $response = $request->post($url, $payload);

        if (! $response->successful()) {
            Log::warning('Relay HQ heartbeat was rejected.', [
                'status' => $response->status(),
                'body' => mb_substr($response->body(), 0, 1000),
            ]);

            throw new RuntimeException('HQ heartbeat failed with HTTP '.$response->status().'.');
        }

        $body = $response->json();
        if (! is_array($body) || ($body['status'] ?? false) !== true || ! is_array($body['data'] ?? null)) {
            throw new RuntimeException('HQ heartbeat returned an unexpected response wrapper.');
        }

        $hub = $body['data']['hub'] ?? null;
        if (! is_array($hub)) {
            throw new RuntimeException('HQ heartbeat response did not include data.hub.');
        }

        return [
            'hub' => $hub,
            'snapshot_version' => is_string($body['data']['snapshot_version'] ?? null) ? $body['data']['snapshot_version'] : null,
            'snapshot_hash' => is_string($body['data']['snapshot_hash'] ?? null) ? $body['data']['snapshot_hash'] : null,
        ];
    }

    private function verifyOption(): bool|string
    {
        if (! (bool) config('relay.hq_heartbeat.tls_verify', true)) {
            return false;
        }

        $caBundle = config('relay.hq_heartbeat.ca_bundle');
        if (is_string($caBundle) && $caBundle !== '') {
            return $caBundle;
        }

        return true;
    }
}
