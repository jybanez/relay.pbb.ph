<?php

namespace Tests;

use App\Models\HubRelayClient;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function createRelayClient(array $overrides = []): HubRelayClient
    {
        return HubRelayClient::create(array_merge([
            'name' => 'Test Relay Client',
            'system_code' => 'test.app',
            'api_key' => 'test-relay-key',
            'is_active' => true,
        ], $overrides));
    }

    protected function relayHeaders(?string $apiKey = null): array
    {
        return [
            'X-Relay-Key' => $apiKey ?? 'test-relay-key',
        ];
    }

    protected function createRelayUser(array $overrides = []): User
    {
        return User::factory()->create(array_merge([
            'role' => User::ROLE_ADMIN,
            'is_active' => true,
        ], $overrides));
    }
}
