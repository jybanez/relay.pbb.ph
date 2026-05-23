<?php

namespace Tests\Feature\Relay\Api;

use App\Relay\Auth\RelayHubSignature;
use App\Models\HubRelayDelivery;
use App\Models\HubRegistryHub;
use App\Models\HubRelayReceipt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InboundReceiveTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'relay.hubs' => [
                'city-hub' => ['token' => 'shared-city-key'],
                'barangay-hub' => ['token' => 'shared-barangay-key'],
            ],
            'relay.hq_registry.local_hq_id' => 10,
        ]);

        $this->createRelayClient([
            'system_code' => 'city-eoc.app',
            'api_key' => 'city-eoc-client-key',
        ]);
    }

    private function hubHeaders(string $token = 'shared-city-key', ?string $hubId = null): array
    {
        $headers = [
            'X-Relay-Hub-Key' => $token,
        ];

        if ($hubId !== null) {
            $headers['X-Relay-Hub-Id'] = $hubId;
        }

        return $headers;
    }

    private function mtlsHeaders(
        string $fingerprint = 'ab12cd34ef56',
        string $token = 'shared-city-key',
        ?string $hubId = null,
        bool $includeToken = false,
    ): array {
        $headers = [
            'X-Relay-Client-Cert-Fingerprint' => $fingerprint,
        ];

        if ($includeToken) {
            $headers['X-Relay-Hub-Key'] = $token;
        }

        if ($hubId !== null) {
            $headers['X-Relay-Hub-Id'] = $hubId;
        }

        return $headers;
    }

    public function test_receive_message_creates_receipt(): void
    {
        $payload = [
            'relay_id' => '01ARZ3NDEKTSV4RRFFQ69G5FAV',
            'origin_hq_hub_id' => '6',
            'source_hub_id' => 'city-hub',
            'source_system' => 'sitrep.app',
            'target_hq_hub_id' => 10,
            'target_systems' => ['city-eoc.app'],
            'message_type' => 'sitrep.record',
            'payload' => [
                'incident_id' => 999,
                'description' => 'Received test message',
            ],
            'occurred_at' => now()->toIso8601String(),
        ];

        $response = $this->postJson('/api/v1/receive', $payload, $this->hubHeaders());

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'status' => 'received',
                'relay_id' => '01ARZ3NDEKTSV4RRFFQ69G5FAV',
            ]);

        // Verify receipt was created
        $receipt = HubRelayReceipt::where('relay_id', '01ARZ3NDEKTSV4RRFFQ69G5FAV')->first();
        $this->assertNotNull($receipt);
        $this->assertEquals('city-hub', $receipt->source_hub_id);
        $this->assertEquals('sitrep.record', $receipt->message_type);
    }

    public function test_duplicate_message_returns_received_status(): void
    {
        $relayId = '01ARZ3NDEKTSV4RRFFQ69G5FAV';

        // Create initial receipt
        HubRelayReceipt::create([
            'relay_id' => $relayId,
            'source_hub_id' => 'city-hub',
            'message_type' => 'sitrep.record',
            'status' => 'received',
            'received_at' => now(),
        ]);

        // Try to receive same message again
        $payload = [
            'relay_id' => $relayId,
            'origin_hq_hub_id' => '6',
            'source_hub_id' => 'city-hub',
            'source_system' => 'sitrep.app',
            'target_hq_hub_id' => 10,
            'target_systems' => ['city-eoc.app'],
            'message_type' => 'sitrep.record',
            'payload' => [
                'incident_id' => 999,
            ],
        ];

        $response = $this->postJson('/api/v1/receive', $payload, $this->hubHeaders());

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'status' => 'duplicate',
            ]);

        // Verify only one receipt exists
        $this->assertEquals(1, HubRelayReceipt::where('relay_id', $relayId)->count());
    }

    public function test_batch_receive_processes_multiple_messages(): void
    {
        $payload = [
            'messages' => [
                [
                    'relay_id' => '01ARZ3NDEKTSV4RRFFQ69G5FAV',
                    'origin_hq_hub_id' => '6',
                    'source_hub_id' => 'city-hub',
                    'source_system' => 'sitrep.app',
                    'target_hq_hub_id' => 10,
                    'target_systems' => ['city-eoc.app'],
                    'message_type' => 'sitrep.record',
                    'payload' => ['id' => 1],
                ],
                [
                    'relay_id' => '01ARZ3NDEKTSV4RRFFQ69G5FBW',
                    'origin_hq_hub_id' => '6',
                    'source_hub_id' => 'city-hub',
                    'source_system' => 'sitrep.app',
                    'target_hq_hub_id' => 10,
                    'target_systems' => ['city-eoc.app'],
                    'message_type' => 'sitrep.record',
                    'payload' => ['id' => 2],
                ],
            ],
        ];

        $response = $this->postJson('/api/v1/receive-batch', $payload, $this->hubHeaders());

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'received_count' => 2,
            ])
            ->assertJsonStructure([
                'results' => [
                    '*' => ['relay_id', 'success', 'status'],
                ],
            ]);

        // Verify receipts were created
        $this->assertEquals(2, HubRelayReceipt::count());
    }

    public function test_receive_missing_required_fields_fails(): void
    {
        $payload = [
            'relay_id' => '01ARZ3NDEKTSV4RRFFQ69G5FAV',
            'source_hub_id' => 'city-hub',
            // Missing required fields
        ];

        $response = $this->postJson('/api/v1/receive', $payload, $this->hubHeaders());

        $response->assertStatus(422);
    }

    public function test_receive_rejects_message_that_does_not_target_local_hq_hub(): void
    {
        $response = $this->postJson('/api/v1/receive', [
            'relay_id' => '01ARZ3NDEKTSV4RRFFQ69G5FA1',
            'origin_hq_hub_id' => '6',
            'source_hub_id' => 'city-hub',
            'source_system' => 'sitrep.app',
            'target_hq_hub_id' => 456,
            'target_systems' => ['rafi-foundation.app'],
            'message_type' => 'sitrep.record',
            'payload' => ['incident_id' => 1],
        ], $this->hubHeaders());

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'error' => 'Message does not target this relay.',
            ]);
    }

    public function test_receive_accepts_unknown_local_target_system_but_marks_receipt_undeliverable(): void
    {
        config([
            'relay.targets' => [],
        ]);

        $response = $this->postJson('/api/v1/receive', [
            'relay_id' => '01ARZ3NDEKTSV4RRFFQ69G5FA2',
            'origin_hq_hub_id' => '6',
            'source_hub_id' => 'city-hub',
            'source_system' => 'sitrep.app',
            'target_hq_hub_id' => 10,
            'target_systems' => ['unknown-local.app'],
            'message_type' => 'sitrep.record',
            'payload' => ['incident_id' => 2],
        ], $this->hubHeaders());

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'status' => HubRelayReceipt::STATUS_UNDELIVERABLE,
                'relay_id' => '01ARZ3NDEKTSV4RRFFQ69G5FA2',
            ]);

        $receipt = HubRelayReceipt::query()->where('relay_id', '01ARZ3NDEKTSV4RRFFQ69G5FA2')->first();

        $this->assertNotNull($receipt);
        $this->assertSame(HubRelayReceipt::STATUS_UNDELIVERABLE, $receipt->status);
        $this->assertStringContainsString('No registered local client for target systems', (string) $receipt->processing_notes);
    }

    public function test_receive_rejects_looped_hop_when_local_hub_already_exists_in_trace(): void
    {
        $response = $this->postJson('/api/v1/receive', [
            'relay_id' => '01ARZ3NDEKTSV4RRFFQ69G5FA3',
            'origin_hq_hub_id' => '6',
            'source_hub_id' => 'city-hub',
            'source_system' => 'sitrep.app',
            'target_hq_hub_id' => 10,
            'target_systems' => ['city-eoc.app'],
            'hop_trace' => [
                [
                    'hub_id' => '10',
                    'event' => 'received',
                    'at' => now()->subSecond()->toIso8601String(),
                ],
            ],
            'message_type' => 'sitrep.record',
            'payload' => ['incident_id' => 3],
        ], $this->hubHeaders());

        $response->assertStatus(409)
            ->assertJson([
                'success' => false,
                'status' => HubRelayReceipt::STATUS_REJECTED,
                'relay_id' => '01ARZ3NDEKTSV4RRFFQ69G5FA3',
            ]);

        $receipt = HubRelayReceipt::query()->where('relay_id', '01ARZ3NDEKTSV4RRFFQ69G5FA3')->first();

        $this->assertNotNull($receipt);
        $this->assertSame(HubRelayReceipt::STATUS_REJECTED, $receipt->status);
        $this->assertStringContainsString('Loop detected', (string) $receipt->processing_notes);
    }

    public function test_receive_queues_forwarding_delivery_for_unvisited_adjacent_peer(): void
    {
        config([
            'relay.targets' => [
                '11' => ['base_url' => 'https://cebu-cebu-relay.pbb.ph'],
            ],
        ]);

        $response = $this->postJson('/api/v1/receive', [
            'relay_id' => '01ARZ3NDEKTSV4RRFFQ69G5FA4',
            'origin_hq_hub_id' => '6',
            'source_hub_id' => 'city-hub',
            'source_system' => 'sitrep.app',
            'target_hq_hub_id' => 10,
            'target_systems' => ['city-eoc.app', 'provincial-eoc.app'],
            'hop_trace' => [
                [
                    'hub_id' => '6',
                    'event' => 'submitted',
                    'at' => now()->subSeconds(5)->toIso8601String(),
                ],
            ],
            'message_type' => 'sitrep.record',
            'payload' => ['incident_id' => 4],
        ], $this->hubHeaders());

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'status' => HubRelayReceipt::STATUS_RECEIVED,
                'relay_id' => '01ARZ3NDEKTSV4RRFFQ69G5FA4',
            ]);

        $delivery = HubRelayDelivery::query()->where('hub_relay_message_id', function ($query) {
            $query->select('id')
                ->from('hub_relay_messages')
                ->where('relay_id', '01ARZ3NDEKTSV4RRFFQ69G5FA4')
                ->limit(1);
        })->first();

        $this->assertNotNull($delivery);
        $this->assertSame('11', (string) ($delivery->target_hq_hub_id ?: $delivery->target_hub_id));
        $this->assertNull($delivery->target_system);
    }

    public function test_receive_can_process_local_delivery_and_forwarding_in_same_hop(): void
    {
        config([
            'relay.targets' => [
                '11' => ['base_url' => 'https://cebu-cebu-relay.pbb.ph'],
            ],
        ]);

        $response = $this->postJson('/api/v1/receive', [
            'relay_id' => '01ARZ3NDEKTSV4RRFFQ69G5FA5',
            'origin_hq_hub_id' => '6',
            'source_hub_id' => 'city-hub',
            'source_system' => 'sitrep.app',
            'target_hq_hub_id' => 10,
            'target_systems' => ['city-eoc.app', 'provincial-forwarder.app'],
            'hop_trace' => [
                [
                    'hub_id' => '6',
                    'event' => 'submitted',
                    'at' => now()->subSeconds(5)->toIso8601String(),
                ],
            ],
            'message_type' => 'sitrep.record',
            'payload' => ['incident_id' => 5],
        ], $this->hubHeaders());

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'status' => HubRelayReceipt::STATUS_RECEIVED,
                'relay_id' => '01ARZ3NDEKTSV4RRFFQ69G5FA5',
            ]);

        $receipt = HubRelayReceipt::query()->where('relay_id', '01ARZ3NDEKTSV4RRFFQ69G5FA5')->first();
        $messageId = \App\Models\HubRelayMessage::query()
            ->where('relay_id', '01ARZ3NDEKTSV4RRFFQ69G5FA5')
            ->value('id');

        $this->assertNotNull($receipt);
        $this->assertNotNull($messageId);
        $this->assertStringContainsString('Forwarded to 1 next-hop relay(s)', (string) $receipt->processing_notes);
        $this->assertGreaterThan(0, HubRelayDelivery::query()->where('hub_relay_message_id', $messageId)->count());
    }

    public function test_receive_requires_hub_key(): void
    {
        $response = $this->postJson('/api/v1/receive', [
            'relay_id' => '01ARZ3NDEKTSV4RRFFQ69G5FAV',
            'origin_hq_hub_id' => '6',
            'source_hub_id' => 'city-hub',
            'source_system' => 'sitrep.app',
            'target_hq_hub_id' => 10,
            'target_systems' => ['city-eoc.app'],
            'message_type' => 'sitrep.record',
            'payload' => ['incident_id' => 1],
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'error' => 'Missing X-Relay-Hub-Key header',
            ]);
    }

    public function test_receive_rejects_invalid_hub_key(): void
    {
        $response = $this->postJson('/api/v1/receive', [
            'relay_id' => '01ARZ3NDEKTSV4RRFFQ69G5FAV',
            'origin_hq_hub_id' => '6',
            'source_hub_id' => 'city-hub',
            'source_system' => 'sitrep.app',
            'target_hq_hub_id' => 10,
            'target_systems' => ['city-eoc.app'],
            'message_type' => 'sitrep.record',
            'payload' => ['incident_id' => 1],
        ], $this->hubHeaders('wrong-key'));

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'error' => 'Invalid relay hub credentials',
            ]);
    }

    public function test_receive_batch_rejects_mixed_source_hubs(): void
    {
        $response = $this->postJson('/api/v1/receive-batch', [
            'messages' => [
                [
                    'relay_id' => '01ARZ3NDEKTSV4RRFFQ69G5FAV',
                    'origin_hq_hub_id' => '6',
                    'source_hub_id' => 'city-hub',
                    'source_system' => 'sitrep.app',
                    'target_hq_hub_id' => 10,
                    'target_systems' => ['city-eoc.app'],
                    'message_type' => 'sitrep.record',
                    'payload' => ['id' => 1],
                ],
                [
                    'relay_id' => '01ARZ3NDEKTSV4RRFFQ69G5FBW',
                    'origin_hq_hub_id' => '6',
                    'source_hub_id' => 'barangay-hub',
                    'source_system' => 'sitrep.app',
                    'target_hq_hub_id' => 10,
                    'target_systems' => ['city-eoc.app'],
                    'message_type' => 'sitrep.record',
                    'payload' => ['id' => 2],
                ],
            ],
        ], $this->hubHeaders());

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'error' => 'Missing source hub identity for relay request',
            ]);
    }

    public function test_receive_accepts_valid_hmac_signature(): void
    {
        config([
            'relay.hub_auth.mode' => 'hmac',
        ]);

        $payload = [
            'relay_id' => '01ARZ3NDEKTSV4RRFFQ69G5FAV',
            'origin_hq_hub_id' => '6',
            'source_hub_id' => 'city-hub',
            'source_system' => 'sitrep.app',
            'target_hq_hub_id' => 10,
            'target_systems' => ['city-eoc.app'],
            'message_type' => 'sitrep.record',
            'payload' => ['incident_id' => 999],
        ];

        $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $timestamp = now()->toIso8601String();
        $signature = app(RelayHubSignature::class)->sign($timestamp, $body, 'shared-city-key');

        $response = $this->call(
            'POST',
            '/api/v1/receive',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_RELAY_TIMESTAMP' => $timestamp,
                'HTTP_X_RELAY_SIGNATURE' => $signature,
                'HTTP_X_RELAY_HUB_KEY' => 'shared-city-key',
            ],
            $body
        );

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'status' => 'received',
            ]);
    }

    public function test_receive_rejects_invalid_hmac_signature(): void
    {
        config([
            'relay.hub_auth.mode' => 'hmac',
        ]);

        $payload = [
            'relay_id' => '01ARZ3NDEKTSV4RRFFQ69G5FAV',
            'origin_hq_hub_id' => '6',
            'source_hub_id' => 'city-hub',
            'source_system' => 'sitrep.app',
            'target_hq_hub_id' => 10,
            'target_systems' => ['city-eoc.app'],
            'message_type' => 'sitrep.record',
            'payload' => ['incident_id' => 999],
        ];

        $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        $response = $this->call(
            'POST',
            '/api/v1/receive',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_RELAY_TIMESTAMP' => now()->toIso8601String(),
                'HTTP_X_RELAY_SIGNATURE' => 'bad-signature',
                'HTTP_X_RELAY_HUB_KEY' => 'shared-city-key',
            ],
            $body
        );

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'error' => 'Invalid relay hub signature',
            ]);
    }

    public function test_receive_accepts_valid_client_certificate_fingerprint_in_mtls_mode(): void
    {
        config([
            'relay.hub_auth.mode' => 'mtls',
            'relay.hubs.city-hub.tls_client_certificate_fingerprint' => 'AB:12:CD:34:EF:56',
        ]);

        $response = $this->postJson('/api/v1/receive', [
            'relay_id' => '01ARZ3NDEKTSV4RRFFQ69G5FAX',
            'origin_hq_hub_id' => '6',
            'source_hub_id' => 'city-hub',
            'source_system' => 'sitrep.app',
            'target_hq_hub_id' => 10,
            'target_systems' => ['city-eoc.app'],
            'message_type' => 'sitrep.record',
            'payload' => ['incident_id' => 1],
        ], $this->mtlsHeaders());

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'status' => 'received',
            ]);
    }

    public function test_receive_rejects_invalid_client_certificate_fingerprint_in_mtls_mode(): void
    {
        config([
            'relay.hub_auth.mode' => 'mtls',
            'relay.hubs.city-hub.tls_client_certificate_fingerprint' => 'ab12cd34ef56',
        ]);

        $response = $this->postJson('/api/v1/receive', [
            'relay_id' => '01ARZ3NDEKTSV4RRFFQ69G5FAY',
            'origin_hq_hub_id' => '6',
            'source_hub_id' => 'city-hub',
            'source_system' => 'sitrep.app',
            'target_hq_hub_id' => 10,
            'target_systems' => ['city-eoc.app'],
            'message_type' => 'sitrep.record',
            'payload' => ['incident_id' => 1],
        ], $this->mtlsHeaders('wrongfingerprint'));

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'error' => 'Invalid relay hub client certificate',
            ]);
    }

    public function test_receive_requires_both_certificate_and_hmac_in_mtls_hmac_mode(): void
    {
        config([
            'relay.hub_auth.mode' => 'mtls_hmac',
            'relay.hubs.city-hub.tls_client_certificate_fingerprint' => 'ab12cd34ef56',
        ]);

        $payload = [
            'relay_id' => '01ARZ3NDEKTSV4RRFFQ69G5FAZ',
            'origin_hq_hub_id' => '6',
            'source_hub_id' => 'city-hub',
            'source_system' => 'sitrep.app',
            'target_hq_hub_id' => 10,
            'target_systems' => ['city-eoc.app'],
            'message_type' => 'sitrep.record',
            'payload' => ['incident_id' => 2],
        ];

        $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $timestamp = now()->toIso8601String();
        $signature = app(RelayHubSignature::class)->sign($timestamp, $body, 'shared-city-key');

        $response = $this->call(
            'POST',
            '/api/v1/receive',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_RELAY_CLIENT_CERT_FINGERPRINT' => 'ab12cd34ef56',
                'HTTP_X_RELAY_HUB_KEY' => 'shared-city-key',
                'HTTP_X_RELAY_TIMESTAMP' => $timestamp,
                'HTTP_X_RELAY_SIGNATURE' => $signature,
            ],
            $body
        );

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'status' => 'received',
            ]);
    }

    public function test_receive_accepts_hq_registry_known_hub_with_local_credentials(): void
    {
        config([
            'relay.hubs' => [],
            'relay.hq_registry.inbound_trust_mode' => 'known_hq_hubs',
            'relay.hub_credentials' => [
                '6' => ['token' => 'shared-city-key'],
            ],
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

        $payload = [
            'relay_id' => '01ARZ3NDEKTSV4RRFFQ69G5FCZ',
            'origin_hq_hub_id' => '6',
            'source_hub_id' => 'city-hub-01',
            'source_system' => 'sitrep.app',
            'target_hq_hub_id' => 10,
            'target_systems' => ['city-eoc.app'],
            'message_type' => 'sitrep.record',
            'payload' => ['incident_id' => 3],
        ];

        $response = $this->postJson('/api/v1/receive', $payload, [
            'X-Relay-Hub-Key' => 'shared-city-key',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'status' => 'received',
            ]);
    }
}
