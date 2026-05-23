<?php

namespace Tests\Feature\Relay;

use App\Models\HubRelayClient;
use App\Models\HubRelayDelivery;
use App\Models\HubRelayHandler;
use App\Models\HubRelayHandlerDispatch;
use App\Models\HubRelayMessage;
use App\Models\HubRelayReceipt;
use App\Models\HubRelayUploadSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_renders_monitoring_data(): void
    {
        $this->actingAs($this->createRelayUser());

        $message = HubRelayMessage::factory()->create([
            'source_hub_id' => 'barangay-hub',
            'source_system' => 'sitrep.app',
            'message_type' => 'sitrep.record',
        ]);

        HubRelayDelivery::create([
            'hub_relay_message_id' => $message->id,
            'target_hub_id' => 'city-hub',
            'status' => HubRelayDelivery::STATUS_FAILED,
            'attempt_count' => 2,
        ]);

        HubRelayReceipt::create([
            'relay_id' => $message->relay_id,
            'source_hub_id' => 'city-hub',
            'message_type' => 'sitrep.record',
            'status' => HubRelayReceipt::STATUS_RECEIVED,
            'received_at' => now(),
        ]);

        HubRelayClient::create([
            'name' => 'SITREP Client',
            'system_code' => 'sitrep.app',
            'api_key' => 'abc123',
            'is_active' => true,
        ]);

        HubRelayHandler::create([
            'hub_relay_client_id' => HubRelayClient::query()->firstOrFail()->id,
            'name' => 'SITREP Webhook',
            'endpoint_url' => 'https://local.app.test/hooks/relay',
            'message_type_pattern' => 'sitrep.*',
            'is_active' => true,
        ]);

        HubRelayHandlerDispatch::create([
            'hub_relay_handler_id' => HubRelayHandler::query()->firstOrFail()->id,
            'hub_relay_message_id' => $message->id,
            'hub_relay_receipt_id' => HubRelayReceipt::query()->firstOrFail()->id,
            'status' => HubRelayHandlerDispatch::STATUS_FAILED,
            'attempt_count' => 2,
            'last_error' => 'Webhook timeout',
            'queued_at' => now(),
            'failed_at' => now(),
            'next_retry_at' => now()->addMinute(),
        ]);

        HubRelayUploadSession::create([
            'hub_relay_message_id' => $message->id,
            'direction' => HubRelayUploadSession::DIRECTION_LOCAL_OUTBOUND,
            'source_hub_id' => 'barangay-hub',
            'target_hub_id' => 'city-hub',
            'attachment_name' => 'report.txt',
            'mime_type' => 'text/plain',
            'size_bytes' => 12,
            'chunk_size_bytes' => 6,
            'transferred_bytes' => 6,
            'transfer_progress_percent' => 50,
            'current_chunk_index' => 1,
            'transfer_status' => HubRelayUploadSession::STATUS_UPLOADING,
            'storage_disk' => 'local',
            'temp_path' => 'relay_uploads/tmp/test',
            'last_activity_at' => now(),
        ]);

        $response = $this->get('/relay');

        $response->assertOk()
            ->assertSee('Queued Deliveries')
            ->assertSee('Recent Deliveries')
            ->assertSee('Local Handlers')
            ->assertSee('Handler Dispatches');

        $this->getJson('/relay/data/dashboard')
            ->assertOk()
            ->assertJsonPath('hubStatus.0.target_hub_id', 'city-hub')
            ->assertJsonPath('recentUploads.0.attachment_name', 'report.txt')
            ->assertJsonPath('clients.0.name', 'SITREP Client')
            ->assertJsonPath('handlers.0.name', 'SITREP Webhook');
    }
}
