<?php

namespace App\Console\Commands;

use App\Installer\InstallerPackageBuildService;
use Illuminate\Console\Command;
use RuntimeException;

class BuildRelayInstallerPackageCommand extends Command
{
    protected $signature = 'relay:installer:build
        {output=storage/app/installer-build}
        {--runtime-only : Rebuild only the installer runtime and final installer.zip using the cached release artifact}
        {--release-only : Rebuild only the cached release artifact and manifest}
        {--force-release : Rebuild the embedded release artifact even when the cached fingerprint matches}';

    protected $description = 'Build the deployable Relay browser-installer package artifacts';

    public function handle(InstallerPackageBuildService $builder): int
    {
        try {
            $result = $builder->build(
                $this->outputPath((string) $this->argument('output')),
                [
                    'runtime_only' => (bool) $this->option('runtime-only'),
                    'release_only' => (bool) $this->option('release-only'),
                    'force_release' => (bool) $this->option('force-release'),
                ]
            );
        } catch (RuntimeException $e) {
            $this->error('Relay installer package build failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info('Relay installer package build completed.');
        $this->line('Output root: '.$result['output_root']);
        $this->line('Release artifact: '.$result['release_cache_package_path']);
        $this->line('Release manifest: '.$result['release_manifest_path']);
        $this->line('Release status: '.($result['release_built'] ? 'rebuilt' : 'reused'));

        if (! $result['release_only']) {
            $this->line('Bootstrap index: '.$result['bootstrap_index_path']);
            $this->line('Installer package: '.$result['installer_package_path']);
        }

        return self::SUCCESS;
    }

    private function outputPath(string $value): string
    {
        return preg_match('/^[A-Za-z]:\\\\|^\//', $value) === 1
            ? $value
            : base_path($value);
    }
}
