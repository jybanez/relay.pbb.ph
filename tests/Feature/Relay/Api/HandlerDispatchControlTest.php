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

class HandlerDispatchControlTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_can_list_and_view_own_handler_dispatches(): void
    {
        $client = $this->createRelayClient();
        $handler = HubRelayHandler::query()->create([
            'hub_relay_client_id' => $client->id,
            'name' => 'SITREP Receiver',
            'endpoint_url' => 'https://local.app.test/hooks/relay',
            'message_type_pattern' => 'sitrep.*',
            'is_active' => true,
        ]);

        $message = HubRelayMessage::factory()->create([
            'message_type' => 'sitrep.record',
        ]);

        $receipt = HubRelayReceipt::query()->create([
            'relay_id' => $message->relay_id,
            'source_hub_id' => 'city-hub',
            'message_type' => $message->message_type,
            'status' => HubRelayReceipt::STATUS_PROCESSED,
            'received_at' => now(),
            'processed_at' => now(),
        ]);

        $dispatch = HubRelayHandlerDispatch::query()->create([
            'hub_relay_handler_id' => $handler->id,
            'hub_relay_message_id' => $message->id,
            'hub_relay_receipt_id' => $receipt->id,
            'status' => HubRelayHandlerDispatch::STATUS_FAILED,
            'attempt_count' => 2,
            'last_error' => 'Webhook timeout',
            'queued_at' => now(),
            'failed_at' => now(),
        ]);

        $list = $this->getJson('/api/v1/handler-dispatches', $this->relayHeaders($client->api_key));

        $list->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $dispatch->id);

        $show = $this->getJson('/api/v1/handler-dispatches/'.$dispatch->id, $this->relayHeaders($client->api_key));

        $show->assertOk()
            ->assertJsonPath('dispatch.id', $dispatch->id)
            ->assertJsonPath('dispatch.handler.name', 'SITREP Receiver');
    }

    public function test_client_can_retry_failed_handler_dispatch(): void
    {
        Queue::fake();

        $client = $this->createRelayClient();
        $handler = HubRelayHandler::query()->create([
            'hub_relay_client_id' => $client->id,
            'name' => 'SITREP Receiver',
            'endpoint_url' => 'https://local.app.test/hooks/relay',
            'message_type_pattern' => 'sitrep.*',
            'is_active' => true,
        ]);

        $message = HubRelayMessage::factory()->create();
        $receipt = HubRelayReceipt::query()->create([
            'relay_id' => $message->relay_id,
            'source_hub_id' => 'city-hub',
            'message_type' => $message->message_type,
            'status' => HubRelayReceipt::STATUS_PROCESSED,
            'received_at' => now(),
            'processed_at' => now(),
        ]);

        $dispatch = HubRelayHandlerDispatch::query()->create([
            'hub_relay_handler_id' => $handler->id,
            'hub_relay_message_id' => $message->id,
            'hub_relay_receipt_id' => $receipt->id,
            'status' => HubRelayHandlerDispatch::STATUS_DEAD,
            'attempt_count' => 3,
            'last_error' => 'Local handler rejected relay message with status 500.',
            'failed_at' => now(),
        ]);

        $response = $this->postJson('/api/v1/handler-dispatches/'.$dispatch->id.'/retry', [], $this->relayHeaders($client->api_key));

        $response->assertOk()
            ->assertJsonPath('dispatch.status', HubRelayHandlerDispatch::STATUS_QUEUED);

        Queue::assertPushed(DispatchRelayToLocalHandler::class, fn (DispatchRelayToLocalHandler $job) => $job->dispatchId === $dispatch->id);
    }

    public function test_local_handler_job_tracks_failed_attempt_state(): void
    {
        $client = $this->createRelayClient();
        $handler = HubRelayHandler::query()->create([
            'hub_relay_client_id' => $client->id,
            'name' => 'SITREP Receiver',
            'endpoint_url' => 'https://local.app.test/hooks/relay',
            'message_type_pattern' => 'sitrep.*',
            'is_active' => true,
        ]);

        $message = HubRelayMessage::factory()->create();
        $receipt = HubRelayReceipt::query()->create([
            'relay_id' => $message->relay_id,
            'source_hub_id' => 'city-hub',
            'message_type' => $message->message_type,
            'status' => HubRelayReceipt::STATUS_PROCESSED,
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

        config([
            'relay.local_handlers.max_attempts' => 3,
            'relay.local_handlers.backoff_seconds' => [30, 120, 600],
        ]);

        \Illuminate\Support\Facades\Http::fake([
            'https://local.app.test/*' => \Illuminate\Support\Facades\Http::response(['ok' => false], 500),
        ]);

        try {
            (new DispatchRelayToLocalHandler($dispatch->id))->handle();
            $this->fail('Expected local handler dispatch to throw.');
        } catch (\RuntimeException) {
            $dispatch->refresh();
            $this->assertSame(HubRelayHandlerDispatch::STATUS_FAILED, $dispatch->status);
            $this->assertSame(1, $dispatch->attempt_count);
            $this->assertSame(500, $dispatch->last_response_status);
            $this->assertNotNull($dispatch->next_retry_at);
        }
    }
}
