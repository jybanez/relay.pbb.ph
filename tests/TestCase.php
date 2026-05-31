<?php

namespace Tests;

use App\Models\HubRelayClient;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\File;

abstract class TestCase extends BaseTestCase
{
    private string $testPublicPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testPublicPath = storage_path('framework/testing/public-'.getmypid());
        File::deleteDirectory($this->testPublicPath);
        File::ensureDirectoryExists($this->testPublicPath);
        $this->app->usePublicPath($this->testPublicPath);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->testPublicPath);

        parent::tearDown();
    }

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

    protected function seedHubSnapshot(int|string $hubId = 10, string $relayHubId = 'relay-hub-01'): void
    {
        $publicPath = public_path();

        File::ensureDirectoryExists($publicPath);

        File::put($publicPath.DIRECTORY_SEPARATOR.'hub.json', json_encode([
            'hub_id' => is_numeric($hubId) ? (int) $hubId : $hubId,
            'relay_hub_id' => $relayHubId,
            'name' => 'Test Hub',
            'domain' => 'relay.test',
            'status' => 'active',
            'hydrated_from' => 'test',
        ], JSON_PRETTY_PRINT).PHP_EOL);
    }

    protected function createRelayUser(array $overrides = []): User
    {
        return User::factory()->create(array_merge([
            'role' => User::ROLE_ADMIN,
            'is_active' => true,
        ], $overrides));
    }
}
