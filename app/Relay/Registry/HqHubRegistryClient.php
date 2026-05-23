<?php

namespace App\Relay\Registry;

use Illuminate\Http\Client\Factory as HttpFactory;
use RuntimeException;

class HqHubRegistryClient
{
    public function __construct(
        private HttpFactory $http,
    ) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listHubs(): array
    {
        $baseUrl = config('relay.hq_registry.base_url');
        $token = config('relay.hq_registry.token');

        if (! is_string($baseUrl) || $baseUrl === '' || ! is_string($token) || $token === '') {
            throw new RuntimeException('HQ registry API is not configured.');
        }

        $response = $this->http
            ->acceptJson()
            ->withToken($token)
            ->get(rtrim($baseUrl, '/') . '/api/hubs')
            ->throw()
            ->json();

        $hubs = $response['data']['hubs'] ?? null;

        if (! is_array($hubs)) {
            throw new RuntimeException('HQ registry returned an unexpected hubs payload.');
        }

        return $hubs;
    }
}
