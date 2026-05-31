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

    protected $hidden = [
        'target_hub_ids',
        'target_system',
        'target_systems',
    ];

    protected static function booted(): void
    {
        static::saving(function (HubRelayMessage $message): void {
            $targets = $message->canonicalTargets();

            if ($targets === []) {
                return;
            }

            if (empty($message->target_hub_ids)) {
                $message->target_hub_ids = collect($targets)
                    ->pluck('id')
                    ->unique()
                    ->values()
                    ->all();
            }

            if (empty($message->target_systems)) {
                $message->target_systems = collect($targets)
                    ->flatMap(fn (array $target): array => $target['systems'])
                    ->unique()
                    ->values()
                    ->all();
            }

            if (empty($message->target_system)) {
                $message->target_system = collect($message->target_systems ?? [])->first();
            }
        });
    }

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

    public function canonicalTargets(): array
    {
        return self::normalizeTargets($this->targets ?? []);
    }

    public function targetHubIds(): array
    {
        return collect($this->canonicalTargets())
            ->pluck('id')
            ->unique()
            ->values()
            ->all();
    }

    public function targetSystemsForHub(string|int|null $hubId): array
    {
        if ($hubId === null || $hubId === '') {
            return [];
        }

        $hubId = (string) $hubId;

        return collect($this->canonicalTargets())
            ->filter(fn (array $target): bool => (string) $target['id'] === $hubId)
            ->flatMap(fn (array $target): array => $target['systems'])
            ->unique()
            ->values()
            ->all();
    }

    public function allTargetSystems(): array
    {
        return collect($this->canonicalTargets())
            ->flatMap(fn (array $target): array => $target['systems'])
            ->unique()
            ->values()
            ->all();
    }

    public static function normalizeTargets(array $targets): array
    {
        return collect($targets)
            ->filter(fn ($target): bool => is_array($target))
            ->map(function (array $target): ?array {
                $id = $target['id'] ?? $target['target_hq_hub_id'] ?? null;

                if (! is_string($id) && ! is_int($id)) {
                    return null;
                }

                $systems = $target['systems'] ?? null;

                if ($systems === null && isset($target['target_system'])) {
                    $systems = [$target['target_system']];
                }

                if (! is_array($systems)) {
                    return null;
                }

                $systems = collect($systems)
                    ->filter(fn ($system): bool => is_string($system) && trim($system) !== '')
                    ->map(fn (string $system): string => trim($system))
                    ->unique()
                    ->values()
                    ->all();

                if ($systems === []) {
                    return null;
                }

                return [
                    'id' => (string) $id,
                    'systems' => $systems,
                ];
            })
            ->filter()
            ->unique(fn (array $target): string => $target['id'].'|'.implode(',', $target['systems']))
            ->values()
            ->all();
    }
}
