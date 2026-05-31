<?php

namespace Tests\Feature\Relay\Registry;

use App\Models\HubRegistryHub;
use App\Models\HubRegistryLink;
use App\Models\RelayNodeSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HqHubRegistrySyncCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_hq_sync_command_caches_hubs_and_links(): void
    {
        $this->seedHubSnapshot(14, 'barangay-hub-01');

        config([
            'relay.hq_registry.base_url' => 'https://hub.pbb.ph',
            'relay.hq_registry.token' => 'hq-token',
            'relay.hq_registry.sync_enabled' => true,
            'relay.hq_registry.outbound_topology_mode' => 'hq_uplinks',
            'relay.hq_registry.inbound_trust_mode' => 'known_hq_hubs',
        ]);

        Http::fake([
            'https://hub.pbb.ph/api/hubs' => Http::response([
                'status' => true,
                'data' => [
                    'hubs' => [
                        [
                            'id' => 14,
                            'relay_hub_id' => 'barangay-hub-01',
                            'name' => 'Barangay Hub',
                            'code' => 'BARANGAY-001',
                            'deployment' => 'barangay',
                            'domain' => 'barangay.hub.pbb.ph',
                            'status' => 'active',
                            'token' => [
                                'has_token' => true,
                                'is_active' => true,
                            ],
                            'uplinks' => [
                                [
                                    'id' => 21,
                                    'uplink_hub_id' => 6,
                                    'uplink_type' => 'hierarchy',
                                    'uplink_domain' => 'city.hub.pbb.ph',
                                    'priority' => 1,
                                    'is_primary' => true,
                                    'hub' => [
                                    'id' => 6,
                                    'relay_hub_id' => 'city-hub-01',
                                    'name' => 'City Hub',
                                        'code' => 'CITY-001',
                                        'deployment' => 'city',
                                        'domain' => 'city.hub.pbb.ph',
                                        'status' => 'active',
                                    ],
                                ],
                            ],
                            'sources' => [],
                        ],
                        [
                            'id' => 6,
                            'relay_hub_id' => 'city-hub-01',
                            'name' => 'City Hub',
                            'code' => 'CITY-001',
                            'deployment' => 'city',
                            'domain' => 'city.hub.pbb.ph',
                            'status' => 'active',
                            'token' => [
                                'has_token' => true,
                                'is_active' => true,
                            ],
                            'uplinks' => [],
                            'sources' => [
                                [
                                    'id' => 33,
                                    'hub_id' => 14,
                                    'uplink_type' => 'hierarchy',
                                    'priority' => 1,
                                    'is_primary' => true,
                                    'hub' => [
                                    'id' => 14,
                                    'relay_hub_id' => 'barangay-hub-01',
                                    'name' => 'Barangay Hub',
                                        'code' => 'BARANGAY-001',
                                        'deployment' => 'barangay',
                                        'domain' => 'barangay.hub.pbb.ph',
                                        'status' => 'active',
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'count' => 2,
                ],
            ], 200),
        ]);

        $this->artisan('relay:hq-sync')
            ->expectsOutput('HQ hub registry sync completed.')
            ->assertExitCode(0);

        $this->assertSame(2, HubRegistryHub::query()->count());
        $this->assertDatabaseHas('hub_registry_hubs', [
            'hq_id' => 14,
            'relay_hub_id' => 'barangay-hub-01',
            'code' => 'BARANGAY-001',
            'domain' => 'barangay.hub.pbb.ph',
        ]);
        $this->assertDatabaseHas('hub_registry_hubs', [
            'hq_id' => 6,
            'relay_hub_id' => 'city-hub-01',
            'code' => 'CITY-001',
            'domain' => 'city.hub.pbb.ph',
        ]);
        $this->assertDatabaseHas('hub_registry_links', [
            'hub_relay_hub_id' => 'barangay-hub-01',
            'linked_relay_hub_id' => 'city-hub-01',
            'relationship_type' => HubRegistryLink::RELATIONSHIP_UPLINK,
        ]);

        $nodeSetting = RelayNodeSetting::query()->find(1);

        $this->assertNotNull($nodeSetting);
        $this->assertSame('barangay-hub-01', $nodeSetting->local_relay_hub_id);
        $this->assertSame(14, $nodeSetting->local_hq_id);
        $this->assertSame('success', $nodeSetting->hq_last_sync_status);
    }
}
