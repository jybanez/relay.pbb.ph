<?php

namespace App\Installer;

use Carbon\CarbonImmutable;
use RuntimeException;

class InstallerExecutionService
{
    public function __construct(
        private InstallerStateStore $stateStore,
        private InstallerConfigWriter $configWriter,
        private InstallerDatabaseService $databaseService,
        private InstallerAdminProvisioner $adminProvisioner,
        private InstallerReleasePackageService $releasePackageService,
        private InstallerCleanupService $cleanupService,
        private HubSnapshotWriter $hubSnapshotWriter,
    ) {}

    public function execute(): array
    {
        $state = $this->stateStore->current();
        $hq = $state['hq'] ?? null;
        $settings = $state['settings'] ?? null;
        $admin = $state['admin'] ?? null;

        if (! is_array($hq) || ! is_array($settings) || ! is_array($admin)) {
            throw new RuntimeException('Installer state is incomplete. Environment, HQ identity, and settings must be completed first.');
        }

        $release = $this->releasePackageService->extractIfConfigured();
        $this->databaseService->verify($settings);

        $appKey = 'base64:'.base64_encode(random_bytes(32));
        $appUrl = $hq['domain'] ?? config('app.url');

        $this->configWriter->write([
            'APP_URL' => $appUrl,
            'APP_KEY' => $appKey,
            'DB_CONNECTION' => $settings['database_driver'] ?? 'sqlite',
            'DB_HOST' => $settings['database_host'] ?? null,
            'DB_PORT' => $settings['database_port'] ?? null,
            'DB_DATABASE' => $settings['database_driver'] === 'sqlite'
                ? ($settings['sqlite_path'] ?? null)
                : ($settings['database_name'] ?? null),
            'DB_USERNAME' => $settings['database_username'] ?? null,
            'DB_PASSWORD' => $settings['database_password'] ?? null,
            'RELAY_LOCAL_HUB_ID' => $hq['relay_hub_id'] ?? null,
            'RELAY_HQ_API_ENABLED' => 'true',
            'RELAY_HQ_API_BASE_URL' => $hq['hq_api_base_url'] ?? config('installer.hq_api_base_url'),
            'RELAY_HQ_API_TOKEN' => $hq['token'] ?? null,
            'RELAY_HQ_LOCAL_RELAY_HUB_ID' => $hq['relay_hub_id'] ?? null,
            'RELAY_HQ_LOCAL_HQ_ID' => $hq['hq_hub_id'] ?? null,
            'RELAY_HQ_SYNC_ENABLED' => 'true',
            'RELAY_HQ_HEARTBEAT_ENABLED' => 'true',
            'INSTALLER_ENABLED' => 'false',
        ]);

        $hubSnapshotPath = $this->hubSnapshotWriter->writeForInstall($hq);

        $adminCredentials = $this->databaseService->runAgainstConnection($settings, function (string $connection) use ($admin): array {
            $this->databaseService->migrate();

            return $this->adminProvisioner->provision($admin, $connection);
        });

        $lock = $this->writeInstallLock($hq, $appUrl);
        $cleanupManifest = $this->cleanupService->createManifest(
            $this->cleanupTargets($release),
            [
                config('installer.lock_path'),
                config('installer.env_path'),
            ],
        );

        if ((bool) config('installer.cleanup_auto_run', false)) {
            $cleanupResult = $this->cleanupService->cleanup();
        } else {
            $cleanupResult = null;
        }

        $state = $this->stateStore->markInstalled([
            'installed_at' => $lock['installed_at'],
            'app_url' => $appUrl,
            'relay_hub_id' => $hq['relay_hub_id'] ?? null,
            'cleanup_manifest_path' => config('installer.cleanup_manifest_path'),
            'cleanup_pending' => $cleanupResult === null && $cleanupManifest !== [],
            'cleanup_auto_run' => (bool) config('installer.cleanup_auto_run', false),
            'release' => $release,
        ]);

        return [
            'state' => $state,
            'lock' => $lock,
            'admin' => $adminCredentials,
            'release' => $release,
            'hub_snapshot' => [
                'path' => $hubSnapshotPath,
                'url' => rtrim((string) ($hq['domain'] ?? config('app.url')), '/').'/hub.json',
            ],
            'cleanup' => [
                'manifest' => $cleanupManifest,
                'result' => $cleanupResult,
                'auto_run' => (bool) config('installer.cleanup_auto_run', false),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $hq
     * @return array<string, mixed>
     */
    private function writeInstallLock(array $hq, ?string $appUrl): array
    {
        $payload = [
            'installed_at' => CarbonImmutable::now()->toIso8601String(),
            'relay_release_version' => (string) config('relay.version.package', '1.1.0'),
            'hq_hub_id' => $hq['hq_hub_id'] ?? null,
            'relay_hub_id' => $hq['relay_hub_id'] ?? null,
            'app_url' => $appUrl,
        ];

        file_put_contents(
            (string) config('installer.lock_path'),
            json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        );

        return $payload;
    }

    /**
     * @param  array<string, mixed>|null  $release
     * @return list<string>
     */
    private function cleanupTargets(?array $release): array
    {
        $targets = [];

        $bootstrapRoot = (string) config('installer.bootstrap_root', '');
        if ($bootstrapRoot !== '') {
            $targets[] = $bootstrapRoot;
        }

        $releaseExtractRoot = $release['extract_root'] ?? null;
        $installedAppRoot = (string) config('installer.installed_app_root', '');
        if (
            is_string($releaseExtractRoot)
            && $releaseExtractRoot !== ''
            && ($installedAppRoot === '' || $this->normalizePath($releaseExtractRoot) !== $this->normalizePath($installedAppRoot))
        ) {
            $targets[] = $releaseExtractRoot;
        }

        $releasePackagePath = (string) config('installer.release_package_path', '');
        if ($releasePackagePath !== '') {
            $targets[] = $releasePackagePath;
        }

        return array_values(array_unique($targets));
    }

    private function normalizePath(string $path): string
    {
        return rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path), DIRECTORY_SEPARATOR);
    }
}
