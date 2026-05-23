<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HubRelayUploadSession extends Model
{
    use HasUlids;

    public const DIRECTION_LOCAL_OUTBOUND = 'local_outbound';
    public const DIRECTION_HUB_INBOUND = 'hub_inbound';

    public const STATUS_INITIALIZING = 'initializing';
    public const STATUS_UPLOADING = 'uploading';
    public const STATUS_ASSEMBLING = 'assembling';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    public $incrementing = false;
    protected $keyType = 'string';
    protected $primaryKey = 'id';

    protected $fillable = [
        'hub_relay_message_id',
        'hub_relay_attachment_id',
        'direction',
        'source_hub_id',
        'target_hub_id',
        'attachment_name',
        'mime_type',
        'size_bytes',
        'checksum',
        'chunk_size_bytes',
        'total_chunks',
        'transferred_bytes',
        'transfer_progress_percent',
        'current_chunk_index',
        'transfer_status',
        'storage_disk',
        'temp_path',
        'assembled_path',
        'last_activity_at',
        'completed_at',
        'last_error',
    ];

    protected $casts = [
        'last_activity_at' => 'datetime',
        'completed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'transfer_progress_percent' => 'float',
    ];

    public function message(): BelongsTo
    {
        return $this->belongsTo(HubRelayMessage::class, 'hub_relay_message_id');
    }

    public function attachment(): BelongsTo
    {
        return $this->belongsTo(HubRelayAttachment::class, 'hub_relay_attachment_id');
    }
}
