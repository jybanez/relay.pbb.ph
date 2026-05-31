<?php

namespace Tests\Feature\Relay\Api;

use App\Jobs\DispatchRelayToLocalHandler;
use App\Models\HubRelayHandler;
use App\Models\HubRelayHandlerDispatch;
use App\Models\HubRelayMessage;
use App\Models\HubRelayReceipt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class InboxAndHandlerDispatchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedHubSnapshot(10, 'relay-hub-10');

        config([
            'relay.hubs' => [
                'city-hub' => ['token' => 'shared-city-key'],
                'province-hub' => ['token' => 'shared-city-key'],
                'orphan-hub' => ['token' => 'shared-city-key'],
            ],
        ]);
    }

    public function test_inbound_receive_queues_matching_local_handlers_and_marks_receipt_processed(): void
    {
        Queue::fake();

        $client = $this->createRelayClient();
        HubRelayHandler::query()->create([
            'hub_relay_client_id' => $client->id,
            'name' => 'SITREP Receiver',
            'endpoint_url' => 'https://local.app.test/hooks/relay',
            'message_type_pattern' => 'sitrep.*',
            'source_system' => 'sitrep.app',
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/v1/receive', [
            'relay_id' => '01ARZ3NDEKTSV4RRFFQ69G5FAV',
            'origin_hq_hub_id' => '6',
            'source_hub_id' => 'city-hub',
            'source_system' => 'sitrep.app',
            'target_hq_hub_id' => 10,
            'targets' => [['id' => '10', 'systems' => ['test.app']]],
            'message_type' => 'sitrep.record',
            'payload' => ['incident_id' => 100],
        ], [
            'X-Relay-Hub-Key' => 'shared-city-key',
        ]);

        $response->assertCreated()
            ->assertJsonPath('status', 'received');

        $receipt = HubRelayReceipt::query()->where('relay_id', '01ARZ3NDEKTSV4RRFFQ69G5FAV')->firstOrFail();

        $this->assertSame(HubRelayReceipt::STATUS_PROCESSED, $receipt->status);
        $this->assertNotNull($receipt->processed_at);
        $this->assertStringContainsString('Queued for 1 local handler', (string) $receipt->processing_notes);

        Queue::assertPushed(DispatchRelayToLocalHandler::class, 1);
        $this->assertDatabaseCount('hub_relay_handler_dispatches', 1);
    }

    public function test_inbox_lists_received_messages(): void
    {
        $client = $this->createRelayClient([
            'api_key' => 'client-a-key',
        ]);
        $otherClient = $this->createRelayClient([
            'system_code' => 'other.app',
            'api_key' => 'client-b-key',
        ]);

        $matchingHandler = HubRelayHandler::query()->create([
            'hub_relay_client_id' => $client->id,
            'name' => 'Client A Inbox Handler',
            'endpoint_url' => 'https://client-a.test/hooks/relay',
            'message_type_pattern' => 'sitrep.*',
            'source_system' => 'sitrep.app',
            'source_hub_id' => 'city-hub',
            'is_active' => true,
        ]);

        $otherHandler = HubRelayHandler::query()->create([
            'hub_relay_client_id' => $otherClient->id,
            'name' => 'Client B Inbox Handler',
            'endpoint_url' => 'https://client-b.test/hooks/relay',
            'message_type_pattern' => 'case.*',
            'source_system' => 'case-mgmt.app',
            'source_hub_id' => 'province-hub',
            'is_active' => true,
        ]);

        $message = HubRelayMessage::query()->create([
            'relay_id' => '01ARZ3NDEKTSV4RRFFQ69G5FBW',
            'source_hub_id' => 'city-hub',
            'source_system' => 'sitrep.app',
            'targets' => [['id' => '10', 'systems' => [$client->system_code]]],
            'message_type' => 'sitrep.record',
            'payload_format' => 'json',
            'payload_version' => '1.0',
            'content_hash' => 'abc123',
            'payload' => ['incident_id' => 5],
            'priority' => 'normal',
            'attachments_count' => 0,
            'occurred_at' => now(),
        ]);

        HubRelayReceipt::query()->create([
            'relay_id' => $message->relay_id,
            'source_hub_id' => $message->source_hub_id,
            'message_type' => $message->message_type,
            'status' => HubRelayReceipt::STATUS_PROCESSED,
            'content_hash' => $message->content_hash,
            'received_at' => now(),
            'processed_at' => now(),
        ]);

        HubRelayHandlerDispatch::query()->create([
            'hub_relay_handler_id' => $matchingHandler->id,
            'hub_relay_message_id' => $message->id,
            'hub_relay_receipt_id' => $message->receipt()->firstOrFail()->id,
            'status' => HubRelayHandlerDispatch::STATUS_QUEUED,
            'queued_at' => now(),
        ]);

        $otherMessage = HubRelayMessage::query()->create([
            'relay_id' => '01ARZ3NDEKTSV4RRFFQ69G5FBX',
            'source_hub_id' => 'province-hub',
            'source_system' => 'case-mgmt.app',
            'targets' => [['id' => '10', 'systems' => [$otherClient->system_code]]],
            'message_type' => 'case.record',
            'payload_format' => 'json',
            'payload_version' => '1.0',
            'content_hash' => 'def456',
            'payload' => ['case_id' => 9],
            'priority' => 'normal',
            'attachments_count' => 0,
            'occurred_at' => now(),
        ]);

        HubRelayReceipt::query()->create([
            'relay_id' => $otherMessage->relay_id,
            'source_hub_id' => $otherMessage->source_hub_id,
            'message_type' => $otherMessage->message_type,
            'status' => HubRelayReceipt::STATUS_PROCESSED,
            'content_hash' => $otherMessage->content_hash,
            'received_at' => now(),
            'processed_at' => now(),
        ]);

        HubRelayHandlerDispatch::query()->create([
            'hub_relay_handler_id' => $otherHandler->id,
            'hub_relay_message_id' => $otherMessage->id,
            'hub_relay_receipt_id' => $otherMessage->receipt()->firstOrFail()->id,
            'status' => HubRelayHandlerDispatch::STATUS_QUEUED,
            'queued_at' => now(),
        ]);

        $response = $this->getJson('/api/v1/inbox', $this->relayHeaders($client->api_key));

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.relay_id', $message->relay_id)
            ->assertJsonPath('data.0.receipt.status', HubRelayReceipt::STATUS_PROCESSED);

        $this->getJson('/api/v1/inbox/'.$message->id, $this->relayHeaders($client->api_key))
            ->assertOk()
            ->assertJsonPath('message.relay_id', $message->relay_id);

        $this->getJson('/api/v1/inbox/'.$otherMessage->id, $this->relayHeaders($client->api_key))
            ->assertNotFound();
    }

    public function test_inbox_only_shows_messages_visible_to_authenticated_client(): void
    {
        Queue::fake();

        $clientA = $this->createRelayClient([
            'api_key' => 'client-a-key',
        ]);
        $clientB = $this->createRelayClient([
            'system_code' => 'client.b',
            'api_key' => 'client-b-key',
        ]);

        HubRelayHandler::query()->create([
            'hub_relay_client_id' => $clientA->id,
            'name' => 'Client A Handler',
            'endpoint_url' => 'https://client-a.test/hooks/relay',
            'message_type_pattern' => 'sitrep.*',
            'source_system' => 'sitrep.app',
            'source_hub_id' => 'city-hub',
            'is_active' => true,
        ]);

        HubRelayHandler::query()->create([
            'hub_relay_client_id' => $clientB->id,
            'name' => 'Client B Handler',
            'endpoint_url' => 'https://client-b.test/hooks/relay',
            'message_type_pattern' => 'case.*',
            'source_system' => 'case-mgmt.app',
            'source_hub_id' => 'province-hub',
            'is_active' => true,
        ]);

        HubRelayHandler::query()->create([
            'hub_relay_client_id' => $clientA->id,
            'name' => 'Client A Inactive Handler',
            'endpoint_url' => 'https://client-a.test/hooks/inactive',
            'message_type_pattern' => 'orphan.*',
            'source_system' => 'orphan.app',
            'source_hub_id' => 'orphan-hub',
            'is_active' => false,
        ]);

        $this->postJson('/api/v1/receive', [
            'relay_id' => '01ARZ3NDEKTSV4RRFFQ69G5FAA',
            'origin_hq_hub_id' => '6',
            'source_hub_id' => 'city-hub',
            'source_system' => 'sitrep.app',
            'target_hq_hub_id' => 10,
            'targets' => [['id' => '10', 'systems' => ['test.app']]],
            'message_type' => 'sitrep.record',
            'payload' => ['incident_id' => 1],
        ], [
            'X-Relay-Hub-Key' => 'shared-city-key',
        ])->assertCreated();

        $this->postJson('/api/v1/receive', [
            'relay_id' => '01ARZ3NDEKTSV4RRFFQ69G5FAB',
            'origin_hq_hub_id' => '6',
            'source_hub_id' => 'province-hub',
            'source_system' => 'case-mgmt.app',
            'target_hq_hub_id' => 10,
            'targets' => [['id' => '10', 'systems' => ['client.b']]],
            'message_type' => 'case.record',
            'payload' => ['case_id' => 2],
        ], [
            'X-Relay-Hub-Key' => 'shared-city-key',
        ])->assertCreated();

        $this->postJson('/api/v1/receive', [
            'relay_id' => '01ARZ3NDEKTSV4RRFFQ69G5FAC',
            'origin_hq_hub_id' => '6',
            'source_hub_id' => 'orphan-hub',
            'source_system' => 'orphan.app',
            'target_hq_hub_id' => 10,
            'targets' => [['id' => '10', 'systems' => ['orphan.app']]],
            'message_type' => 'orphan.record',
            'payload' => ['orphan_id' => 3],
        ], [
            'X-Relay-Hub-Key' => 'shared-city-key',
        ])->assertCreated();

        $clientAInbox = $this->getJson('/api/v1/inbox', $this->relayHeaders($clientA->api_key));
        $clientBInbox = $this->getJson('/api/v1/inbox', $this->relayHeaders($clientB->api_key));

        $clientAInbox->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.relay_id', '01ARZ3NDEKTSV4RRFFQ69G5FAA');

        $clientBInbox->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.relay_id', '01ARZ3NDEKTSV4RRFFQ69G5FAB');

        $clientAVisible = HubRelayMessage::query()->where('relay_id', '01ARZ3NDEKTSV4RRFFQ69G5FAA')->firstOrFail();
        $clientBVisible = HubRelayMessage::query()->where('relay_id', '01ARZ3NDEKTSV4RRFFQ69G5FAB')->firstOrFail();
        $orphanMessage = HubRelayMessage::query()->where('relay_id', '01ARZ3NDEKTSV4RRFFQ69G5FAC')->firstOrFail();

        $this->getJson('/api/v1/inbox/'.$clientAVisible->id, $this->relayHeaders($clientA->api_key))
            ->assertOk()
            ->assertJsonPath('message.relay_id', '01ARZ3NDEKTSV4RRFFQ69G5FAA');

        $this->getJson('/api/v1/inbox/'.$clientBVisible->id, $this->relayHeaders($clientA->api_key))
            ->assertNotFound();

        $this->getJson('/api/v1/inbox/'.$orphanMessage->id, $this->relayHeaders($clientA->api_key))
            ->assertNotFound();
    }

    public function test_local_handler_job_posts_message_to_registered_endpoint(): void
    {
        Http::fake([
            'https://local.app.test/*' => Http::response(['ok' => true], 200),
        ]);

        $client = $this->createRelayClient();
        $handler = HubRelayHandler::query()->create([
            'hub_relay_client_id' => $client->id,
            'name' => 'SITREP Receiver',
            'endpoint_url' => 'https://local.app.test/hooks/relay',
            'message_type_pattern' => 'sitrep.*',
            'auth_token' => 'local-secret',
            'is_active' => true,
        ]);

        $message = HubRelayMessage::query()->create([
            'relay_id' => '01ARZ3NDEKTSV4RRFFQ69G5FCX',
            'source_hub_id' => 'city-hub',
            'source_system' => 'sitrep.app',
            'targets' => [['id' => '10', 'systems' => ['test.app']]],
            'message_type' => 'sitrep.record',
            'payload_format' => 'json',
            'payload_version' => '1.0',
            'content_hash' => 'hash-1',
            'payload' => ['incident_id' => 88],
            'priority' => 'normal',
            'attachments_count' => 0,
            'occurred_at' => now(),
        ]);

        $receipt = HubRelayReceipt::query()->create([
            'relay_id' => $message->relay_id,
            'source_hub_id' => $message->source_hub_id,
            'message_type' => $message->message_type,
            'status' => HubRelayReceipt::STATUS_PROCESSED,
            'content_hash' => $message->content_hash,
            'received_at' => now(),
            'processed_at' => now(),
        ]);

        $dispatch = HubRelayHandlerDispatch::query()->create([
            'hub_relay_handler_id' => $handler->id,
            'hub_relay_message_id' => $message->id,
            'hub_relay_receipt_id' => $receipt->id,
            'status' => HubRelayHandlerDispatch::STATUS_QUEUED,
            'queued_at' => now(),
        ]);

        (new DispatchRelayToLocalHandler($dispatch->id))->handle();

        Http::assertSent(fn ($request) =>
            $request->url() === 'https://local.app.test/hooks/relay'
            && $request->hasHeader('Authorization', 'Bearer local-secret')
            && $request['message']['relay_id'] === $message->relay_id
            && $request['receipt']['id'] === $receipt->id
        );

        $handler->refresh();
        $dispatch->refresh();
        $this->assertSame(HubRelayHandlerDispatch::STATUS_SUCCEEDED, $dispatch->status);
        $this->assertNotNull($handler->last_succeeded_at);
        $this->assertNull($handler->last_error);
    }
}
