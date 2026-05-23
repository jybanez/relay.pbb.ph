<?php

namespace Tests\Feature\Relay\Api;

use App\Jobs\ProcessRelayDelivery;
use App\Models\HubRelayDelivery;
use App\Models\HubRelayMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class MessageSubmissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_submit_message_creates_message_and_deliveries(): void
    {
        $client = $this->createRelayClient();
        Queue::fake();
        config([
            'relay.hq_registry.local_hq_id' => 10,
            'relay.targets' => [
                '11' => ['base_url' => 'https://relay-b.test'],
                '456' => ['base_url' => 'https://relay-c.test'],
            ],
        ]);

        $payload = [
            'source_system' => 'sitrep.app',
            'target_systems' => [
                'city-eoc.app',
                'provincial-forwarder.app',
                'rafi-foundation.app',
            ],
            'message_type' => 'sitrep.record',
            'payload' => [
                'incident_id' => 123,
                'description' => 'Test incident',
                'severity' => 'high',
            ],
            'priority' => 'high',
        ];

        $response = $this->postJson('/api/v1/messages', $payload, $this->relayHeaders());

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'relay_id',
                'message_id',
                'status',
                'deliveries_count',
                'deliveries',
            ]);

        $this->assertTrue($response->json('success'));
        $this->assertEquals('queued', $response->json('status'));
        $this->assertEquals(2, $response->json('deliveries_count'));

        // Verify database state
        $message = HubRelayMessage::where('relay_id', $response->json('relay_id'))->first();
        $this->assertNotNull($message);
        $this->assertSame($client->id, $message->hub_relay_client_id);
        $this->assertEquals('10', $message->source_hub_id);
        $this->assertEquals('10', $message->origin_hq_hub_id);
        $this->assertEquals('sitrep.app', $message->source_system);
        $this->assertSame(['11', '456'], $message->target_hub_ids);
        $this->assertSame(
            ['city-eoc.app', 'provincial-forwarder.app', 'rafi-foundation.app'],
            $message->target_systems
        );
        $this->assertEquals('sitrep.record', $message->message_type);
        $this->assertEquals(2, $message->deliveries()->count());

        Queue::assertPushed(ProcessRelayDelivery::class, 2);
    }

    public function test_submit_message_requires_required_fields(): void
    {
        $this->createRelayClient();

        $response = $this->postJson('/api/v1/messages', [
            'target_systems' => ['sitrep.app'],
            // Missing other required fields
        ], $this->relayHeaders());

        $response->assertStatus(422);
    }

    public function test_get_messages_returns_paginated_list(): void
    {
        $client = $this->createRelayClient([
            'api_key' => 'client-a-key',
        ]);
        $otherClient = $this->createRelayClient([
            'system_code' => 'other.app',
            'api_key' => 'client-b-key',
        ]);

        HubRelayMessage::factory(2)->create([
            'hub_relay_client_id' => $client->id,
        ]);

        HubRelayMessage::factory()->create([
            'hub_relay_client_id' => $otherClient->id,
        ]);

        $response = $this->getJson('/api/v1/messages', $this->relayHeaders($client->api_key));

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data',
                'pagination' => [
                    'total',
                    'per_page',
                    'current_page',
                    'last_page',
                ],
            ])
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('pagination.total', 2);
    }

    public function test_get_message_detail(): void
    {
        $client = $this->createRelayClient([
            'api_key' => 'client-a-key',
        ]);
        $otherClient = $this->createRelayClient([
            'system_code' => 'other.app',
            'api_key' => 'client-b-key',
        ]);

        $message = HubRelayMessage::factory()->create([
            'hub_relay_client_id' => $client->id,
        ]);

        $otherMessage = HubRelayMessage::factory()->create([
            'hub_relay_client_id' => $otherClient->id,
        ]);

        $response = $this->getJson("/api/v1/messages/{$message->id}", $this->relayHeaders($client->api_key));

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'deliveries',
                'attachments',
            ]);

        $this->getJson("/api/v1/messages/{$otherMessage->id}", $this->relayHeaders($client->api_key))
            ->assertNotFound();
    }

    public function test_client_can_only_list_and_access_own_deliveries(): void
    {
        $client = $this->createRelayClient([
            'api_key' => 'client-a-key',
        ]);
        $otherClient = $this->createRelayClient([
            'system_code' => 'other.app',
            'api_key' => 'client-b-key',
        ]);

        $message = HubRelayMessage::factory()->create([
            'hub_relay_client_id' => $client->id,
        ]);
        $otherMessage = HubRelayMessage::factory()->create([
            'hub_relay_client_id' => $otherClient->id,
        ]);

        $delivery = HubRelayDelivery::create([
            'hub_relay_message_id' => $message->id,
            'target_hub_id' => 'city-hub',
            'status' => HubRelayDelivery::STATUS_FAILED,
        ]);

        $otherDelivery = HubRelayDelivery::create([
            'hub_relay_message_id' => $otherMessage->id,
            'target_hub_id' => 'province-hub',
            'status' => HubRelayDelivery::STATUS_FAILED,
        ]);

        $this->getJson('/api/v1/deliveries', $this->relayHeaders($client->api_key))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $delivery->id);

        $this->getJson('/api/v1/deliveries/'.$delivery->id, $this->relayHeaders($client->api_key))
            ->assertOk()
            ->assertJsonPath('delivery.id', $delivery->id);

        $this->getJson('/api/v1/deliveries/'.$otherDelivery->id, $this->relayHeaders($client->api_key))
            ->assertNotFound();

        $this->postJson('/api/v1/deliveries/'.$otherDelivery->id.'/retry', [], $this->relayHeaders($client->api_key))
            ->assertNotFound();

        $this->postJson('/api/v1/deliveries/'.$otherDelivery->id.'/cancel', [], $this->relayHeaders($client->api_key))
            ->assertNotFound();
    }

    public function test_local_api_requires_relay_key(): void
    {
        $response = $this->postJson('/api/v1/messages', [
            'source_system' => 'sitrep.app',
            'target_systems' => ['sitrep.app'],
            'message_type' => 'sitrep.record',
            'payload' => ['incident_id' => 123],
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'error' => 'Missing X-Relay-Key header',
            ]);
    }
}
