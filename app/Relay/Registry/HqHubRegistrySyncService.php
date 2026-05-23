<?php

namespace App\Relay\Registry;

use App\Models\HubRegistryHub;
use App\Models\HubRegistryLink;
use App\Models\RelayNodeSetting;
use Illuminate\Support\Facades\DB;

class HqHubRegistrySyncService
{
    public function __construct(
        private HqHubRegistryClient $client,
    ) {}

    /**
     * @return array{synced_hubs:int,synced_links:int,local_relay_hub_id:?string,local_hq_id:int|null}
     */
    public function sync(): array
    {
        $hubs = $this->client->listHubs();
        $now = now();
        $syncedHubIds = [];
        $syncedLinks = 0;
        $localRelayHubId = $this->configuredLocalRelayHubId();
        $localHqId = $this->configuredLocalHqId();

        DB::transaction(function () use ($hubs, $now, &$syncedHubIds, &$syncedLinks, $localRelayHubId, $localHqId): void {
            foreach ($hubs as $hub) {
                if (! is_array($hub) || ! isset($hub['id'])) {
                    continue;
                }

                $hqHubId = (int) $hub['id'];
                $relayHubId = $this->nullableString($hub['relay_hub_id'] ?? null);

                if ($relayHubId === null) {
                    continue;
                }

                $syncedHubIds[] = $hqHubId;

                HubRegistryHub::query()->updateOrCreate(
                    ['relay_hub_id' => $relayHubId],
                    [
                        'hq_id' => $hqHubId,
                        'code' => $this->nullableString($hub['code'] ?? null),
                        'name' => (string) ($hub['name'] ?? ''),
                        'deployment' => (string) ($hub['deployment'] ?? 'other'),
                        'domain' => $this->nullableString($hub['domain'] ?? null),
                        'status' => (string) ($hub['status'] ?? 'planned'),
                        'country_code' => $this->nullableString($hub['country_code'] ?? null),
                        'reg_code' => $this->nullableString($hub['reg_code'] ?? null),
                        'prov_code' => $this->nullableString($hub['prov_code'] ?? null),
                        'citymun_code' => $this->nullableString($hub['citymun_code'] ?? null),
                        'brgy_code' => $this->nullableString($hub['brgy_code'] ?? null),
                        'last_seen_at' => $hub['last_seen_at'] ?? null,
                        'last_response_ms' => isset($hub['last_response_ms']) ? (int) $hub['last_response_ms'] : null,
                        'deployed_at' => $hub['deployed_at'] ?? null,
                        'has_token' => (bool) data_get($hub, 'token.has_token', false),
                        'token_is_active' => (bool) data_get($hub, 'token.is_active', false),
                        'token_last_used_at' => data_get($hub, 'token.last_used_at'),
                        'token_revoked_at' => data_get($hub, 'token.revoked_at'),
                        'token_issued_at' => data_get($hub, 'token.issued_at'),
                        'raw_payload_json' => $hub,
                        'synced_at' => $now,
                    ],
                );

                HubRegistryLink::query()
                    ->where('hub_hq_id', $hqHubId)
                    ->delete();

                foreach ($hub['uplinks'] ?? [] as $uplink) {
                    if (! is_array($uplink)) {
                        continue;
                    }

                    $this->upsertLink($hqHubId, $relayHubId, HubRegistryLink::RELATIONSHIP_UPLINK, $uplink, $now);
                    $syncedLinks++;
                }

                foreach ($hub['sources'] ?? [] as $source) {
                    if (! is_array($source)) {
                        continue;
                    }

                    $this->upsertLink($hqHubId, $relayHubId, HubRegistryLink::RELATIONSHIP_SOURCE, $source, $now);
                    $syncedLinks++;
                }
            }

            RelayNodeSetting::query()->updateOrCreate(
                ['id' => 1],
                [
                    'local_relay_hub_id' => $localRelayHubId,
                    'local_hq_id' => $localHqId,
                    'hq_sync_enabled' => (bool) config('relay.hq_registry.sync_enabled', false),
                    'hq_last_sync_at' => $now,
                    'hq_last_sync_status' => 'success',
                    'hq_last_sync_error' => null,
                    'outbound_topology_mode' => (string) config('relay.hq_registry.outbound_topology_mode', 'manual'),
                    'inbound_trust_mode' => (string) config('relay.hq_registry.inbound_trust_mode', 'manual'),
                ],
            );
        });

        return [
            'synced_hubs' => count($syncedHubIds),
            'synced_links' => $syncedLinks,
            'local_relay_hub_id' => $localRelayHubId,
            'local_hq_id' => $localHqId,
        ];
    }

    public function markFailed(\Throwable $e): void
    {
        RelayNodeSetting::query()->updateOrCreate(
            ['id' => 1],
            [
                'local_relay_hub_id' => $this->configuredLocalRelayHubId(),
                'local_hq_id' => $this->configuredLocalHqId(),
                'hq_sync_enabled' => (bool) config('relay.hq_registry.sync_enabled', false),
                'hq_last_sync_at' => now(),
                'hq_last_sync_status' => 'failed',
                'hq_last_sync_error' => $e->getMessage(),
                'outbound_topology_mode' => (string) config('relay.hq_registry.outbound_topology_mode', 'manual'),
                'inbound_trust_mode' => (string) config('relay.hq_registry.inbound_trust_mode', 'manual'),
            ],
        );
    }

    private function upsertLink(int $hubHqId, string $hubRelayHubId, string $relationshipType, array $payload, \Illuminate\Support\Carbon $now): void
    {
        $linkedHqId = (int) ($payload['hub']['id'] ?? $payload['uplink_hub_id'] ?? $payload['hub_id'] ?? 0);
        $linkedRelayHubId = $this->nullableString($payload['hub']['relay_hub_id'] ?? null);

        $normalizedLinkedHqId = $linkedHqId > 0 ? $linkedHqId : null;

        HubRegistryLink::query()->updateOrCreate(
            [
                'hub_hq_id' => $hubHqId,
                'linked_hq_id' => $normalizedLinkedHqId,
                'relationship_type' => $relationshipType,
            ],
            [
                'hub_relay_hub_id' => $hubRelayHubId,
                'linked_relay_hub_id' => $linkedRelayHubId,
                'uplink_type' => $this->nullableString($payload['uplink_type'] ?? null),
                'priority' => isset($payload['priority']) ? (int) $payload['priority'] : null,
                'is_primary' => (bool) ($payload['is_primary'] ?? false),
                'linked_domain' => $this->nullableString($payload['hub']['domain'] ?? $payload['uplink_domain'] ?? null),
                'raw_payload_json' => $payload,
                'synced_at' => $now,
            ],
        );
    }

    private function configuredLocalRelayHubId(): ?string
    {
        $value = config('relay.hq_registry.local_relay_hub_id');

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return trim($value);
    }

    private function configuredLocalHqId(): ?int
    {
        $value = config('relay.hq_registry.local_hq_id');

        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    private function nullableString(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
