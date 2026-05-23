<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HubRelayHandler extends Model
{
    use HasUlids;

    public $incrementing = false;
    protected $keyType = 'string';
    protected $primaryKey = 'id';

    protected $fillable = [
        'hub_relay_client_id',
        'name',
        'endpoint_url',
        'message_type_pattern',
        'source_system',
        'source_hub_id',
        'auth_token',
        'is_active',
        'last_dispatched_at',
        'last_succeeded_at',
        'last_failed_at',
        'last_error',
    ];

    protected $casts = [
        'is_active' => 'bool',
        'last_dispatched_at' => 'datetime',
        'last_succeeded_at' => 'datetime',
        'last_failed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $hidden = [
        'auth_token',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(HubRelayClient::class, 'hub_relay_client_id');
    }

    public function dispatches(): HasMany
    {
        return $this->hasMany(HubRelayHandlerDispatch::class, 'hub_relay_handler_id');
    }
}
