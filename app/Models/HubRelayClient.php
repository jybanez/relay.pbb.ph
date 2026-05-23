<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class HubRelayClient extends Model
{
    use HasUlids;

    public $incrementing = false;
    protected $keyType = 'string';
    protected $primaryKey = 'id';

    protected $fillable = [
        'name',
        'system_code',
        'api_key',
        'description',
        'is_active',
        'last_used_at',
    ];

    protected $casts = [
        'is_active' => 'bool',
        'last_used_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $hidden = ['api_key'];

    public function handlers(): HasMany
    {
        return $this->hasMany(HubRelayHandler::class, 'hub_relay_client_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(HubRelayMessage::class, 'hub_relay_client_id');
    }

    public static function generateApiKey(): string
    {
        return 'relay_'.Str::lower(Str::random(40));
    }

    public function maskedApiKey(): string
    {
        if (! is_string($this->api_key) || $this->api_key === '') {
            return 'Not set';
        }

        return substr($this->api_key, 0, 8).'...'.substr($this->api_key, -4);
    }
}
