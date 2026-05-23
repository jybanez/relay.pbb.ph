<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class HubRelayMessage extends Model
{
    use HasFactory;
    use HasUlids;

    public $incrementing = false;
    protected $keyType = 'string';
    protected $primaryKey = 'id';

    protected $fillable = [
        'hub_relay_client_id',
        'relay_id',
        'origin_hq_hub_id',
        'source_hub_id',
        'source_system',
        'target_hub_ids',
        'targets',
        'target_system',
        'target_systems',
        'hop_trace',
        'message_type',
        'payload_format',
        'payload_version',
        'reference_type',
        'reference_id',
        'content_hash',
        'payload',
        'tags',
        'priority',
        'attachments_count',
        'correlation_id',
        'occurred_at',
    ];

    protected $casts = [
        'target_hub_ids' => 'array',
        'targets' => 'array',
        'target_systems' => 'array',
        'hop_trace' => 'array',
        'payload' => 'array',
        'tags' => 'array',
        'occurred_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function deliveries(): HasMany
    {
        return $this->hasMany(HubRelayDelivery::class, 'hub_relay_message_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(HubRelayClient::class, 'hub_relay_client_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(HubRelayAttachment::class, 'hub_relay_message_id');
    }

    public function uploadSessions(): HasMany
    {
        return $this->hasMany(HubRelayUploadSession::class, 'hub_relay_message_id');
    }

    public function handlerDispatches(): HasMany
    {
        return $this->hasMany(HubRelayHandlerDispatch::class, 'hub_relay_message_id');
    }

    public function receipt(): HasOne
    {
        return $this->hasOne(HubRelayReceipt::class, 'relay_id', 'relay_id');
    }
}
