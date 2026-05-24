<?php

namespace Tests\Feature\Relay;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HqHeartbeatTest extends TestCase
{
    use RefreshDatabase;

    private string $publicPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->publicPath = storage_path('framework/testing/hq-heartbeat-public');
        File::deleteDirectory($this->publicPath);
        File::ensureDirectoryExists($this->publicPath);
        $this->app->usePublicPath($this->publicPath);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->publicPath);

        parent::tearDown();
    }

    public function test_hq_heartbeat_command_posts_payload_and_hydrates_public_snapshot(): void
    {
        config([
            'app.url' => 'https://relay.pbb.ph',
            'relay.hq_registry.base_url' => 'https://hub.pbb.ph',
            'relay.hq_registry.token' => 'hub-token',
            'relay.local_hub_id' => null,
            'relay.hq_registry.local_relay_hub_id' => '072217029',
            'relay.hq_registry.local_hq_id' => 12,
            'relay.hq_heartbeat.enabled' => true,
        ]);

        Http::fake([
            'https://hub.pbb.ph/api/hubs/heartbeat' => Http::response([
                'status' => true,
                'data' => [
                    'hub' => [
                        'base_url' => 'https://hub.pbb.ph',
                        'hub_id' => 12,
                        'relay_hub_id' => '072217029',
                        'name' => 'Guadalupe, CEBU CITY, CEBU',
                        'deployment' => 'barangay',
                        'domain' => 'guadalupe-cebu-cebu.pbb.ph',
                        'status' => 'active',
                        'token' => 'must-not-leak',
                        'uplinks' => [
                            [
                                'id' => 29,
                                'uplink_hub_id' => 11,
                                'uplink_type' => 'hierarchy',
                                'uplink_domain' => 'cebu-cebu.pbb.ph',
                                'priority' => 1,
                                'is_primary' => true,
                                'hub' => [
                                    'id' => 11,
                                    'name' => 'CEBU CITY, CEBU',
                                    'token' => 'must-not-leak',
                                ],
                            ],
                        ],
                        'sources' => [],
                    ],
                    'snapshot_version' => 'hub-12:abc123',
                    'snapshot_hash' => 'abc123',
                ],
                'meta' => null,
                'error' => null,
            ], 200),
        ]);

        $this->artisan('relay:hq-heartbeat --once')
            ->expectsOutput('Relay HQ heartbeat accepted.')
            ->assertExitCode(0);

        Http::assertSent(function ($request): bool {
            $data = $request->data();
            $authorization = $request->header('Authorization')[0] ?? null;

            return $request->url() === 'https://hub.pbb.ph/api/hubs/heartbeat'
                && $authorization === 'Bearer hub-token'
                && $data['relay_hub_id'] === '072217029'
                && $data['hub_id'] === 12
                && $data['health']['status'] === 'healthy'
                && $data['health']['queued_deliveries'] === 0
                && $data['services']['hq_heartbeat']['status'] === 'running';
        });

        $snapshot = json_decode((string) file_get_contents($this->publicPath.DIRECTORY_SEPARATOR.'hub.json'), true);

        $this->assertSame('072217029', $snapshot['relay_hub_id'] ?? null);
        $this->assertSame('hq_heartbeat', $snapshot['hydrated_from'] ?? null);
        $this->assertSame('hub-12:abc123', $snapshot['snapshot_version'] ?? null);
        $this->assertSame('abc123', $snapshot['hq_snapshot_hash'] ?? null);
        $this->assertArrayHasKey('snapshot_hash', $snapshot);
        $this->assertArrayNotHasKey('token', $snapshot);
        $this->assertArrayNotHasKey('token', $snapshot['uplinks'][0]['hub'] ?? []);
    }

    public function test_hq_heartbeat_failure_keeps_last_valid_snapshot(): void
    {
        config([
            'relay.hq_registry.base_url' => 'https://hub.pbb.ph',
            'relay.hq_registry.token' => 'hub-token',
            'relay.local_hub_id' => null,
            'relay.hq_registry.local_relay_hub_id' => '072217029',
            'relay.hq_registry.local_hq_id' => 12,
            'relay.hq_heartbeat.enabled' => true,
        ]);

        $path = $this->publicPath.DIRECTORY_SEPARATOR.'hub.json';
        File::put($path, json_encode([
            'relay_hub_id' => '072217029',
            'name' => 'Last Valid Snapshot',
            'hydrated_from' => 'install',
        ], JSON_PRETTY_PRINT).PHP_EOL);

        Http::fake([
            'https://hub.pbb.ph/api/hubs/heartbeat' => Http::response([
                'status' => false,
                'data' => null,
                'meta' => null,
                'error' => 'Heartbeat relay_hub_id does not match the authenticated hub.',
            ], 409),
        ]);

        $this->artisan('relay:hq-heartbeat --once')
            ->expectsOutput('Relay HQ heartbeat not sent: HQ heartbeat failed with HTTP 409.')
            ->assertExitCode(1);

        $snapshot = json_decode((string) file_get_contents($path), true);

        $this->assertSame('Last Valid Snapshot', $snapshot['name'] ?? null);
        $this->assertSame('install', $snapshot['hydrated_from'] ?? null);
    }
}
