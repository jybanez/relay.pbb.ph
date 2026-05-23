<?php

namespace Tests\Feature\Relay\Api;

use App\Jobs\ProcessRelayDelivery;
use App\Models\HubRegistryHub;
use App\Models\HubRegistryLink;
use App\Models\HubRelayDelivery;
use App\Models\HubRelayMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class DeliveryProcessingTest extends TestCase
{
    use RefreshDatabase;

    public function test_processing_delivery_marks_it_delivered_on_successful_response(): void
    {
        config([
            'relay.targets' => [
                'city-hub' => [
                    'base_url' => 'https://city.example',
                ],
            ],
        ]);

        Http::fake([
            'https://city.example/api/v1/receive' => Http::response([
                'success' => true,
                'status' => 'received',
            ], 201),
        ]);

        $message = HubRelayMessage::factory()->create([
            'target_hub_ids' => ['city-hub'],
            'targets' => [['target_hq_hub_id' => 'city-hub', 'target_system' => 'sitrep.app']],
            'target_system' => 'sitrep.app',
            'target_systems' => ['sitrep.app'],
        ]);

        $delivery = HubRelayDelivery::create([
            'hub_relay_message_id' => $message->id,
            'target_hub_id' => 'city-hub',
            'target_hq_hub_id' => 'city-hub',
            'target_system' => 'sitrep.app',
            'status' => HubRelayDelivery::STATUS_QUEUED,
        ]);

        app()->call([new ProcessRelayDelivery($delivery->id), 'handle']);

        $delivery->refresh();

        $this->assertSame(HubRelayDelivery::STATUS_DELIVERED, $delivery->status);
        $this->assertSame(1, $delivery->attempt_count);
        $this->assertNotNull($delivery->delivered_at);
        $this->assertNull($delivery->next_retry_at);
    }

    public function test_processing_delivery_marks_failed_and_schedules_retry(): void
    {
        Queue::fake();

        config([
            'relay.targets' => [
                'city-hub' => [
                    'base_url' => 'https://city.example',
                ],
            ],
            'relay.delivery.max_attempts' => 5,
            'relay.delivery.backoff_minutes' => [1, 5, 15],
        ]);

        Http::fake([
            'https://city.example/api/v1/receive' => Http::response([
                'success' => false,
            ], 500),
        ]);

        $message = HubRelayMessage::factory()->create([
            'target_hub_ids' => ['city-hub'],
            'targets' => [['target_hq_hub_id' => 'city-hub', 'target_system' => 'sitrep.app']],
            'target_system' => 'sitrep.app',
            'target_systems' => ['sitrep.app'],
        ]);

        $delivery = HubRelayDelivery::create([
            'hub_relay_message_id' => $message->id,
            'target_hub_id' => 'city-hub',
            'target_hq_hub_id' => 'city-hub',
            'target_system' => 'sitrep.app',
            'status' => HubRelayDelivery::STATUS_QUEUED,
        ]);

        app()->call([new ProcessRelayDelivery($delivery->id), 'handle']);

        $delivery->refresh();

        $this->assertSame(HubRelayDelivery::STATUS_FAILED, $delivery->status);
        $this->assertSame(1, $delivery->attempt_count);
        $this->assertNotNull($delivery->next_retry_at);
        $this->assertSame('Remote hub responded with HTTP 500.', $delivery->last_error);

        Queue::assertPushed(ProcessRelayDelivery::class, function (ProcessRelayDelivery $job) use ($delivery) {
            return $job->deliveryId === $delivery->id;
        });
    }

    public function test_processing_delivery_marks_dead_after_max_attempts(): void
    {
        Queue::fake();

        config([
            'relay.targets' => [
                'city-hub' => [
                    'base_url' => 'https://city.example',
                ],
            ],
            'relay.delivery.max_attempts' => 2,
        ]);

        Http::fake([
            'https://city.example/api/v1/receive' => Http::response([], 503),
        ]);

        $message = HubRelayMessage::factory()->create([
            'target_hub_ids' => ['city-hub'],
            'targets' => [['target_hq_hub_id' => 'city-hub', 'target_system' => 'sitrep.app']],
            'target_system' => 'sitrep.app',
            'target_systems' => ['sitrep.app'],
        ]);

        $delivery = HubRelayDelivery::create([
            'hub_relay_message_id' => $message->id,
            'target_hub_id' => 'city-hub',
            'target_hq_hub_id' => 'city-hub',
            'target_system' => 'sitrep.app',
            'status' => HubRelayDelivery::STATUS_FAILED,
            'attempt_count' => 1,
        ]);

        app()->call([new ProcessRelayDelivery($delivery->id), 'handle']);

        $delivery->refresh();

        $this->assertSame(HubRelayDelivery::STATUS_DEAD, $delivery->status);
        $this->assertSame(2, $delivery->attempt_count);
        $this->assertNull($delivery->next_retry_at);

        Queue::assertNotPushed(ProcessRelayDelivery::class);
    }

    public function test_processing_delivery_sends_hmac_headers_when_enabled(): void
    {
        config([
            'relay.targets' => [
                'city-hub' => [
                    'base_url' => 'https://city.example',
                    'token' => 'shared-city-key',
                ],
            ],
            'relay.local_hub_id' => 'barangay-hub',
            'relay.hub_auth.mode' => 'hmac',
        ]);

        Http::fake([
            'https://city.example/api/v1/receive' => Http::response([
                'success' => true,
                'status' => 'received',
            ], 201),
        ]);

        $message = HubRelayMessage::factory()->create([
            'source_hub_id' => 'barangay-hub',
            'target_hub_ids' => ['city-hub'],
            'targets' => [['target_hq_hub_id' => 'city-hub', 'target_system' => 'sitrep.app']],
            'target_system' => 'sitrep.app',
            'target_systems' => ['sitrep.app'],
        ]);

        $delivery = HubRelayDelivery::create([
            'hub_relay_message_id' => $message->id,
            'target_hub_id' => 'city-hub',
            'target_hq_hub_id' => 'city-hub',
            'target_system' => 'sitrep.app',
            'status' => HubRelayDelivery::STATUS_QUEUED,
        ]);

        app()->call([new ProcessRelayDelivery($delivery->id), 'handle']);

        Http::assertSent(function ($request) {
            return $request->hasHeader('X-Relay-Hub-Id', 'barangay-hub')
                && $request->hasHeader('X-Relay-Hub-Key', 'shared-city-key')
                && $request->hasHeader('X-Relay-Timestamp')
                && $request->hasHeader('X-Relay-Signature');
        });
    }

    public function test_processing_delivery_uses_certificate_bound_transport_without_hub_key_in_mtls_mode(): void
    {
        config([
            'relay.targets' => [
                'city-hub' => [
                    'base_url' => 'https://city.example',
                    'client_certificate_path' => 'C:/certs/relay-client.pem',
                    'client_private_key_path' => 'C:/certs/relay-client.key',
                    'ca_certificate_path' => 'C:/certs/relay-ca.pem',
                ],
            ],
            'relay.local_hub_id' => 'barangay-hub',
            'relay.hub_auth.mode' => 'mtls',
        ]);

        Http::fake([
            'https://city.example/api/v1/receive' => Http::response([
                'success' => true,
                'status' => 'received',
            ], 201),
        ]);

        $message = HubRelayMessage::factory()->create([
            'source_hub_id' => 'barangay-hub',
            'target_hub_ids' => ['city-hub'],
            'targets' => [['target_hq_hub_id' => 'city-hub', 'target_system' => 'sitrep.app']],
            'target_system' => 'sitrep.app',
            'target_systems' => ['sitrep.app'],
        ]);

        $delivery = HubRelayDelivery::create([
            'hub_relay_message_id' => $message->id,
            'target_hub_id' => 'city-hub',
            'target_hq_hub_id' => 'city-hub',
            'target_system' => 'sitrep.app',
            'status' => HubRelayDelivery::STATUS_QUEUED,
        ]);

        app()->call([new ProcessRelayDelivery($delivery->id), 'handle']);

        Http::assertSent(function ($request) {
            return $request->hasHeader('X-Relay-Hub-Id', 'barangay-hub')
                && ! $request->hasHeader('X-Relay-Hub-Key')
                && ! $request->hasHeader('X-Relay-Signature');
        });
    }

    public function test_processing_delivery_resolves_target_from_hq_registry_cache(): void
    {
        config([
            'relay.hq_registry.local_relay_hub_id' => 'barangay-hub-01',
            'relay.hq_registry.local_hq_id' => 14,
            'relay.hq_registry.outbound_topology_mode' => 'hq_uplinks',
            'relay.hub_credentials' => [
                '6' => [
                    'token' => 'shared-city-key',
                ],
                'city-hub-01' => [
                    'token' => 'shared-city-key',
                ],
            ],
            'relay.local_hub_id' => null,
        ]);

        HubRegistryHub::query()->create([
            'hq_id' => 14,
            'relay_hub_id' => 'barangay-hub-01',
            'code' => 'BARANGAY-001',
            'name' => 'Barangay Hub',
            'deployment' => 'barangay',
            'domain' => 'barangay.hub.pbb.ph',
            'status' => 'active',
        ]);

        HubRegistryHub::query()->create([
            'hq_id' => 6,
            'relay_hub_id' => 'city-hub-01',
            'code' => 'CITY-001',
            'name' => 'City Hub',
            'deployment' => 'city',
            'domain' => 'city.hub.pbb.ph',
            'status' => 'active',
        ]);

        HubRegistryLink::query()->create([
            'hub_relay_hub_id' => 'barangay-hub-01',
            'linked_relay_hub_id' => 'city-hub-01',
            'hub_hq_id' => 14,
            'linked_hq_id' => 6,
            'relationship_type' => HubRegistryLink::RELATIONSHIP_UPLINK,
            'uplink_type' => 'hierarchy',
            'is_primary' => true,
        ]);

        Http::fake([
            'https://city.hub.pbb.ph/api/v1/receive' => Http::response([
                'success' => true,
                'status' => 'received',
            ], 201),
        ]);

        $message = HubRelayMessage::factory()->create([
            'source_hub_id' => '14',
            'target_hub_ids' => ['city-hub-01'],
            'targets' => [['target_hq_hub_id' => 'city-hub-01', 'target_system' => 'sitrep.app']],
            'target_system' => 'sitrep.app',
            'target_systems' => ['sitrep.app'],
        ]);

        $delivery = HubRelayDelivery::create([
            'hub_relay_message_id' => $message->id,
            'target_hub_id' => 'city-hub-01',
            'target_hq_hub_id' => 'city-hub-01',
            'target_system' => 'sitrep.app',
            'status' => HubRelayDelivery::STATUS_QUEUED,
        ]);

        app()->call([new ProcessRelayDelivery($delivery->id), 'handle']);

        $delivery->refresh();

        $this->assertSame(HubRelayDelivery::STATUS_DELIVERED, $delivery->status);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://city.hub.pbb.ph/api/v1/receive'
                && $request->hasHeader('X-Relay-Hub-Id', 'barangay-hub-01')
                && $request->hasHeader('X-Relay-Hub-Key', 'shared-city-key');
        });
    }
}
