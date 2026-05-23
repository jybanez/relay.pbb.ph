<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HubRelayAttachment extends Model
{
    use HasUlids;

    public $incrementing = false;
    protected $keyType = 'string';
    protected $primaryKey = 'id';

    protected $fillable = [
        'hub_relay_message_id',
        'attachment_type',
        'name',
        'mime_type',
        'size_bytes',
        'storage_disk',
        'storage_path',
        'checksum',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function message(): BelongsTo
    {
        return $this->belongsTo(HubRelayMessage::class, 'hub_relay_message_id');
    }

    public function uploadSessions(): HasMany
    {
        return $this->hasMany(HubRelayUploadSession::class, 'hub_relay_attachment_id');
    }
}
