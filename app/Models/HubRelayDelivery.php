<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HubRelayDelivery extends Model
{
    use HasUlids;

    public $incrementing = false;
    protected $keyType = 'string';
    protected $primaryKey = 'id';

    protected $fillable = [
        'hub_relay_message_id',
        'target_hub_id',
        'target_hq_hub_id',
        'target_system',
        'status',
        'attempt_count',
        'last_attempt_at',
        'delivered_at',
        'last_error',
        'next_retry_at',
    ];

    protected $casts = [
        'last_attempt_at' => 'datetime',
        'delivered_at' => 'datetime',
        'next_retry_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    const STATUS_QUEUED = 'queued';
    const STATUS_SENDING = 'sending';
    const STATUS_DELIVERED = 'delivered';
    const STATUS_FAILED = 'failed';
    const STATUS_DEAD = 'dead';

    public function message(): BelongsTo
    {
        return $this->belongsTo(HubRelayMessage::class, 'hub_relay_message_id');
    }

    public function isQueued(): bool
    {
        return $this->status === self::STATUS_QUEUED;
    }

    public function isDelivered(): bool
    {
        return $this->status === self::STATUS_DELIVERED;
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    public function isDead(): bool
    {
        return $this->status === self::STATUS_DEAD;
    }
}
