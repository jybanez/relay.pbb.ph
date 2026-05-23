<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HubRegistryHub extends Model
{
    protected $table = 'hub_registry_hubs';

    protected $fillable = [
        'hq_id',
        'relay_hub_id',
        'code',
        'name',
        'deployment',
        'domain',
        'status',
        'country_code',
        'reg_code',
        'prov_code',
        'citymun_code',
        'brgy_code',
        'last_seen_at',
        'last_response_ms',
        'deployed_at',
        'has_token',
        'token_is_active',
        'token_last_used_at',
        'token_revoked_at',
        'token_issued_at',
        'raw_payload_json',
        'synced_at',
    ];

    protected $casts = [
        'hq_id' => 'integer',
        'last_seen_at' => 'datetime',
        'deployed_at' => 'date',
        'has_token' => 'bool',
        'token_is_active' => 'bool',
        'token_last_used_at' => 'datetime',
        'token_revoked_at' => 'datetime',
        'token_issued_at' => 'datetime',
        'raw_payload_json' => 'array',
        'synced_at' => 'datetime',
    ];

    public function uplinks(): HasMany
    {
        return $this->hasMany(HubRegistryLink::class, 'hub_relay_hub_id', 'relay_hub_id')
            ->where('relationship_type', HubRegistryLink::RELATIONSHIP_UPLINK);
    }

    public function sources(): HasMany
    {
        return $this->hasMany(HubRegistryLink::class, 'hub_relay_hub_id', 'relay_hub_id')
            ->where('relationship_type', HubRegistryLink::RELATIONSHIP_SOURCE);
    }
}
