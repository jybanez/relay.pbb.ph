<?php

namespace App\Installer;

use Carbon\CarbonImmutable;
use RuntimeException;

class InstallerExecutionStateStore
{
    /**
     * @var list<array{key:string,label:string,pending_message:string}>
     */
    private array $stepDefinitions = [
        ['key' => 'prepare_workspace', 'label' => 'Prepare Workspace', 'pending_message' => 'Preparing installer workspace.'],
        ['key' => 'extract_release', 'label' => 'Extract Release', 'pending_message' => 'Extracting embedded Relay release package.'],
        ['key' => 'write_environment', 'label' => 'Write Environment', 'pending_message' => 'Writing environment configuration.'],
        ['key' => 'verify_database', 'label' => 'Verify Database', 'pending_message' => 'Verifying target database connectivity.'],
        ['key' => 'run_migrations', 'label' => 'Run Migrations', 'pending_message' => 'Applying database migrations.'],
        ['key' => 'create_admin', 'label' => 'Create Admin', 'pending_message' => 'Creating the initial Relay admin account.'],
        ['key' => 'write_install_lock', 'label' => 'Write Install Lock', 'pending_message' => 'Writing the installed lock marker.'],
        ['key' => 'prepare_cleanup', 'label' => 'Prepare Cleanup', 'pending_message' => 'Preparing installer cleanup targets.'],
        ['key' => 'finalize_installed_state', 'label' => 'Finalize Installed State', 'pending_message' => 'Finalizing installer state.'],
    ];

    public function current(): array
    {
        $path = $this->path();

        if (! is_file($path)) {
            return $this->defaultState();
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        if (! is_array($decoded)) {
            return $this->defaultState();
        }

        $state = array_merge($this->defaultState(), $decoded);
        $state['steps'] = $this->normalizeSteps($state['steps'] ?? []);

        return $state;
    }

    public function start(): array
    {
        $current = $this->current();
        $timestamp = CarbonImmutable::now()->toIso8601String();

        if (($current['status'] ?? 'idle') === 'running') {
            return $current;
        }

        return $this->write([
            'status' => 'running',
            'started_at' => $current['started_at'] ?? $timestamp,
            'updated_at' => $timestamp,
            'current_step' => $this->stepDefinitions[0]['key'],
            'last_completed_step' => null,
            'steps' => $this->normalizeSteps([]),
            'failure' => null,
            'retry_allowed' => false,
            'cleanup_pending' => false,
            'install_result' => null,
            'admin_credentials' => null,
        ]);
    }

    public function markStepRunning(string $key, ?string $message = null): array
    {
        $state = $this->current();
        $steps = $this->normalizeSteps($state['steps'] ?? []);

        foreach ($steps as &$step) {
            if ($step['key'] === $key) {
                $step['status'] = 'running';
                $step['message'] = $message ?? $step['pending_message'];
                break;
            }
        }

        return $this->write(array_merge($state, [
            'status' => 'running',
            'current_step' => $key,
            'updated_at' => CarbonImmutable::now()->toIso8601String(),
            'steps' => $steps,
            'failure' => null,
            'retry_allowed' => false,
        ]));
    }

    public function markStepCompleted(string $key, ?string $message = null): array
    {
        $state = $this->current();
        $steps = $this->normalizeSteps($state['steps'] ?? []);
        $nextStep = null;

        foreach ($steps as $index => &$step) {
            if ($step['key'] === $key) {
                $step['status'] = 'completed';
                $step['message'] = $message ?? $step['pending_message'];
                $nextStep = $steps[$index + 1]['key'] ?? null;
                break;
            }
        }

        return $this->write(array_merge($state, [
            'status' => $nextStep === null ? 'completed' : 'running',
            'current_step' => $nextStep,
            'last_completed_step' => $key,
            'updated_at' => CarbonImmutable::now()->toIso8601String(),
            'steps' => $steps,
            'failure' => null,
            'retry_allowed' => false,
        ]));
    }

    /**
     * @param  array{step:string,message:string,detail?:string|null,retry_allowed?:bool}  $failure
     */
    public function markFailed(array $failure): array
    {
        $state = $this->current();
        $steps = $this->normalizeSteps($state['steps'] ?? []);

        foreach ($steps as &$step) {
            if ($step['key'] === $failure['step']) {
                $step['status'] = 'failed';
                $step['message'] = $failure['message'];
                break;
            }
        }

        return $this->write(array_merge($state, [
            'status' => 'failed',
            'current_step' => $failure['step'],
            'updated_at' => CarbonImmutable::now()->toIso8601String(),
            'steps' => $steps,
            'failure' => [
                'step' => $failure['step'],
                'message' => $failure['message'],
                'detail' => $failure['detail'] ?? null,
            ],
            'retry_allowed' => (bool) ($failure['retry_allowed'] ?? false),
        ]));
    }

    /**
     * @param  array<string,mixed>  $result
     */
    public function markCompleted(array $result, ?array $adminCredentials = null): array
    {
        $state = $this->current();

        return $this->write(array_merge($state, [
            'status' => 'completed',
            'current_step' => null,
            'updated_at' => CarbonImmutable::now()->toIso8601String(),
            'steps' => $this->normalizeTerminalSteps($state['steps'] ?? []),
            'failure' => null,
            'retry_allowed' => false,
            'cleanup_pending' => (bool) data_get($result, 'cleanup.manifest'),
            'install_result' => $result,
            'admin_credentials' => $adminCredentials,
        ]));
    }

    /**
     * @param  array<string,mixed>  $result
     */
    public function storeInstallResult(array $result): array
    {
        $state = $this->current();

        return $this->write(array_merge($state, [
            'updated_at' => CarbonImmutable::now()->toIso8601String(),
            'install_result' => $result,
        ]));
    }

    public function storeAdminCredentials(array $adminCredentials): array
    {
        $state = $this->current();

        return $this->write(array_merge($state, [
            'updated_at' => CarbonImmutable::now()->toIso8601String(),
            'admin_credentials' => $adminCredentials,
        ]));
    }

    public function reset(): array
    {
        return $this->write($this->defaultState());
    }

    /**
     * @return list<array{key:string,label:string,status:string,message:?string,pending_message:string}>
     */
    public function steps(): array
    {
        return $this->normalizeSteps([]);
    }

    private function defaultState(): array
    {
        $timestamp = CarbonImmutable::now()->toIso8601String();

        return [
            'status' => 'idle',
            'started_at' => null,
            'updated_at' => $timestamp,
            'current_step' => null,
            'last_completed_step' => null,
            'steps' => $this->normalizeSteps([]),
            'failure' => null,
            'retry_allowed' => false,
            'cleanup_pending' => false,
            'install_result' => null,
            'admin_credentials' => null,
        ];
    }

    /**
     * @param  array<int,array<string,mixed>>  $existing
     * @return list<array{key:string,label:string,status:string,message:?string,pending_message:string}>
     */
    private function normalizeSteps(array $existing): array
    {
        $indexed = [];
        foreach ($existing as $step) {
            if (is_array($step) && isset($step['key'])) {
                $indexed[(string) $step['key']] = $step;
            }
        }

        $steps = [];

        foreach ($this->stepDefinitions as $definition) {
            $step = $indexed[$definition['key']] ?? [];
            $steps[] = [
                'key' => $definition['key'],
                'label' => $definition['label'],
                'status' => in_array($step['status'] ?? 'pending', ['pending', 'running', 'completed', 'failed'], true)
                    ? (string) ($step['status'] ?? 'pending')
                    : 'pending',
                'message' => array_key_exists('message', $step) ? (is_string($step['message']) ? $step['message'] : null) : null,
                'pending_message' => $definition['pending_message'],
            ];
        }

        return $steps;
    }

    /**
     * @param  array<int,array<string,mixed>>  $existing
     * @return list<array{key:string,label:string,status:string,message:?string,pending_message:string}>
     */
    private function normalizeTerminalSteps(array $existing): array
    {
        $steps = $this->normalizeSteps($existing);

        foreach ($steps as &$step) {
            if ($step['status'] === 'running') {
                $step['status'] = 'completed';
                $step['message'] = $step['message'] ?? $step['pending_message'];
            }
        }

        return $steps;
    }

    private function write(array $state): array
    {
        $path = $this->path();
        $directory = dirname($path);

        if (! is_dir($directory) && ! mkdir($directory, 0777, true) && ! is_dir($directory)) {
            throw new RuntimeException("Installer execution state directory [$directory] could not be created.");
        }

        file_put_contents(
            $path,
            json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        );

        return $state;
    }

    private function path(): string
    {
        return (string) config('installer.execution_state_path');
    }
}
