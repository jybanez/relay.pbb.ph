<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HubRelayReceipt extends Model
{
    use HasUlids;

    public $incrementing = false;
    protected $keyType = 'string';
    protected $primaryKey = 'id';

    protected $fillable = [
        'relay_id',
        'source_hub_id',
        'message_type',
        'status',
        'content_hash',
        'received_at',
        'processed_at',
        'processing_notes',
    ];

    protected $casts = [
        'received_at' => 'datetime',
        'processed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    const STATUS_RECEIVED = 'received';
    const STATUS_PROCESSED = 'processed';
    const STATUS_DUPLICATE = 'duplicate';
    const STATUS_REJECTED = 'rejected';
    const STATUS_UNDELIVERABLE = 'undeliverable';

    public function handlerDispatches(): HasMany
    {
        return $this->hasMany(HubRelayHandlerDispatch::class, 'hub_relay_receipt_id');
    }
}
