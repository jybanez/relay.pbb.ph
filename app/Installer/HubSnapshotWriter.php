<?php

namespace App\Installer;

class HubSnapshotWriter
{
    private const METADATA_KEYS = [
        'hydrated_at',
        'hydrated_from',
        'snapshot_version',
        'snapshot_hash',
        'hq_snapshot_hash',
    ];

    /**
     * @param  array<string, mixed>  $hq
     * @return array<string, mixed>
     */
    public function payload(array $hq): array
    {
        $hub = is_array($hq['raw_hub'] ?? null) ? $hq['raw_hub'] : $hq;
        $payload = $this->publicHubPayload($hub);

        $payload['base_url'] = (string) ($hq['hq_api_base_url'] ?? config('installer.hq_api_base_url'));
        $payload['hub_id'] = $payload['hub_id'] ?? ($hq['hq_hub_id'] ?? null);
        $payload['relay_hub_id'] = $payload['relay_hub_id'] ?? ($hq['relay_hub_id'] ?? null);
        $payload['name'] = $payload['name'] ?? ($hq['name'] ?? null);
        $payload['deployment'] = $payload['deployment'] ?? ($hq['deployment'] ?? null);
        $payload['domain'] = $payload['domain'] ?? $this->domainOnly($hq['domain'] ?? null);
        $payload['status'] = $payload['status'] ?? ($hq['status'] ?? null);
        $payload['uplinks'] = is_array($payload['uplinks'] ?? null) ? $payload['uplinks'] : [];
        $payload['sources'] = is_array($payload['sources'] ?? null) ? $payload['sources'] : [];

        return $this->orderedPayload($payload);
    }

    /**
     * @param  array<string, mixed>  $hq
     */
    public function writeForInstall(array $hq): ?string
    {
        $root = (string) config('installer.installed_app_root', base_path());
        $path = rtrim($root, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'hub.json';

        return $this->write($path, $this->payload($hq));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function write(string $path, array $payload): string
    {
        $directory = dirname($path);
        if (! is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        $content = json_encode($this->orderedPayload($payload), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE).PHP_EOL;
        $temporaryPath = $path.'.tmp.'.bin2hex(random_bytes(6));

        file_put_contents($temporaryPath, $content);
        rename($temporaryPath, $path);

        return $path;
    }

    /**
     * @param  array<string, mixed>  $hub
     */
    public function writeForHeartbeat(string $path, array $hub, ?string $snapshotVersion = null, ?string $hqSnapshotHash = null): string
    {
        $payload = $this->payload($hub);
        $payload['hydrated_at'] = now()->toIso8601String();
        $payload['hydrated_from'] = 'hq_heartbeat';

        if (is_string($snapshotVersion) && $snapshotVersion !== '') {
            $payload['snapshot_version'] = $snapshotVersion;
        }

        if (is_string($hqSnapshotHash) && $hqSnapshotHash !== '') {
            $payload['hq_snapshot_hash'] = $hqSnapshotHash;
        }

        $payload['snapshot_hash'] = $this->snapshotHash($payload);

        return $this->write($path, $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function snapshotHash(array $payload): string
    {
        $stable = $this->orderedPayload($payload);
        unset($stable['hydrated_at']);

        return hash('sha256', json_encode($stable, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    /**
     * @param  array<string, mixed>  $hub
     * @return array<string, mixed>
     */
    private function publicHubPayload(array $hub): array
    {
        $payload = [
            'base_url' => $hub['base_url'] ?? null,
            'hub_id' => $hub['hub_id'] ?? ($hub['id'] ?? null),
            'relay_hub_id' => $hub['relay_hub_id'] ?? null,
            'name' => $hub['name'] ?? null,
            'code' => $hub['code'] ?? null,
            'deployment' => $hub['deployment'] ?? null,
            'domain' => $this->domainOnly($hub['domain'] ?? null),
            'status' => $hub['status'] ?? null,
            'country_code' => $hub['country_code'] ?? null,
            'reg_code' => $hub['reg_code'] ?? null,
            'prov_code' => $hub['prov_code'] ?? null,
            'citymun_code' => $hub['citymun_code'] ?? null,
            'brgy_code' => $hub['brgy_code'] ?? null,
            'uplinks' => $this->publicList($hub['uplinks'] ?? []),
            'sources' => $this->publicList($hub['sources'] ?? []),
        ];

        return array_filter($payload, static fn (mixed $value): bool => $value !== null);
    }

    /**
     * @param  mixed  $items
     * @return list<array<string, mixed>>
     */
    private function publicList(mixed $items): array
    {
        if (! is_array($items)) {
            return [];
        }

        $list = [];
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $entry = [];
            foreach (['id', 'uplink_hub_id', 'hub_id', 'source_hub_id', 'uplink_type', 'source_type', 'uplink_domain', 'source_domain', 'priority', 'is_primary'] as $key) {
                if (array_key_exists($key, $item)) {
                    $entry[$key] = $item[$key];
                }
            }
            if (is_array($item['hub'] ?? null)) {
                $entry['hub'] = array_filter([
                    'id' => $item['hub']['id'] ?? null,
                    'name' => $item['hub']['name'] ?? null,
                    'code' => $item['hub']['code'] ?? null,
                    'deployment' => $item['hub']['deployment'] ?? null,
                    'domain' => $this->domainOnly($item['hub']['domain'] ?? null),
                    'status' => $item['hub']['status'] ?? null,
                ], static fn (mixed $value): bool => $value !== null);
            }

            $list[] = $entry;
        }

        return $list;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function orderedPayload(array $payload): array
    {
        $ordered = [];
        foreach (['base_url', 'hub_id', 'relay_hub_id', 'name', 'code', 'deployment', 'domain', 'status', 'country_code', 'reg_code', 'prov_code', 'citymun_code', 'brgy_code', 'uplinks', 'sources'] as $key) {
            if (array_key_exists($key, $payload)) {
                $ordered[$key] = $payload[$key];
            }
        }

        foreach (self::METADATA_KEYS as $key) {
            if (array_key_exists($key, $payload)) {
                $ordered[$key] = $payload[$key];
            }
        }

        return $ordered;
    }

    private function domainOnly(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $host = parse_url($value, PHP_URL_HOST);

        return is_string($host) && $host !== '' ? $host : $value;
    }
}
