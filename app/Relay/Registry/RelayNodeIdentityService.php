<?php

namespace App\Relay\Registry;

use App\Models\HubRegistryHub;
use App\Models\RelayNodeSetting;

class RelayNodeIdentityService
{
    public function localHqId(): ?string
    {
        $configured = config('relay.hq_registry.local_hq_id');

        if (is_string($configured) && trim($configured) !== '') {
            return trim($configured);
        }

        if (is_int($configured)) {
            return (string) $configured;
        }

        $nodeSetting = RelayNodeSetting::query()->find(1);

        if (is_int($nodeSetting?->local_hq_id)) {
            return (string) $nodeSetting->local_hq_id;
        }

        return null;
    }

    public function localHubId(): ?string
    {
        $manualHubId = config('relay.local_hub_id');

        if (is_string($manualHubId) && $manualHubId !== '') {
            return $manualHubId;
        }

        $configuredRelayHubId = config('relay.hq_registry.local_relay_hub_id');

        if (is_string($configuredRelayHubId) && $configuredRelayHubId !== '') {
            return $configuredRelayHubId;
        }

        $nodeSetting = RelayNodeSetting::query()->find(1);

        if (is_string($nodeSetting?->local_relay_hub_id) && $nodeSetting->local_relay_hub_id !== '') {
            return $nodeSetting->local_relay_hub_id;
        }

        return null;
    }

    public function localHub(): ?HubRegistryHub
    {
        $hubId = $this->localHubId();

        if ($hubId === null) {
            return null;
        }

        return HubRegistryHub::query()
            ->where('relay_hub_id', $hubId)
            ->first();
    }
}
