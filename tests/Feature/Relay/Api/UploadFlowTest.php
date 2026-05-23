<?php

namespace Tests\Feature\Relay\Api;

use App\Models\HubRelayAttachment;
use App\Models\HubRelayMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UploadFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        config([
            'relay.hubs' => [
                'city-hub' => ['token' => 'shared-city-key'],
            ],
        ]);
    }

    private function hubHeaders(string $token = 'shared-city-key', string $hubId = 'city-hub'): array
    {
        return [
            'X-Relay-Hub-Key' => $token,
            'X-Relay-Hub-Id' => $hubId,
        ];
    }

    public function test_local_attachment_chunk_flow_completes_and_stores_file(): void
    {
        $this->createRelayClient();

        $message = HubRelayMessage::factory()->create([
            'source_hub_id' => 'barangay-hub',
        ]);

        $contentA = 'hello ';
        $contentB = 'world';
        $fullContent = $contentA . $contentB;
        $checksum = hash('sha256', $fullContent);

        $init = $this->postJson(
            "/api/v1/messages/{$message->id}/attachments/init",
            [
                'attachment_type' => 'file',
                'attachment_name' => 'report.txt',
                'mime_type' => 'text/plain',
                'size_bytes' => strlen($fullContent),
                'checksum' => $checksum,
                'chunk_size_bytes' => 6,
                'target_hub_id' => 'city-hub',
            ],
            $this->relayHeaders()
        );

        $init->assertStatus(201)
            ->assertJson([
                'success' => true,
                'accepted' => true,
            ]);

        $sessionId = $init->json('upload_session_id');
        $attachmentId = $init->json('attachment_id');

        $chunkOne = $this->call(
            'POST',
            "/api/v1/uploads/{$sessionId}/chunk",
            ['chunk_index' => 0, 'total_chunks' => 2, 'chunk_checksum' => hash('sha256', $contentA)],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/octet-stream',
                'HTTP_X_RELAY_KEY' => 'test-relay-key',
            ],
            $contentA
        );

        $chunkOne->assertStatus(200)
            ->assertJson([
                'success' => true,
                'accepted' => true,
                'received_chunk_index' => 0,
            ]);

        $chunkTwo = $this->call(
            'POST',
            "/api/v1/uploads/{$sessionId}/chunk",
            ['chunk_index' => 1, 'total_chunks' => 2, 'chunk_checksum' => hash('sha256', $contentB)],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/octet-stream',
                'HTTP_X_RELAY_KEY' => 'test-relay-key',
            ],
            $contentB
        );

        $chunkTwo->assertStatus(200);

        $complete = $this->postJson(
            "/api/v1/uploads/{$sessionId}/complete",
            ['total_chunks' => 2, 'final_checksum' => $checksum],
            $this->relayHeaders()
        );

        $complete->assertStatus(200)
            ->assertJson([
                'success' => true,
                'assembled' => true,
                'verified' => true,
                'attachment_id' => $attachmentId,
            ]);

        $status = $this->getJson("/api/v1/uploads/{$sessionId}", $this->relayHeaders());

        $status->assertStatus(200)
            ->assertJson([
                'success' => true,
                'transfer_status' => 'completed',
                'transfer_progress_percent' => 100.0,
            ]);

        $attachment = HubRelayAttachment::query()->findOrFail($attachmentId);

        $this->assertSame($checksum, $attachment->checksum);
        $this->assertTrue(Storage::disk('local')->exists($attachment->storage_path));
        $this->assertSame($fullContent, Storage::disk('local')->get($attachment->storage_path));
    }

    public function test_hub_to_hub_upload_flow_initializes_after_inbound_receive(): void
    {
        $receivePayload = [
            'relay_id' => '01ARZ3NDEKTSV4RRFFQ69G5FAV',
            'origin_hq_hub_id' => '6',
            'source_hub_id' => 'city-hub',
            'source_system' => 'sitrep.app',
            'target_hq_hub_id' => 10,
            'target_systems' => ['test.app'],
            'message_type' => 'attachment.file',
            'attachments_count' => 1,
            'payload' => ['incident_id' => 99],
        ];

        $this->postJson('/api/v1/receive', $receivePayload, ['X-Relay-Hub-Key' => 'shared-city-key'])
            ->assertStatus(201);

        $content = 'binary-data';
        $checksum = hash('sha256', $content);

        $init = $this->postJson('/api/v1/upload/init', [
            'relay_id' => '01ARZ3NDEKTSV4RRFFQ69G5FAV',
            'source_hub_id' => 'city-hub',
            'target_hub_id' => 'this-hub',
            'attachment_type' => 'file',
            'attachment_name' => 'evidence.bin',
            'mime_type' => 'application/octet-stream',
            'size_bytes' => strlen($content),
            'checksum' => $checksum,
            'chunk_size_bytes' => 1024,
        ], $this->hubHeaders());

        $init->assertStatus(201)
            ->assertJson([
                'success' => true,
                'accepted' => true,
            ]);

        $sessionId = $init->json('upload_session_id');
        $attachmentId = $init->json('attachment_id');

        $chunk = $this->call(
            'POST',
            '/api/v1/upload/chunk',
            [
                'upload_session_id' => $sessionId,
                'chunk_index' => 0,
                'total_chunks' => 1,
                'chunk_checksum' => $checksum,
            ],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/octet-stream',
                'HTTP_X_RELAY_HUB_KEY' => 'shared-city-key',
                'HTTP_X_RELAY_HUB_ID' => 'city-hub',
            ],
            $content
        );

        $chunk->assertStatus(200)
            ->assertJson([
                'success' => true,
                'accepted' => true,
                'received_chunk_index' => 0,
            ]);

        $complete = $this->postJson('/api/v1/upload/complete', [
            'upload_session_id' => $sessionId,
            'total_chunks' => 1,
            'final_checksum' => $checksum,
        ], $this->hubHeaders());

        $complete->assertStatus(200)
            ->assertJson([
                'success' => true,
                'assembled' => true,
                'verified' => true,
                'attachment_id' => $attachmentId,
            ]);

        $status = $this->getJson("/api/v1/upload/{$sessionId}/status", $this->hubHeaders());

        $status->assertStatus(200)
            ->assertJson([
                'success' => true,
                'transfer_status' => 'completed',
            ]);

        $attachment = HubRelayAttachment::query()->findOrFail($attachmentId);

        $this->assertTrue(Storage::disk('local')->exists($attachment->storage_path));
        $this->assertSame($content, Storage::disk('local')->get($attachment->storage_path));
    }
}
