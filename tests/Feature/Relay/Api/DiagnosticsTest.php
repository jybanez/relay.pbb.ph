<?php

namespace Tests\Feature\Relay\Api;

use App\Models\HubRelayMessage;
use App\Models\HubRelayDelivery;
use App\Models\HubRelayReceipt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DiagnosticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_diagnostics_endpoint_returns_version_info(): void
    {
        $response = $this->getJson('/api/v1/diagnostics');

        $response->assertStatus(200)
            ->assertHeader('X-Relay-Protocol-Version', '1.1')
            ->assertHeader('X-Relay-Minimum-Supported-Protocol-Version', '1.0')
            ->assertHeader('X-Relay-Supported-Protocol-Versions', '1.0,1.1')
            ->assertJsonStructure([
                'version' => [
                    'relay_package_version',
                    'relay_protocol_version',
                    'minimum_supported_protocol_version',
                ],
                'queue_status' => [
                    'total_queued',
                    'total_messages',
                    'total_deliveries',
                    'failed_deliveries',
                    'dead_letter_deliveries',
                ],
                'delivery_summary',
                'inbox_summary',
                'timestamp',
            ]);

        $this->assertEquals('1.1.0', $response->json('version.relay_package_version'));
        $this->assertEquals('1.1', $response->json('version.relay_protocol_version'));
    }

    public function test_diagnostics_includes_queue_metrics(): void
    {
        // Create some test data
        $message1 = HubRelayMessage::factory()->create();
        $message2 = HubRelayMessage::factory()->create();

        HubRelayDelivery::create([
            'hub_relay_message_id' => $message1->id,
            'target_hub_id' => 'hub-1',
            'status' => 'queued',
        ]);

        HubRelayDelivery::create([
            'hub_relay_message_id' => $message2->id,
            'target_hub_id' => 'hub-2',
            'status' => 'delivered',
        ]);

        $response = $this->getJson('/api/v1/diagnostics');

        $response->assertStatus(200)
            ->assertJson([
                'queue_status' => [
                    'total_messages' => 2,
                    'total_deliveries' => 2,
                    'total_queued' => 1,
                ],
            ]);
    }

    public function test_diagnostics_includes_inbox_metrics(): void
    {
        // Create some receipts
        HubRelayReceipt::create([
            'relay_id' => '01ARZ3NDEKTSV4RRFFQ69G5FAV',
            'source_hub_id' => 'city-hub',
            'message_type' => 'sitrep.record',
            'status' => 'received',
            'received_at' => now(),
        ]);

        HubRelayReceipt::create([
            'relay_id' => '01ARZ3NDEKTSV4RRFFQ69G5FBW',
            'source_hub_id' => 'city-hub',
            'message_type' => 'sitrep.record',
            'status' => 'processed',
            'received_at' => now(),
            'processed_at' => now(),
        ]);

        $response = $this->getJson('/api/v1/diagnostics');

        $response->assertStatus(200)
            ->assertJson([
                'inbox_summary' => [
                    'total_receipts' => 2,
                    'by_status' => [
                        'received' => 1,
                        'processed' => 1,
                    ],
                ],
            ]);
    }

    public function test_compatibility_endpoint(): void
    {
        $response = $this->getJson('/api/v1/compatibility');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'version' => [
                    'relay_package_version',
                    'relay_protocol_version',
                ],
                'health' => [
                    'status',
                    'dead_letter_count',
                    'queued_count',
                ],
                'supported_auth_modes',
                'supported_protocol_versions',
                'relay_protocol_capabilities',
                'api_endpoints',
            ]);
    }

    public function test_protocol_middleware_allows_legacy_supported_version_and_marks_compatibility_mode(): void
    {
        $response = $this->getJson('/api/v1/diagnostics', [
            'X-Relay-Protocol-Version' => '1.0',
        ]);

        $response->assertOk()
            ->assertHeader('X-Relay-Requested-Protocol-Version', '1.0')
            ->assertHeader('X-Relay-Protocol-Compatibility-Mode', 'legacy')
            ->assertHeader('X-Relay-Protocol-Version', '1.1');
    }

    public function test_protocol_middleware_closes_http_connection_by_default(): void
    {
        $this->getJson('/api/v1/diagnostics')
            ->assertOk()
            ->assertHeader('Connection', 'close');
    }

    public function test_protocol_middleware_can_keep_http_connection_when_configured(): void
    {
        config(['relay.http.force_connection_close' => false]);

        $this->getJson('/api/v1/diagnostics')
            ->assertOk()
            ->assertHeaderMissing('Connection');
    }

    public function test_protocol_middleware_rejects_unsupported_version(): void
    {
        $response = $this->getJson('/api/v1/diagnostics', [
            'X-Relay-Protocol-Version' => '0.9',
        ]);

        $response->assertStatus(426)
            ->assertJson([
                'success' => false,
                'error' => 'Unsupported relay protocol version',
                'requested_protocol_version' => '0.9',
            ]);
    }

    public function test_protocol_middleware_rejects_non_supported_version_even_if_not_below_minimum(): void
    {
        config([
            'relay.version.protocol' => '1.1',
            'relay.version.minimum_supported_protocol' => '1.0',
            'relay.version.supported_protocols' => ['1.0', '1.1'],
        ]);

        $response = $this->getJson('/api/v1/diagnostics', [
            'X-Relay-Protocol-Version' => '1.2',
        ]);

        $response->assertStatus(406)
            ->assertJson([
                'success' => false,
                'error' => 'Relay protocol version is not supported by this node',
                'requested_protocol_version' => '1.2',
            ]);
    }

    public function test_status_endpoint_returns_lightweight_heartbeat_payload(): void
    {
        $this->seedHubSnapshot(10, 'relay-hub-01');

        $message = HubRelayMessage::factory()->create();

        HubRelayDelivery::create([
            'hub_relay_message_id' => $message->id,
            'target_hub_id' => 'city-hub',
            'status' => 'queued',
        ]);

        HubRelayReceipt::create([
            'relay_id' => '01ARZ3NDEKTSV4RRFFQ69G5FAV',
            'source_hub_id' => 'barangay-hub',
            'message_type' => 'sitrep.record',
            'status' => 'received',
            'received_at' => now(),
        ]);

        $response = $this->getJson('/api/status');

        $response->assertOk()
            ->assertJsonStructure([
                'status',
                'hub_id',
                'timestamp',
                'version' => [
                    'relay_package_version',
                    'relay_protocol_version',
                    'minimum_supported_protocol_version',
                ],
                'health' => [
                    'status',
                    'dead_letter_count',
                    'queued_count',
                    'last_check',
                ],
                'queue' => [
                    'queued',
                    'failed',
                    'dead',
                    'total_messages',
                    'total_deliveries',
                ],
                'inbox' => [
                    'total_receipts',
                ],
            ])
            ->assertJson([
                'hub_id' => 'relay-hub-01',
                'queue' => [
                    'queued' => 1,
                    'total_messages' => 1,
                    'total_deliveries' => 1,
                ],
                'inbox' => [
                    'total_receipts' => 1,
                ],
            ]);
    }
}
