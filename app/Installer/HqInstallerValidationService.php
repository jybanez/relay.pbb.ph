<?php

namespace App\Installer;

use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\RequestException;
use RuntimeException;

class HqInstallerValidationService
{
    public function __construct(
        private HttpFactory $http,
    ) {}

    public function validate(int $hubId, string $token): array
    {
        $baseUrl = rtrim((string) config('installer.hq_api_base_url', ''), '/');

        if ($baseUrl === '') {
            throw new RuntimeException('Installer HQ API base URL is not configured.');
        }

        try {
            $payload = $this->http
                ->acceptJson()
                ->withToken($token)
                ->get($baseUrl.'/api/hubs/'.$hubId)
                ->throw()
                ->json();
        } catch (RequestException $e) {
            $status = $e->response?->status();
            $message = $e->response?->json('error')
                ?? $e->response?->json('message')
                ?? 'Unable to validate HQ hub identity.';

            throw new RuntimeException($status ? "HQ validation failed ($status): $message" : $message, 0, $e);
        }

        $hub = $payload['data']['hub'] ?? null;

        if (! is_array($hub)) {
            throw new RuntimeException('HQ returned an unexpected hub detail payload.');
        }

        if ((int) ($hub['id'] ?? 0) !== $hubId) {
            throw new RuntimeException('HQ returned a different hub record than the submitted HQ Hub ID.');
        }

        $relayHubId = trim((string) ($hub['relay_hub_id'] ?? ''));

        if ($relayHubId === '') {
            throw new RuntimeException('HQ hub record does not expose a valid relay_hub_id.');
        }

        $tokenHasValue = (bool) data_get($hub, 'token.has_token', false);
        $tokenActive = (bool) data_get($hub, 'token.is_active', false);

        if (! $tokenHasValue || ! $tokenActive) {
            throw new RuntimeException('HQ hub token is missing or inactive for this hub.');
        }

        $status = trim((string) ($hub['status'] ?? ''));

        if (in_array($status, ['retired', 'inactive'], true)) {
            throw new RuntimeException("HQ hub status [$status] is not installable.");
        }

        return [
            'hq_hub_id' => $hubId,
            'relay_hub_id' => $relayHubId,
            'name' => (string) ($hub['name'] ?? ''),
            'deployment' => (string) ($hub['deployment'] ?? 'other'),
            'status' => $status,
            'domain' => $this->normalizedAppUrl($hub['domain'] ?? null),
            'hq_api_base_url' => $baseUrl,
            'token' => $token,
            'uplinks' => is_array($hub['uplinks'] ?? null) ? $hub['uplinks'] : [],
            'raw_hub' => $hub,
        ];
    }

    private function normalizedAppUrl(mixed $domain): ?string
    {
        $value = trim((string) $domain);

        if ($value === '') {
            return null;
        }

        if (preg_match('/^https?:\/\//i', $value) === 1) {
            return $value;
        }

        return 'https://'.$value;
    }
}
