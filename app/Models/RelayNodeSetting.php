<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RelayNodeSetting extends Model
{
    protected $fillable = [
        'local_relay_hub_id',
        'local_hq_id',
        'hq_sync_enabled',
        'hq_last_sync_at',
        'hq_last_sync_status',
        'hq_last_sync_error',
        'outbound_topology_mode',
        'inbound_trust_mode',
    ];

    protected $casts = [
        'local_hq_id' => 'integer',
        'hq_sync_enabled' => 'bool',
        'hq_last_sync_at' => 'datetime',
    ];
}
