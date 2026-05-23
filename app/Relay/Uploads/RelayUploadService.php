<?php

namespace App\Relay\Uploads;

use App\Models\HubRelayAttachment;
use App\Models\HubRelayMessage;
use App\Models\HubRelayUploadSession;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

class RelayUploadService
{
    public function initLocalUpload(HubRelayMessage $message, array $data): array
    {
        return DB::transaction(function () use ($message, $data) {
            $attachment = HubRelayAttachment::create([
                'hub_relay_message_id' => $message->id,
                'attachment_type' => $data['attachment_type'],
                'name' => $data['attachment_name'],
                'mime_type' => $data['mime_type'],
                'size_bytes' => $data['size_bytes'],
                'storage_disk' => config('relay.uploads.disk', 'local'),
                'storage_path' => '',
                'checksum' => $data['checksum'] ?? null,
            ]);

            $session = $this->createSession(
                message: $message,
                attachment: $attachment,
                direction: HubRelayUploadSession::DIRECTION_LOCAL_OUTBOUND,
                sourceHubId: $message->source_hub_id,
                targetHubId: $data['target_hub_id'] ?? null,
                attachmentName: $data['attachment_name'],
                mimeType: $data['mime_type'],
                sizeBytes: (int) $data['size_bytes'],
                checksum: $data['checksum'] ?? null,
                chunkSizeBytes: (int) ($data['chunk_size_bytes'] ?? config('relay.uploads.chunk_size_bytes', 1048576)),
            );

            $message->forceFill([
                'attachments_count' => $message->attachments()->count(),
            ])->save();

            return $this->sessionResponse($session, $attachment);
        });
    }

    public function initInboundUpload(array $data): array
    {
        return DB::transaction(function () use ($data) {
            $message = HubRelayMessage::query()
                ->where('relay_id', $data['relay_id'])
                ->where('source_hub_id', $data['source_hub_id'])
                ->first();

            if ($message === null) {
                throw new InvalidArgumentException('Cannot initialize upload for an unknown relay message');
            }

            $attachment = HubRelayAttachment::create([
                'hub_relay_message_id' => $message->id,
                'attachment_type' => $data['attachment_type'],
                'name' => $data['attachment_name'],
                'mime_type' => $data['mime_type'],
                'size_bytes' => $data['size_bytes'],
                'storage_disk' => config('relay.uploads.disk', 'local'),
                'storage_path' => '',
                'checksum' => $data['checksum'] ?? null,
            ]);

            $session = $this->createSession(
                message: $message,
                attachment: $attachment,
                direction: HubRelayUploadSession::DIRECTION_HUB_INBOUND,
                sourceHubId: $data['source_hub_id'],
                targetHubId: $data['target_hub_id'] ?? null,
                attachmentName: $data['attachment_name'],
                mimeType: $data['mime_type'],
                sizeBytes: (int) $data['size_bytes'],
                checksum: $data['checksum'] ?? null,
                chunkSizeBytes: (int) ($data['chunk_size_bytes'] ?? config('relay.uploads.chunk_size_bytes', 1048576)),
            );

            $message->forceFill([
                'attachments_count' => $message->attachments()->count(),
            ])->save();

            return $this->sessionResponse($session, $attachment);
        });
    }

    public function appendChunk(HubRelayUploadSession $session, int $chunkIndex, int $totalChunks, ?string $chunkChecksum, string $content): array
    {
        if ($session->transfer_status === HubRelayUploadSession::STATUS_COMPLETED) {
            throw new InvalidArgumentException('Upload session is already completed');
        }

        if ($content === '') {
            throw new InvalidArgumentException('Chunk content is required');
        }

        if ($chunkChecksum !== null && $chunkChecksum !== hash('sha256', $content)) {
            throw new InvalidArgumentException('Chunk checksum verification failed');
        }

        $disk = Storage::disk($session->storage_disk);
        $chunkPath = $this->chunkPath($session, $chunkIndex);
        $disk->put($chunkPath, $content);

        $chunkFiles = collect($disk->files($session->temp_path . '/chunks'));
        $transferredBytes = (int) $chunkFiles->sum(fn (string $path) => (int) $disk->size($path));
        $progress = $session->size_bytes > 0
            ? round(($transferredBytes / $session->size_bytes) * 100, 2)
            : 0;

        $session->forceFill([
            'transfer_status' => HubRelayUploadSession::STATUS_UPLOADING,
            'total_chunks' => $totalChunks,
            'current_chunk_index' => $chunkIndex + 1,
            'transferred_bytes' => $transferredBytes,
            'transfer_progress_percent' => min($progress, 100),
            'last_activity_at' => now(),
            'last_error' => null,
        ])->save();

        return [
            'accepted' => true,
            'upload_session_id' => $session->id,
            'received_chunk_index' => $chunkIndex,
            'transferred_bytes' => $session->transferred_bytes,
            'transfer_progress_percent' => $session->transfer_progress_percent,
            'next_chunk_index' => $chunkIndex + 1,
        ];
    }

    public function completeUpload(HubRelayUploadSession $session, int $totalChunks, ?string $finalChecksum): array
    {
        if ($session->transfer_status === HubRelayUploadSession::STATUS_COMPLETED) {
            return $this->completeResponse($session);
        }

        $disk = Storage::disk($session->storage_disk);
        $chunkDirectory = $session->temp_path . '/chunks';

        for ($index = 0; $index < $totalChunks; $index++) {
            if (! $disk->exists($this->chunkPath($session, $index))) {
                throw new InvalidArgumentException("Missing chunk [{$index}] for upload session");
            }
        }

        $session->forceFill([
            'transfer_status' => HubRelayUploadSession::STATUS_ASSEMBLING,
            'total_chunks' => $totalChunks,
            'last_activity_at' => now(),
            'last_error' => null,
        ])->save();

        $assembledPath = $this->assembledPath($session);
        $assembledContent = '';

        for ($index = 0; $index < $totalChunks; $index++) {
            $assembledContent .= $disk->get($this->chunkPath($session, $index));
        }

        $computedChecksum = hash('sha256', $assembledContent);
        $expectedChecksum = $finalChecksum ?: $session->checksum;

        if ($expectedChecksum !== null && $expectedChecksum !== '' && ! hash_equals($expectedChecksum, $computedChecksum)) {
            $session->forceFill([
                'transfer_status' => HubRelayUploadSession::STATUS_FAILED,
                'last_error' => 'Final checksum verification failed',
                'last_activity_at' => now(),
            ])->save();

            throw new InvalidArgumentException('Final checksum verification failed');
        }

        $disk->put($assembledPath, $assembledContent);

        $attachment = $session->attachment;
        if ($attachment !== null) {
            $attachment->forceFill([
                'storage_disk' => $session->storage_disk,
                'storage_path' => $assembledPath,
                'checksum' => $computedChecksum,
                'size_bytes' => strlen($assembledContent),
            ])->save();
        }

        $session->forceFill([
            'assembled_path' => $assembledPath,
            'transfer_status' => HubRelayUploadSession::STATUS_COMPLETED,
            'transferred_bytes' => strlen($assembledContent),
            'transfer_progress_percent' => 100,
            'current_chunk_index' => $totalChunks,
            'completed_at' => now(),
            'last_activity_at' => now(),
            'last_error' => null,
        ])->save();

        if ($disk->exists($chunkDirectory)) {
            $disk->deleteDirectory($chunkDirectory);
        }

        return $this->completeResponse($session->fresh(['attachment']));
    }

    public function getStatus(HubRelayUploadSession $session): array
    {
        return [
            'upload_session_id' => $session->id,
            'attachment_id' => $session->hub_relay_attachment_id,
            'transfer_status' => $session->transfer_status,
            'transfer_size_bytes' => $session->size_bytes,
            'transferred_bytes' => $session->transferred_bytes,
            'transfer_progress_percent' => $session->transfer_progress_percent,
            'current_chunk_index' => $session->current_chunk_index,
            'total_chunks' => $session->total_chunks,
            'last_activity_at' => optional($session->last_activity_at)?->toIso8601String(),
            'completed_at' => optional($session->completed_at)?->toIso8601String(),
            'attachment_name' => $session->attachment_name,
            'mime_type' => $session->mime_type,
        ];
    }

    public function completeAttachment(HubRelayAttachment $attachment, array $data): array
    {
        $session = $attachment->uploadSessions()
            ->latest('created_at')
            ->first();

        if ($session === null) {
            throw new InvalidArgumentException('No upload session found for attachment');
        }

        return $this->completeUpload(
            $session,
            (int) $data['total_chunks'],
            $data['final_checksum'] ?? null,
        );
    }

    private function createSession(
        HubRelayMessage $message,
        HubRelayAttachment $attachment,
        string $direction,
        ?string $sourceHubId,
        ?string $targetHubId,
        string $attachmentName,
        string $mimeType,
        int $sizeBytes,
        ?string $checksum,
        int $chunkSizeBytes,
    ): HubRelayUploadSession {
        $session = HubRelayUploadSession::create([
            'hub_relay_message_id' => $message->id,
            'hub_relay_attachment_id' => $attachment->id,
            'direction' => $direction,
            'source_hub_id' => $sourceHubId,
            'target_hub_id' => $targetHubId,
            'attachment_name' => $attachmentName,
            'mime_type' => $mimeType,
            'size_bytes' => $sizeBytes,
            'checksum' => $checksum,
            'chunk_size_bytes' => $chunkSizeBytes,
            'storage_disk' => config('relay.uploads.disk', 'local'),
            'temp_path' => trim(config('relay.uploads.temp_prefix', 'relay_uploads/tmp'), '/') . '/' . $attachment->id,
            'last_activity_at' => now(),
        ]);

        Storage::disk($session->storage_disk)->makeDirectory($session->temp_path . '/chunks');

        return $session;
    }

    private function sessionResponse(HubRelayUploadSession $session, HubRelayAttachment $attachment): array
    {
        return [
            'accepted' => true,
            'attachment_id' => $attachment->id,
            'upload_session_id' => $session->id,
            'chunk_size_bytes' => $session->chunk_size_bytes,
            'next_chunk_index' => 0,
        ];
    }

    private function completeResponse(HubRelayUploadSession $session): array
    {
        return [
            'assembled' => $session->transfer_status === HubRelayUploadSession::STATUS_COMPLETED,
            'verified' => $session->transfer_status === HubRelayUploadSession::STATUS_COMPLETED,
            'attachment_id' => $session->hub_relay_attachment_id,
            'upload_session_id' => $session->id,
            'receipt_status' => $session->direction === HubRelayUploadSession::DIRECTION_HUB_INBOUND ? 'received' : 'stored',
        ];
    }

    private function chunkPath(HubRelayUploadSession $session, int $chunkIndex): string
    {
        return $session->temp_path . '/chunks/' . str_pad((string) $chunkIndex, 8, '0', STR_PAD_LEFT) . '.part';
    }

    private function assembledPath(HubRelayUploadSession $session): string
    {
        $prefix = trim(config('relay.uploads.attachment_prefix', 'relay_attachments'), '/');
        $safeName = preg_replace('/[^A-Za-z0-9._-]/', '_', $session->attachment_name) ?: 'attachment.bin';

        return $prefix . '/' . $session->hub_relay_message_id . '/' . $session->hub_relay_attachment_id . '/' . $safeName;
    }
}
