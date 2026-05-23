<?php

namespace App\Installer;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

class InstallerStateStore
{
    private const ALLOWED_STATUSES = [
        'fresh',
        'environment_checked',
        'hq_validated',
        'settings_collected',
        'installing',
        'cleanup_pending',
        'installed',
        'failed',
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

        return array_merge($this->defaultState(), $decoded);
    }

    public function markEnvironmentChecked(array $summary): array
    {
        $state = $this->current();

        return $this->write(array_merge($state, [
            'status' => 'environment_checked',
            'current_step' => 'environment',
            'environment_summary' => $summary,
            'updated_at' => CarbonImmutable::now()->toIso8601String(),
        ]));
    }

    public function markHqValidated(array $hqData, ?array $admin = null): array
    {
        $state = $this->current();

        return $this->write(array_merge($state, [
            'status' => 'hq_validated',
            'current_step' => 'hq_identity',
            'hq' => $hqData,
            'admin' => $admin ?? $state['admin'],
            'updated_at' => CarbonImmutable::now()->toIso8601String(),
        ]));
    }

    public function markSettingsCollected(array $settings, ?array $admin = null): array
    {
        $state = $this->current();

        return $this->write(array_merge($state, [
            'status' => 'settings_collected',
            'current_step' => 'install_settings',
            'settings' => $settings,
            'admin' => $admin ?? $state['admin'],
            'updated_at' => CarbonImmutable::now()->toIso8601String(),
        ]));
    }

    public function markInstalled(array $summary): array
    {
        $state = $this->current();

        return $this->write(array_merge($state, [
            'status' => 'installed',
            'current_step' => 'installed',
            'install_summary' => $summary,
            'updated_at' => CarbonImmutable::now()->toIso8601String(),
        ]));
    }

    public function reset(): array
    {
        return $this->write($this->defaultState());
    }

    private function write(array $state): array
    {
        $status = (string) ($state['status'] ?? 'fresh');

        if (! in_array($status, self::ALLOWED_STATUSES, true)) {
            throw new InvalidArgumentException("Unsupported installer state [$status].");
        }

        $path = $this->path();
        $directory = dirname($path);

        if (! is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        file_put_contents(
            $path,
            json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        );

        return $state;
    }

    private function defaultState(): array
    {
        $timestamp = CarbonImmutable::now()->toIso8601String();

        return [
            'status' => 'fresh',
            'current_step' => 'environment',
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
            'environment_summary' => null,
            'hq' => null,
            'admin' => null,
            'settings' => null,
            'install_summary' => null,
        ];
    }

    private function path(): string
    {
        return (string) config('installer.state_path');
    }
}
