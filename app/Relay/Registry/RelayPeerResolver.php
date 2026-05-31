<?php

namespace App\Relay\Registry;

use App\Models\HubRegistryHub;
use App\Models\HubRegistryLink;

class RelayPeerResolver
{
    public function __construct(
        private RelayNodeIdentityService $nodeIdentity,
    ) {}

    public function resolveOutbound(string $targetHubId): ?array
    {
        $legacyTarget = $this->legacyConfigEntry(config('relay.targets', []), $targetHubId);
        $override = $this->overrideConfigEntry(config('relay.target_overrides', []), $targetHubId);
        $hub = $this->registryHub($targetHubId);

        if ($legacyTarget === null && $override === null && $hub === null) {
            return null;
        }

        $baseUrl = $this->nullableString($override['base_url'] ?? null)
            ?? $this->nullableString($legacyTarget['base_url'] ?? null)
            ?? $this->domainBaseUrl($hub?->domain);

        if ($baseUrl === null) {
            return null;
        }

        $credentials = $this->credentialConfigEntry($targetHubId);

        return [
            'hub_id' => $hub?->relay_hub_id ?? $targetHubId,
            'hub_code' => $hub?->code,
            'base_url' => rtrim($baseUrl, '/'),
            'receive_path' => '/' . ltrim((string) ($override['receive_path'] ?? $legacyTarget['receive_path'] ?? '/api/v1/receive'), '/'),
            'token' => $this->nullableString($credentials['token'] ?? $legacyTarget['token'] ?? null),
            'client_certificate_path' => $this->nullableString($credentials['client_certificate_path'] ?? $legacyTarget['client_certificate_path'] ?? null),
            'client_private_key_path' => $this->nullableString($credentials['client_private_key_path'] ?? $legacyTarget['client_private_key_path'] ?? null),
            'client_private_key_passphrase' => $this->nullableString($credentials['client_private_key_passphrase'] ?? $legacyTarget['client_private_key_passphrase'] ?? null),
            'verify_peer' => $this->resolveVerifyPeerValue($override, $legacyTarget),
        ];
    }

    public function resolveInbound(string $hubId): ?array
    {
        $legacyHub = $this->legacyConfigEntry(config('relay.hubs', []), $hubId);
        $credentials = $this->credentialConfigEntry($hubId);
        $hub = $this->registryHub($hubId);

        if ($legacyHub === null && $credentials === null && $hub === null) {
            return null;
        }

        return [
            'hub' => $hub,
            'token' => $this->nullableString($credentials['token'] ?? $legacyHub['token'] ?? null),
            'tls_client_certificate_fingerprint' => $this->nullableString(
                $credentials['tls_client_certificate_fingerprint']
                    ?? $credentials['client_certificate_fingerprint']
                    ?? $legacyHub['tls_client_certificate_fingerprint']
                    ?? $legacyHub['client_certificate_fingerprint']
                    ?? null
            ),
        ];
    }

    public function acceptsInboundHub(string $hubId): bool
    {
        $mode = (string) config('relay.hq_registry.inbound_trust_mode', 'manual');

        if ($mode === 'manual') {
            return $this->resolveInbound($hubId) !== null;
        }

        $hub = $this->registryHub($hubId);

        if ($hub === null || ! in_array($hub->status, ['active', 'maintenance', 'provisioning'], true)) {
            return false;
        }

        if ($mode === 'known_hq_hubs') {
            return true;
        }

        if ($mode === 'hq_sources_only') {
            $localHubId = $this->nodeIdentity->localHubId();

            if (! is_string($localHubId) || $localHubId === '') {
                return false;
            }

            return HubRegistryLink::query()
                ->where('hub_relay_hub_id', $localHubId)
                ->where('relationship_type', HubRegistryLink::RELATIONSHIP_SOURCE)
                ->where('linked_relay_hub_id', $hub->relay_hub_id)
                ->exists();
        }

        return false;
    }

    private function registryHub(string $hubId): ?HubRegistryHub
    {
        $query = HubRegistryHub::query()
            ->where('relay_hub_id', $hubId)
            ->orWhere('code', $hubId)
            ->when(ctype_digit($hubId), fn ($builder) => $builder->orWhere('hq_id', (int) $hubId));

        return $query->first();
    }

    private function legacyConfigEntry(array $entries, string $hubId): ?array
    {
        if (isset($entries[$hubId]) && is_array($entries[$hubId])) {
            return $entries[$hubId];
        }

        return null;
    }

    private function overrideConfigEntry(array $entries, string $hubId): ?array
    {
        return $this->configEntryByHubKey($entries, $hubId);
    }

    private function credentialConfigEntry(string $hubId): ?array
    {
        return $this->configEntryByHubKey(config('relay.hub_credentials', []), $hubId);
    }

    private function configEntryByHubKey(array $entries, string $hubId): ?array
    {
        if (isset($entries[$hubId]) && is_array($entries[$hubId])) {
            return $entries[$hubId];
        }

        $hub = $this->registryHub($hubId);

        if ($hub !== null) {
            $hqKey = (string) $hub->hq_id;

            if (isset($entries[$hqKey]) && is_array($entries[$hqKey])) {
                return $entries[$hqKey];
            }

            if (isset($entries[$hub->relay_hub_id]) && is_array($entries[$hub->relay_hub_id])) {
                return $entries[$hub->relay_hub_id];
            }

            if ($hub->code !== null && isset($entries[$hub->code]) && is_array($entries[$hub->code])) {
                return $entries[$hub->code];
            }
        }

        return null;
    }

    private function domainBaseUrl(?string $domain): ?string
    {
        if ($domain === null || trim($domain) === '') {
            return null;
        }

        if (str_starts_with($domain, 'http://') || str_starts_with($domain, 'https://')) {
            return $domain;
        }

        return 'https://' . $domain;
    }

    private function resolveVerifyPeerValue(?array $override, ?array $legacyTarget): bool|string
    {
        foreach ([$override, $legacyTarget] as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $caPath = $entry['ca_certificate_path'] ?? null;

            if (is_string($caPath) && $caPath !== '') {
                return $caPath;
            }

            if (array_key_exists('verify_peer', $entry)) {
                return filter_var($entry['verify_peer'], FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? true;
            }
        }

        return true;
    }

    private function nullableString(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
