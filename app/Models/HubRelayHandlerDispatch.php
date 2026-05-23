<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HubRelayHandlerDispatch extends Model
{
    use HasUlids;

    public const STATUS_QUEUED = 'queued';
    public const STATUS_SENDING = 'sending';
    public const STATUS_SUCCEEDED = 'succeeded';
    public const STATUS_FAILED = 'failed';
    public const STATUS_DEAD = 'dead';

    public $incrementing = false;
    protected $keyType = 'string';
    protected $primaryKey = 'id';

    protected $fillable = [
        'hub_relay_handler_id',
        'hub_relay_message_id',
        'hub_relay_receipt_id',
        'status',
        'attempt_count',
        'last_response_status',
        'last_error',
        'queued_at',
        'last_attempt_at',
        'next_retry_at',
        'succeeded_at',
        'failed_at',
    ];

    protected $casts = [
        'queued_at' => 'datetime',
        'last_attempt_at' => 'datetime',
        'next_retry_at' => 'datetime',
        'succeeded_at' => 'datetime',
        'failed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function handler(): BelongsTo
    {
        return $this->belongsTo(HubRelayHandler::class, 'hub_relay_handler_id');
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(HubRelayMessage::class, 'hub_relay_message_id');
    }

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(HubRelayReceipt::class, 'hub_relay_receipt_id');
    }
}
