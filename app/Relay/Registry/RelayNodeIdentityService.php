<?php

namespace App\Relay\Registry;

use App\Models\HubRegistryHub;

class RelayNodeIdentityService
{
    public function localHqId(): ?string
    {
        $hubId = $this->snapshotValue('hub_id');

        if (is_int($hubId)) {
            return (string) $hubId;
        }

        if (is_string($hubId) && trim($hubId) !== '') {
            return trim($hubId);
        }

        return null;
    }

    public function localHubId(): ?string
    {
        $relayHubId = $this->snapshotValue('relay_hub_id');

        if (is_string($relayHubId) && trim($relayHubId) !== '') {
            return trim($relayHubId);
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

    private function snapshotValue(string $key): mixed
    {
        $path = public_path('hub.json');

        if (! is_file($path)) {
            return null;
        }

        $snapshot = json_decode((string) file_get_contents($path), true);

        if (! is_array($snapshot)) {
            return null;
        }

        return $snapshot[$key] ?? null;
    }
}
