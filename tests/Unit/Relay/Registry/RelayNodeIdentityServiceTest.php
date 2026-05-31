<?php

namespace Tests\Unit\Relay\Registry;

use App\Relay\Registry\RelayNodeIdentityService;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class RelayNodeIdentityServiceTest extends TestCase
{
    public function test_local_identity_is_read_from_public_hub_snapshot(): void
    {
        $this->seedHubSnapshot(12, '072217029');

        $identity = app(RelayNodeIdentityService::class);

        $this->assertSame('12', $identity->localHqId());
        $this->assertSame('072217029', $identity->localHubId());
    }

    public function test_local_identity_does_not_fallback_to_env_config(): void
    {
        config([
            'relay.local_hub_id' => 'stale-relay-hub',
            'relay.hq_registry.local_relay_hub_id' => 'stale-registry-hub',
            'relay.hq_registry.local_hq_id' => 999,
        ]);

        File::delete(public_path('hub.json'));

        $identity = app(RelayNodeIdentityService::class);

        $this->assertNull($identity->localHqId());
        $this->assertNull($identity->localHubId());
    }
}
