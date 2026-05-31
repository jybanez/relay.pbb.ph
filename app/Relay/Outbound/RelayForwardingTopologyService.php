<?php

namespace App\Relay\Outbound;

use App\Models\HubRegistryLink;
use App\Relay\Registry\RelayNodeIdentityService;

class RelayForwardingTopologyService
{
    public function __construct(
        private RelayNodeIdentityService $nodeIdentity,
    ) {}

    public function nextHopHubIds(array $visitedHubIds = [], ?string $excludeHubId = null, array $targetHubIds = []): array
    {
        $localHqId = $this->nodeIdentity->localHqId();
        $targetHubIds = collect($targetHubIds)
            ->map(fn ($value): string => (string) $value)
            ->filter(fn (string $hubId): bool => $hubId !== '')
            ->reject(fn (string $hubId): bool => $localHqId !== null && $hubId === (string) $localHqId)
            ->reject(fn (string $hubId): bool => in_array($hubId, $visitedHubIds, true))
            ->values()
            ->all();

        if ($targetHubIds === []) {
            return [];
        }

        if ($localHqId === null) {
            return collect(array_keys(config('relay.targets', [])))
                ->map(fn ($value) => (string) $value)
                ->reject(fn (string $hubId) => $hubId === '')
                ->reject(fn (string $hubId) => $excludeHubId !== null && $hubId === (string) $excludeHubId)
                ->reject(fn (string $hubId) => in_array($hubId, $visitedHubIds, true))
                ->filter(fn (string $hubId): bool => in_array($hubId, $targetHubIds, true))
                ->values()
                ->all();
        }

        return HubRegistryLink::query()
            ->where('hub_hq_id', (int) $localHqId)
            ->whereIn('relationship_type', [
                HubRegistryLink::RELATIONSHIP_UPLINK,
                HubRegistryLink::RELATIONSHIP_SOURCE,
            ])
            ->pluck('linked_hq_id')
            ->map(fn ($value) => (string) $value)
            ->reject(fn (string $hubId) => $hubId === '')
            ->reject(fn (string $hubId) => $hubId === (string) $localHqId)
            ->reject(fn (string $hubId) => $excludeHubId !== null && $hubId === (string) $excludeHubId)
            ->reject(fn (string $hubId) => in_array($hubId, $visitedHubIds, true))
            ->unique()
            ->values()
            ->pipe(function ($collection) use ($visitedHubIds, $excludeHubId) {
                if ($collection->isNotEmpty()) {
                    return $collection->all();
                }

                return collect(array_keys(config('relay.targets', [])))
                    ->map(fn ($value) => (string) $value)
                    ->reject(fn (string $hubId) => $hubId === '')
                    ->reject(fn (string $hubId) => $excludeHubId !== null && $hubId === (string) $excludeHubId)
                    ->reject(fn (string $hubId) => in_array($hubId, $visitedHubIds, true))
                    ->values()
                    ->all();
            });
    }
}
