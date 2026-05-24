<?php

namespace App\Installer;

use RuntimeException;

class InstallerExecutionRunner
{
    public function __construct(
        private InstallerStateStore $stateStore,
        private InstallerExecutionStateStore $executionState,
        private InstallerExecutionLock $lock,
        private InstallerConfigWriter $configWriter,
        private InstallerDatabaseService $databaseService,
        private InstallerAdminProvisioner $adminProvisioner,
        private InstallerReleasePackageService $releasePackageService,
        private InstallerCleanupService $cleanupService,
        private HubSnapshotWriter $hubSnapshotWriter,
    ) {}

    public function progress(): array
    {
        return $this->executionState->current();
    }

    public function start(): array
    {
        return $this->executionState->start();
    }

    public function advance(): array
    {
        $locked = $this->lock->withLock(function () {
            $execution = $this->executionState->current();

            if (($execution['status'] ?? 'idle') === 'idle') {
                $execution = $this->executionState->start();
            }

            if (in_array($execution['status'] ?? 'idle', ['completed', 'failed'], true)) {
                return $execution;
            }

            $step = (string) ($execution['current_step'] ?? '');
            if ($step === '') {
                return $execution;
            }

            return $this->runStep($step);
        });

        return is_array($locked) ? $locked : $this->executionState->current();
    }

    public function retry(): array
    {
        $current = $this->executionState->current();
        if (($current['status'] ?? 'idle') !== 'failed' || ! ($current['retry_allowed'] ?? false)) {
            throw new RuntimeException('Installer execution is not in a retryable failed state.');
        }

        $step = (string) ($current['current_step'] ?? '');
        if ($step === '') {
            throw new RuntimeException('Installer execution does not have a failed step to retry.');
        }

        $this->executionState->markStepRunning($step);

        return $this->advance();
    }

    private function runStep(string $step): array
    {
        return match ($step) {
            'prepare_workspace' => $this->runSimpleStep($step, function () {
                $this->prepareWorkspace();

                return 'Installer workspace prepared.';
            }),
            'extract_release' => $this->runSimpleStep($step, function () {
                $release = $this->releasePackageService->extractIfConfigured();
                $current = is_array($this->executionState->current()['install_result'] ?? null)
                    ? $this->executionState->current()['install_result']
                    : [];
                $current['release'] = $release;
                $this->executionState->storeInstallResult($current);

                return $release === null
                    ? 'Using current app runtime; no embedded release extraction required.'
                    : 'Embedded Relay release extracted.';
            }),
            'write_environment' => $this->runSimpleStep($step, function () {
                [$hq, $settings] = $this->validatedState();
                $this->writeEnvironment($hq, $settings);

                return 'Environment configuration written.';
            }),
            'write_hub_snapshot' => $this->runSimpleStep($step, function () {
                [$hq] = $this->validatedState();
                $path = $this->hubSnapshotWriter->writeForInstall($hq);
                $current = is_array($this->executionState->current()['install_result'] ?? null)
                    ? $this->executionState->current()['install_result']
                    : [];
                $current['hub_snapshot'] = [
                    'path' => $path,
                    'url' => rtrim((string) ($hq['domain'] ?? config('app.url')), '/').'/hub.json',
                ];
                $this->executionState->storeInstallResult($current);

                return 'Public hub identity snapshot written.';
            }),
            'verify_database' => $this->runSimpleStep($step, function () {
                [, $settings] = $this->validatedState();
                $this->databaseService->verify($settings);

                return 'Target database connection verified.';
            }),
            'run_migrations' => $this->runSimpleStep($step, function () {
                [, $settings] = $this->validatedState();
                $this->databaseService->runAgainstConnection($settings, function () {
                    $this->databaseService->migrate();
                });

                return 'Database migrations completed.';
            }),
            'create_admin' => $this->runSimpleStep($step, function () {
                [, $settings, $admin] = $this->validatedState();
                $credentials = $this->databaseService->runAgainstConnection($settings, fn (string $connection) => $this->adminProvisioner->provision($admin, $connection));
                $current = is_array($this->executionState->current()['install_result'] ?? null)
                    ? $this->executionState->current()['install_result']
                    : [];
                $current['admin'] = $credentials;
                $this->executionState->storeInstallResult($current);
                $this->executionState->storeAdminCredentials($credentials);

                return 'Initial Relay admin account created.';
            }),
            'write_install_lock' => $this->runSimpleStep($step, function () {
                [$hq] = $this->validatedState();
                $lock = $this->writeInstallLock($hq);
                $current = $this->executionState->current();
                $result = is_array($current['install_result'] ?? null) ? $current['install_result'] : [];
                $result['lock'] = $lock;
                $this->executionState->storeInstallResult($result);

                return 'Installed lock marker written.';
            }),
            'prepare_cleanup' => $this->runSimpleStep($step, function () {
                $release = data_get($this->executionState->current(), 'install_result.release');
                $cleanupManifest = $this->cleanupService->createManifest(
                    $this->cleanupTargets(is_array($release) ? $release : null),
                    [
                        config('installer.lock_path'),
                        config('installer.env_path'),
                    ],
                );

                $current = is_array($this->executionState->current()['install_result'] ?? null)
                    ? $this->executionState->current()['install_result']
                    : [];
                $current['cleanup'] = [
                    'manifest' => $cleanupManifest,
                    'result' => null,
                    'auto_run' => (bool) config('installer.cleanup_auto_run', false),
                ];
                $this->executionState->storeInstallResult($current);

                return 'Installer cleanup manifest prepared.';
            }),
            'finalize_installed_state' => $this->finalizeInstalledState(),
            default => throw new RuntimeException("Unsupported installer execution step [$step]."),
        };
    }

    private function finalizeInstalledState(): array
    {
        return $this->runSimpleStep('finalize_installed_state', function () {
            $result = is_array($this->executionState->current()['install_result'] ?? null)
                ? $this->executionState->current()['install_result']
                : [];
            [$hq] = $this->validatedState();

            $lock = $result['lock'] ?? $this->writeInstallLock($hq);
            $release = $result['release'] ?? null;
            $cleanup = $result['cleanup'] ?? ['manifest' => null, 'result' => null, 'auto_run' => false];

            $state = $this->stateStore->markInstalled([
                'installed_at' => $lock['installed_at'] ?? now()->toIso8601String(),
                'app_url' => $lock['app_url'] ?? ($hq['domain'] ?? config('app.url')),
                'relay_hub_id' => $lock['relay_hub_id'] ?? ($hq['relay_hub_id'] ?? null),
                'cleanup_manifest_path' => config('installer.cleanup_manifest_path'),
                'cleanup_pending' => ($cleanup['result'] ?? null) === null && ! empty($cleanup['manifest']),
                'cleanup_auto_run' => (bool) ($cleanup['auto_run'] ?? false),
                'release' => $release,
            ]);

            $payload = [
                'state' => $state,
                'lock' => $lock,
                'admin' => $this->executionState->current()['admin_credentials'] ?? null,
                'release' => $release,
                'cleanup' => $cleanup,
                'hub_snapshot' => $result['hub_snapshot'] ?? null,
            ];

            return $this->executionState->markCompleted($payload, $payload['admin']);
        });
    }

    private function runSimpleStep(string $step, callable $callback): array
    {
        $this->executionState->markStepRunning($step);

        try {
            $message = $callback();
        } catch (RuntimeException $e) {
            return $this->executionState->markFailed([
                'step' => $step,
                'message' => $e->getMessage(),
                'detail' => $e->getMessage(),
                'retry_allowed' => ! in_array($step, ['write_install_lock', 'finalize_installed_state'], true),
            ]);
        }

        return $this->executionState->markStepCompleted($step, is_string($message) ? $message : null);
    }

    private function prepareWorkspace(): void
    {
        $bootstrapRoot = (string) config('installer.bootstrap_root', '');
        $envDirectory = dirname((string) config('installer.env_path', ''));
        $installedAppRoot = (string) config('installer.installed_app_root', '');

        foreach ([$bootstrapRoot, $envDirectory, $installedAppRoot] as $path) {
            if ($path === '') {
                continue;
            }

            if (! is_dir($path) && ! mkdir($path, 0777, true) && ! is_dir($path)) {
                throw new RuntimeException("Installer workspace path [$path] could not be created.");
            }
        }
    }

    /**
     * @return array{0: array<string,mixed>,1: array<string,mixed>,2: array<string,mixed>}
     */
    private function validatedState(): array
    {
        $state = $this->stateStore->current();
        $hq = $state['hq'] ?? null;
        $settings = $state['settings'] ?? null;
        $admin = $state['admin'] ?? null;

        if (! is_array($hq) || ! is_array($settings) || ! is_array($admin)) {
            throw new RuntimeException('Installer state is incomplete. Environment, HQ identity, and settings must be completed first.');
        }

        return [$hq, $settings, $admin];
    }

    /**
     * @param  array<string,mixed>  $hq
     * @param  array<string,mixed>  $settings
     */
    private function writeEnvironment(array $hq, array $settings): void
    {
        $appKey = 'base64:'.base64_encode(random_bytes(32));
        $appUrl = $hq['domain'] ?? config('app.url');

        $this->configWriter->write([
            'APP_URL' => $appUrl,
            'APP_KEY' => $appKey,
            'DB_CONNECTION' => $settings['database_driver'] ?? 'sqlite',
            'DB_HOST' => $settings['database_host'] ?? null,
            'DB_PORT' => $settings['database_port'] ?? null,
            'DB_DATABASE' => ($settings['database_driver'] ?? 'sqlite') === 'sqlite'
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
            'INSTALLER_ENABLED' => 'false',
        ]);
    }

    /**
     * @param  array<string,mixed>  $hq
     * @return array<string,mixed>
     */
    private function writeInstallLock(array $hq): array
    {
        $payload = [
            'installed_at' => now()->toIso8601String(),
            'relay_release_version' => (string) config('relay.version.package', '1.1.0'),
            'hq_hub_id' => $hq['hq_hub_id'] ?? null,
            'relay_hub_id' => $hq['relay_hub_id'] ?? null,
            'app_url' => $hq['domain'] ?? config('app.url'),
        ];

        file_put_contents(
            (string) config('installer.lock_path'),
            json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        );

        return $payload;
    }

    /**
     * @param  array<string,mixed>|null  $release
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
