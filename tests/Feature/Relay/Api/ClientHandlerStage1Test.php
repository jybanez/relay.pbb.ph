<?php

namespace Tests\Feature\Relay\Api;

use App\Jobs\DispatchRelayToLocalHandler;
use App\Models\HubRelayHandler;
use App\Models\HubRelayHandlerDispatch;
use App\Models\HubRelayMessage;
use App\Models\HubRelayReceipt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ClientHandlerStage1Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedHubSnapshot(10, 'relay-hub-10');

        config([
            'relay.hubs' => [
                'city-hub' => ['token' => 'shared-city-key'],
            ],
        ]);
    }

    public function test_inactive_client_key_is_rejected_by_local_application_routes(): void
    {
        $inactiveClient = $this->createRelayClient([
            'api_key' => 'inactive-relay-key',
            'is_active' => false,
        ]);

        $messagePayload = [
            'source_hub_id' => 'barangay-hub',
            'source_system' => 'sitrep.app',
            'targets' => [
                ['id' => 10, 'systems' => ['client.a']],
            ],
            'message_type' => 'sitrep.record',
            'payload' => ['incident_id' => 1],
        ];

        $this->postJson('/api/v1/messages', $messagePayload, $this->relayHeaders($inactiveClient->api_key))
            ->assertStatus(401)
            ->assertJsonPath('error', 'Invalid relay client credentials');

        $this->getJson('/api/v1/handlers', $this->relayHeaders($inactiveClient->api_key))
            ->assertStatus(401)
            ->assertJsonPath('error', 'Invalid relay client credentials');

        $this->getJson('/api/v1/handler-dispatches', $this->relayHeaders($inactiveClient->api_key))
            ->assertStatus(401)
            ->assertJsonPath('error', 'Invalid relay client credentials');
    }

    public function test_invalid_client_key_is_rejected_by_local_application_routes(): void
    {
        $messagePayload = [
            'source_hub_id' => 'barangay-hub',
            'source_system' => 'sitrep.app',
            'targets' => [
                ['id' => 10, 'systems' => ['client.a']],
            ],
            'message_type' => 'sitrep.record',
            'payload' => ['incident_id' => 1],
        ];

        $headers = $this->relayHeaders('invalid-relay-key');

        $this->postJson('/api/v1/messages', $messagePayload, $headers)
            ->assertStatus(401)
            ->assertJsonPath('error', 'Invalid relay client credentials');

        $this->getJson('/api/v1/handlers', $headers)
            ->assertStatus(401)
            ->assertJsonPath('error', 'Invalid relay client credentials');

        $this->getJson('/api/v1/handler-dispatches', $headers)
            ->assertStatus(401)
            ->assertJsonPath('error', 'Invalid relay client credentials');
    }

    public function test_client_cannot_mutate_another_clients_handler(): void
    {
        $clientA = $this->createRelayClient([
            'name' => 'Client A',
            'system_code' => 'client.a',
            'api_key' => 'relay-key-a',
        ]);

        $clientB = $this->createRelayClient([
            'name' => 'Client B',
            'system_code' => 'client.b',
            'api_key' => 'relay-key-b',
        ]);

        $handler = HubRelayHandler::query()->create([
            'hub_relay_client_id' => $clientA->id,
            'name' => 'Client A Handler',
            'endpoint_url' => 'https://client-a.test/hooks/relay',
            'message_type_pattern' => 'sitrep.*',
            'is_active' => true,
        ]);

        $this->patchJson('/api/v1/handlers/'.$handler->id, [
            'message_type_pattern' => 'incident.*',
        ], $this->relayHeaders($clientB->api_key))
            ->assertNotFound();

        $this->deleteJson('/api/v1/handlers/'.$handler->id, [], $this->relayHeaders($clientB->api_key))
            ->assertNotFound();

        $handler->refresh();

        $this->assertSame('sitrep.*', $handler->message_type_pattern);
        $this->assertTrue($handler->is_active);
    }

    public function test_client_cannot_access_another_clients_handler_dispatches(): void
    {
        $clientA = $this->createRelayClient([
            'name' => 'Client A',
            'system_code' => 'client.a',
            'api_key' => 'relay-key-a',
        ]);

        $clientB = $this->createRelayClient([
            'name' => 'Client B',
            'system_code' => 'client.b',
            'api_key' => 'relay-key-b',
        ]);

        $handlerA = HubRelayHandler::query()->create([
            'hub_relay_client_id' => $clientA->id,
            'name' => 'Client A Handler',
            'endpoint_url' => 'https://client-a.test/hooks/relay',
            'message_type_pattern' => 'sitrep.*',
            'is_active' => true,
        ]);

        $handlerB = HubRelayHandler::query()->create([
            'hub_relay_client_id' => $clientB->id,
            'name' => 'Client B Handler',
            'endpoint_url' => 'https://client-b.test/hooks/relay',
            'message_type_pattern' => 'incident.*',
            'is_active' => true,
        ]);

        $messageA = HubRelayMessage::factory()->create([
            'message_type' => 'sitrep.record',
        ]);

        $messageB = HubRelayMessage::factory()->create([
            'message_type' => 'incident.record',
        ]);

        $receiptA = HubRelayReceipt::query()->create([
            'relay_id' => $messageA->relay_id,
            'source_hub_id' => 'city-hub',
            'message_type' => $messageA->message_type,
            'status' => HubRelayReceipt::STATUS_PROCESSED,
            'received_at' => now(),
            'processed_at' => now(),
        ]);

        $receiptB = HubRelayReceipt::query()->create([
            'relay_id' => $messageB->relay_id,
            'source_hub_id' => 'city-hub',
            'message_type' => $messageB->message_type,
            'status' => HubRelayReceipt::STATUS_PROCESSED,
            'received_at' => now(),
            'processed_at' => now(),
        ]);

        $dispatchA = HubRelayHandlerDispatch::query()->create([
            'hub_relay_handler_id' => $handlerA->id,
            'hub_relay_message_id' => $messageA->id,
            'hub_relay_receipt_id' => $receiptA->id,
            'status' => HubRelayHandlerDispatch::STATUS_FAILED,
            'attempt_count' => 1,
            'failed_at' => now(),
        ]);

        $dispatchB = HubRelayHandlerDispatch::query()->create([
            'hub_relay_handler_id' => $handlerB->id,
            'hub_relay_message_id' => $messageB->id,
            'hub_relay_receipt_id' => $receiptB->id,
            'status' => HubRelayHandlerDispatch::STATUS_FAILED,
            'attempt_count' => 1,
            'failed_at' => now(),
        ]);

        $this->getJson('/api/v1/handler-dispatches', $this->relayHeaders($clientA->api_key))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $dispatchA->id);

        $this->getJson('/api/v1/handler-dispatches/'.$dispatchB->id, $this->relayHeaders($clientA->api_key))
            ->assertNotFound();

        $this->postJson('/api/v1/handler-dispatches/'.$dispatchB->id.'/retry', [], $this->relayHeaders($clientA->api_key))
            ->assertNotFound();
    }

    public function test_inbound_receive_only_queues_matching_active_handlers_for_target_system(): void
    {
        Queue::fake();

        $clientA = $this->createRelayClient([
            'name' => 'Client A',
            'system_code' => 'client.a',
            'api_key' => 'relay-key-a',
        ]);

        $clientB = $this->createRelayClient([
            'name' => 'Client B',
            'system_code' => 'client.b',
            'api_key' => 'relay-key-b',
        ]);

        HubRelayHandler::query()->create([
            'hub_relay_client_id' => $clientA->id,
            'name' => 'Client A Matching Handler',
            'endpoint_url' => 'https://client-a.test/hooks/relay',
            'message_type_pattern' => 'sitrep.*',
            'source_system' => 'sitrep.app',
            'source_hub_id' => 'city-hub',
            'is_active' => true,
        ]);

        HubRelayHandler::query()->create([
            'hub_relay_client_id' => $clientB->id,
            'name' => 'Client B Matching Handler',
            'endpoint_url' => 'https://client-b.test/hooks/relay',
            'message_type_pattern' => 'sitrep.*',
            'source_system' => 'sitrep.app',
            'source_hub_id' => 'city-hub',
            'is_active' => true,
        ]);

        HubRelayHandler::query()->create([
            'hub_relay_client_id' => $clientA->id,
            'name' => 'Inactive Match',
            'endpoint_url' => 'https://client-a.test/hooks/inactive',
            'message_type_pattern' => 'sitrep.*',
            'source_system' => 'sitrep.app',
            'source_hub_id' => 'city-hub',
            'is_active' => false,
        ]);

        HubRelayHandler::query()->create([
            'hub_relay_client_id' => $clientA->id,
            'name' => 'Wrong Source System',
            'endpoint_url' => 'https://client-a.test/hooks/source-system',
            'message_type_pattern' => 'sitrep.*',
            'source_system' => 'case-mgmt.app',
            'source_hub_id' => 'city-hub',
            'is_active' => true,
        ]);

        HubRelayHandler::query()->create([
            'hub_relay_client_id' => $clientB->id,
            'name' => 'Wrong Source Hub',
            'endpoint_url' => 'https://client-b.test/hooks/source-hub',
            'message_type_pattern' => 'sitrep.*',
            'source_system' => 'sitrep.app',
            'source_hub_id' => 'province-hub',
            'is_active' => true,
        ]);

        HubRelayHandler::query()->create([
            'hub_relay_client_id' => $clientA->id,
            'name' => 'Wrong Message Type Pattern',
            'endpoint_url' => 'https://client-a.test/hooks/message-type',
            'message_type_pattern' => 'case.*',
            'source_system' => 'sitrep.app',
            'source_hub_id' => 'city-hub',
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/v1/receive', [
            'relay_id' => '01ARZ3NDEKTSV4RRFFQ69G5FAV',
            'origin_hq_hub_id' => '6',
            'source_hub_id' => 'city-hub',
            'source_system' => 'sitrep.app',
            'target_hq_hub_id' => 10,
            'targets' => [['id' => '10', 'systems' => ['client.a']]],
            'message_type' => 'sitrep.record',
            'payload' => ['incident_id' => 100],
        ], [
            'X-Relay-Hub-Key' => 'shared-city-key',
        ]);

        $response->assertCreated()
            ->assertJsonPath('status', 'received');

        Queue::assertPushed(DispatchRelayToLocalHandler::class, 1);
        $this->assertDatabaseCount('hub_relay_handler_dispatches', 1);

        $dispatchHandlerNames = HubRelayHandlerDispatch::query()
            ->with('handler:id,name')
            ->get()
            ->pluck('handler.name')
            ->sort()
            ->values()
            ->all();

        $this->assertSame([
            'Client A Matching Handler',
        ], $dispatchHandlerNames);
    }
}
