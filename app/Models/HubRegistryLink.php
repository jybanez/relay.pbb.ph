<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HubRegistryLink extends Model
{
    public const RELATIONSHIP_UPLINK = 'uplink';
    public const RELATIONSHIP_SOURCE = 'source';

    protected $table = 'hub_registry_links';

    protected $fillable = [
        'hub_relay_hub_id',
        'linked_relay_hub_id',
        'hub_hq_id',
        'linked_hq_id',
        'relationship_type',
        'uplink_type',
        'priority',
        'is_primary',
        'linked_domain',
        'raw_payload_json',
        'synced_at',
    ];

    protected $casts = [
        'hub_hq_id' => 'integer',
        'linked_hq_id' => 'integer',
        'priority' => 'integer',
        'is_primary' => 'bool',
        'raw_payload_json' => 'array',
        'synced_at' => 'datetime',
    ];

    public function hub(): BelongsTo
    {
        return $this->belongsTo(HubRegistryHub::class, 'hub_relay_hub_id', 'relay_hub_id');
    }

    public function linkedHub(): BelongsTo
    {
        return $this->belongsTo(HubRegistryHub::class, 'linked_relay_hub_id', 'relay_hub_id');
    }
}
