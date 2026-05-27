<?php

namespace App\Installer;

use RuntimeException;
use ZipArchive;

class InstallerPackageBuildService
{
    private const OFFICIAL_REPOSITORY_URL = 'https://github.com/jybanez/relay.pbb.ph';

    private string $runtimeTemplateRoot;

    /**
     * @var list<string>
     */
    private array $releaseDirectories = [
        'app',
        'bootstrap',
        'config',
        'database',
        'installer',
        'public',
        'resources',
        'routes',
        'tools',
        'vendor',
    ];

    /**
     * @var list<string>
     */
    private array $releaseFiles = [
        'artisan',
        'composer.json',
        'composer.lock',
        '.env.example',
        'release.json',
    ];

    /**
     * @var list<string>
     */
    private array $storageDirectories = [
        'app',
        'framework/cache',
        'framework/sessions',
        'framework/views',
        'logs',
    ];

    /**
     * Directory names that should never be shipped in the standalone installer runtime
     * or the embedded Laravel release payload.
     *
     * @var list<string>
     */
    private array $excludedDirectoryNames = [
        '.git',
        '.github',
        '.gitlab',
        '.idea',
        '.vscode',
        'demo',
        'demos',
        'docs',
        'node_modules',
        'sample',
        'samples',
        'scripts',
        'temp',
        'test',
        'tests',
        'tmp',
    ];

    /**
     * Non-runtime Helper audit/doc files that are useful in the checkout but
     * should not inflate release artifacts. VENDORED.md is kept as the compact
     * provenance marker.
     *
     * @var list<string>
     */
    private array $excludedFileNames = [
        'changelog.upstream.md',
        'readme.upstream.md',
    ];

    /**
     * Files that should not ship inside the installed Relay release payload.
     *
     * @var list<string>
     */
    private array $releaseExcludedFiles = [
        'app/Console/Commands/BuildRelayInstallerPackageCommand.php',
        'app/Console/Commands/CreateRelayUserCommand.php',
        'app/Installer/InstallerPackageBuildService.php',
        'installer/index.php',
        'routes/console.php',
        'public/vendor/helpers.pbb.ph/CHANGELOG.upstream.md',
        'public/vendor/helpers.pbb.ph/README.upstream.md',
        'resources/vendor/helpers.pbb.ph/CHANGELOG.upstream.md',
        'resources/vendor/helpers.pbb.ph/README.upstream.md',
        'resources/vendor/helpers.pbb.ph/VENDORED.md',
        'pbb.ph.crt',
        'pbb.ph.key',
        'phpunit.xml',
        'package.json',
        'vite.config.js',
        'PHASE1_IMPLEMENTATION.md',
        'PHASE1_SITREP.md',
        'tmp_maestro_heartbeat.json',
        'tmp_ui.form.modal.upstream.js',
    ];

    /**
     * Directory prefixes that are valid in the working checkout but should not
     * ship in the installed Relay payload.
     *
     * @var list<string>
     */
    private array $releaseExcludedDirectoryPrefixes = [
        'database/factories',
        'database/seeders',
        'public/relay-installer',
        'public/vendor/helpers.pbb.ph/css',
        'public/vendor/helpers.pbb.ph/js/demo',
        'public/vendor/helpers.pbb.ph/js/incident',
        'public/vendor/helpers.pbb.ph/js/vendor',
        'resources/vendor/helpers.pbb.ph',
        'tools/build',
        'tools/dev',
        'tools/package',
        'vendor/fakerphp',
        'vendor/laravel/pail',
        'vendor/laravel/pint',
        'vendor/laravel/sail',
        'vendor/mockery',
        'vendor/nunomaduro/collision',
        'vendor/phpunit',
        'vendor/spatie/laravel-ignition',
    ];

    public function __construct(
        private ?string $sourceRoot = null,
    ) {
        $this->runtimeTemplateRoot = $this->sourceRoot().DIRECTORY_SEPARATOR.'installer-runtime-template';
    }

    /**
     * @param  array{runtime_only?: bool, release_only?: bool, force_release?: bool}  $options
     * @return array<string, mixed>
     */
    public function build(string $outputRoot, array $options = []): array
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('ZipArchive is required to build Relay installer packages.');
        }

        $runtimeOnly = (bool) ($options['runtime_only'] ?? false);
        $releaseOnly = (bool) ($options['release_only'] ?? false);
        $forceRelease = (bool) ($options['force_release'] ?? false);

        if ($runtimeOnly && $releaseOnly) {
            throw new RuntimeException('Runtime-only and release-only build modes cannot be combined.');
        }

        $sourceRoot = $this->sourceRoot();
        $outputRoot = $this->normalizePath($outputRoot);
        $cacheRoot = $outputRoot.DIRECTORY_SEPARATOR.'.cache';
        $buildRoot = $outputRoot.DIRECTORY_SEPARATOR.'.build';
        $runtimeBuildRoot = $buildRoot.DIRECTORY_SEPARATOR.'installer-runtime';
        $releaseCacheRoot = $cacheRoot.DIRECTORY_SEPARATOR.'relay-release';
        $releasePackagePath = $releaseCacheRoot.DIRECTORY_SEPARATOR.'relay-release.zip';
        $releaseManifestPath = $releaseCacheRoot.DIRECTORY_SEPARATOR.'release-manifest.json';
        $releaseFingerprintPath = $releaseCacheRoot.DIRECTORY_SEPARATOR.'release-fingerprint.json';
        $installerPackagePath = $outputRoot.DIRECTORY_SEPARATOR.'installer.zip';
        $bootstrapIndexPath = $outputRoot.DIRECTORY_SEPARATOR.'index.php';
        $htaccessPath = $outputRoot.DIRECTORY_SEPARATOR.'.htaccess';

        $this->ensureDirectory($outputRoot);
        $this->ensureDirectory($cacheRoot);
        $this->resetDirectory($buildRoot);

        $releaseJson = $this->buildReleaseJson($sourceRoot);
        $releaseFingerprint = $this->calculateReleaseFingerprint($sourceRoot, $releaseJson);
        $releaseBuilt = false;
        $releaseReused = false;

        if ($runtimeOnly && ! is_file($releasePackagePath)) {
            throw new RuntimeException('Runtime-only build requires an existing cached relay-release.zip artifact.');
        }

        if (! $runtimeOnly) {
            $cachedFingerprint = $this->readJsonFile($releaseFingerprintPath);
            $releaseCacheMatches = is_file($releasePackagePath)
                && is_file($releaseManifestPath)
                && ($cachedFingerprint['fingerprint'] ?? null) === $releaseFingerprint['fingerprint'];

            if ($forceRelease || ! $releaseCacheMatches) {
                $this->buildReleasePackage($sourceRoot, $releaseCacheRoot, $releasePackagePath, $releaseManifestPath, $releaseJson);
                $this->writeJsonFile($releaseFingerprintPath, $releaseFingerprint);
                $releaseBuilt = true;
            } else {
                $releaseReused = true;
            }
        } else {
            $releaseReused = true;
        }

        if (! $releaseOnly) {
            $this->prepareInstallerRuntime($sourceRoot, $runtimeBuildRoot);
            $this->buildInstallerArchive($runtimeBuildRoot, $releasePackagePath, $releaseManifestPath, $installerPackagePath);
            $this->writeFileAtomically($bootstrapIndexPath, $this->bootstrapEntrypoint());
            $this->writeFileAtomically($htaccessPath, $this->rootHtaccess());
            $this->copyDirectory($sourceRoot.DIRECTORY_SEPARATOR.'installer', $outputRoot.DIRECTORY_SEPARATOR.'installer');
            $this->copyFile(
                $sourceRoot.DIRECTORY_SEPARATOR.'database'.DIRECTORY_SEPARATOR.'schema'.DIRECTORY_SEPARATOR.'mysql-schema.sql',
                $outputRoot.DIRECTORY_SEPARATOR.'database'.DIRECTORY_SEPARATOR.'schema'.DIRECTORY_SEPARATOR.'mysql-schema.sql'
            );
            $this->writeJsonFile($outputRoot.DIRECTORY_SEPARATOR.'release.json', $releaseJson);
            $this->writeChecksums($outputRoot, [
                'index.php',
                '.htaccess',
                'installer.zip',
                'release.json',
                'database/schema/mysql-schema.sql',
            ]);
        }

        $this->deleteDirectory($buildRoot);

        return [
            'output_root' => $outputRoot,
            'bootstrap_index_path' => $bootstrapIndexPath,
            'root_htaccess_path' => $htaccessPath,
            'installer_package_path' => $releaseOnly ? null : $installerPackagePath,
            'kit_release_json_path' => $releaseOnly ? null : $outputRoot.DIRECTORY_SEPARATOR.'release.json',
            'kit_installer_path' => $releaseOnly ? null : $outputRoot.DIRECTORY_SEPARATOR.'installer',
            'checksums_path' => $releaseOnly ? null : $outputRoot.DIRECTORY_SEPARATOR.'checksums.sha256',
            'release_cache_package_path' => $releasePackagePath,
            'release_manifest_path' => $releaseManifestPath,
            'release_fingerprint_path' => $releaseFingerprintPath,
            'release_built' => $releaseBuilt,
            'release_reused' => $releaseReused,
            'release_only' => $releaseOnly,
            'runtime_only' => $runtimeOnly,
        ];
    }

    private function prepareInstallerRuntime(string $sourceRoot, string $targetRoot): void
    {
        $this->copyDirectory($this->runtimeTemplateRoot, $targetRoot);
        $this->copyDirectory(
            $sourceRoot.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'relay-installer',
            $targetRoot.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'relay-installer'
        );
        $this->copyHelperRuntime(
            $sourceRoot.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'helpers.pbb.ph',
            $targetRoot.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'helpers.pbb.ph'
        );

        $this->writeJsonFile(
            $targetRoot.DIRECTORY_SEPARATOR.'manifest.json',
            [
                'installer_version' => (string) config('relay.version.package', '1.1.0'),
                'relay_release_version' => (string) config('relay.version.package', '1.1.0'),
                'minimum_php_version' => (string) config('installer.requirements.php_min', '8.2.0'),
                'requires_extensions' => array_values((array) config('installer.requirements.extensions', [])),
                'has_embedded_relay_release' => true,
                'runtime_type' => 'standalone_php',
            ]
        );
    }

    private function buildReleasePackage(
        string $sourceRoot,
        string $releaseCacheRoot,
        string $packagePath,
        string $manifestPath,
        array $releaseJson,
    ): void {
        $this->resetDirectory($releaseCacheRoot);
        $packagingStageRoot = $releaseCacheRoot.DIRECTORY_SEPARATOR.'.production-stage';
        $releaseSourceRoot = $this->prepareReleasePackagingStage($sourceRoot, $packagingStageRoot, $releaseJson);

        $archive = new ZipArchive();
        $opened = $archive->open($packagePath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        if ($opened !== true) {
            throw new RuntimeException("Release package [$packagePath] could not be created.");
        }

        foreach ($this->releaseDirectories as $directory) {
            $sourceDirectory = $releaseSourceRoot.DIRECTORY_SEPARATOR.$directory;
            if (! is_dir($sourceDirectory)) {
                throw new RuntimeException("Required installer package source directory [$sourceDirectory] was not found.");
            }

            $this->addDirectoryToZip($archive, $sourceDirectory, $directory);
        }

        foreach ($this->releaseFiles as $file) {
            $sourceFile = $releaseSourceRoot.DIRECTORY_SEPARATOR.$file;
            if (! is_file($sourceFile)) {
                throw new RuntimeException("Required installer package source file [$sourceFile] was not found.");
            }

            if ($file === 'release.json') {
                $archive->addFromString('release.json', $this->encodeJson($releaseJson));
                continue;
            }

            $archive->addFile($sourceFile, str_replace('\\', '/', $file));
        }

        $this->addStorageDirectoriesToZip($archive);
        $archive->addFromString('routes/console.php', "<?php\n\n");
        $archive->close();
        $this->deleteDirectory($packagingStageRoot);
        $this->addZipChecksums($packagePath);

        $this->writeJsonFile(
            $manifestPath,
            [
                'release_version' => (string) ($releaseJson['version'] ?? config('relay.version.package', '1.1.0')),
                'display_version' => (string) ($releaseJson['display_version'] ?? ''),
                'build' => $releaseJson['build'] ?? null,
                'database' => $releaseJson['database'] ?? null,
                'app_package' => 'relay-release.zip',
                'app_entrypoint' => '.relay/app/public/index.php',
                'expected_paths' => array_values((array) config('installer.release_expected_paths', [])),
            ]
        );
    }

    private function prepareReleasePackagingStage(string $sourceRoot, string $stageRoot, array $releaseJson): string
    {
        $this->resetDirectory($stageRoot);

        foreach ($this->releaseDirectories as $directory) {
            if ($directory === 'vendor') {
                continue;
            }

            $this->copyDirectory(
                $sourceRoot.DIRECTORY_SEPARATOR.$directory,
                $stageRoot.DIRECTORY_SEPARATOR.$directory
            );
        }

        foreach ($this->releaseFiles as $file) {
            if ($file === 'release.json') {
                $this->writeJsonFile($stageRoot.DIRECTORY_SEPARATOR.'release.json', $releaseJson);
                continue;
            }

            $this->copyFile(
                $sourceRoot.DIRECTORY_SEPARATOR.$file,
                $stageRoot.DIRECTORY_SEPARATOR.$file
            );
        }

        if ($this->shouldInstallProductionComposerDependencies($stageRoot)) {
            $this->installProductionComposerDependencies($stageRoot);
        } else {
            $this->copyDirectory(
                $sourceRoot.DIRECTORY_SEPARATOR.'vendor',
                $stageRoot.DIRECTORY_SEPARATOR.'vendor'
            );
        }

        if (! is_dir($stageRoot.DIRECTORY_SEPARATOR.'vendor')) {
            throw new RuntimeException('Production Composer install did not create a vendor directory.');
        }

        return $stageRoot;
    }

    private function shouldInstallProductionComposerDependencies(string $stageRoot): bool
    {
        $composerJson = $this->readJsonFile($stageRoot.DIRECTORY_SEPARATOR.'composer.json');

        return isset($composerJson['require'])
            || isset($composerJson['require-dev'])
            || isset($composerJson['autoload'])
            || isset($composerJson['scripts']);
    }

    private function installProductionComposerDependencies(string $stageRoot): void
    {
        $composerPhar = $this->resolveComposerPhar();
        $command = implode(' ', [
            escapeshellarg(PHP_BINARY),
            escapeshellarg($composerPhar),
            'install',
            '--no-dev',
            '--optimize-autoloader',
            '--no-interaction',
            '--prefer-dist',
            '--no-progress',
        ]);

        $this->runCommand($command, $stageRoot, 'Composer production dependency install failed');
    }

    private function resolveComposerPhar(): string
    {
        $configured = trim((string) getenv('RELAY_BUILD_COMPOSER_PHAR'));
        if ($configured !== '' && is_file($configured)) {
            return $configured;
        }

        $candidates = [
            'C:\\ProgramData\\ComposerSetup\\bin\\composer.phar',
            dirname((string) (getenv('COMPOSER_HOME') ?: '')).DIRECTORY_SEPARATOR.'composer.phar',
        ];

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        $command = PHP_OS_FAMILY === 'Windows'
            ? 'where composer.phar 2>NUL'
            : 'command -v composer.phar 2>/dev/null';
        $output = [];
        $exitCode = 1;
        @exec($command, $output, $exitCode);

        if ($exitCode === 0 && isset($output[0]) && is_file(trim((string) $output[0]))) {
            return trim((string) $output[0]);
        }

        throw new RuntimeException('composer.phar was not found. Set RELAY_BUILD_COMPOSER_PHAR to build production installer packages.');
    }

    private function runCommand(string $command, string $cwd, string $failureMessage): void
    {
        $stdoutPath = tempnam(sys_get_temp_dir(), 'relay-build-out-');
        $stderrPath = tempnam(sys_get_temp_dir(), 'relay-build-err-');
        if (! is_string($stdoutPath) || ! is_string($stderrPath)) {
            throw new RuntimeException($failureMessage.': temporary output files could not be created.');
        }

        $descriptorSpec = [
            1 => ['file', $stdoutPath, 'w'],
            2 => ['file', $stderrPath, 'w'],
        ];

        $process = proc_open($command, $descriptorSpec, $pipes, $cwd);
        if (! is_resource($process)) {
            @unlink($stdoutPath);
            @unlink($stderrPath);
            throw new RuntimeException($failureMessage.': process could not be started.');
        }

        $exitCode = proc_close($process);
        $stdout = (string) @file_get_contents($stdoutPath);
        $stderr = (string) @file_get_contents($stderrPath);
        @unlink($stdoutPath);
        @unlink($stderrPath);

        if ($exitCode !== 0) {
            $output = trim(implode(PHP_EOL, array_filter([$stdout, $stderr], static fn ($value) => is_string($value) && trim($value) !== '')));
            throw new RuntimeException($failureMessage.($output !== '' ? ': '.$output : '.'));
        }
    }

    private function buildInstallerArchive(
        string $runtimeRoot,
        string $releasePackagePath,
        string $releaseManifestPath,
        string $packagePath,
    ): void {
        if (! is_file($releasePackagePath) || ! is_file($releaseManifestPath)) {
            throw new RuntimeException('Cached release artifact is incomplete. Rebuild the release payload first.');
        }

        $tempPackagePath = $packagePath.'.tmp';
        if (is_file($tempPackagePath)) {
            @unlink($tempPackagePath);
        }

        $archive = new ZipArchive();
        $opened = $archive->open($tempPackagePath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        if ($opened !== true) {
            throw new RuntimeException("Installer package [$packagePath] could not be created.");
        }

        $this->addDirectoryToZip($archive, $runtimeRoot, 'installer-runtime');
        $archive->addFile($releaseManifestPath, 'relay-release/release-manifest.json');
        $archive->addFile($releasePackagePath, 'relay-release/relay-release.zip');
        $archive->close();

        $this->replaceFileAtomically($tempPackagePath, $packagePath);
    }

    /**
     * @return array{fingerprint: string, files: int}
     */
    private function calculateReleaseFingerprint(string $sourceRoot, array $releaseJson): array
    {
        $hashContext = hash_init('sha256');
        $fileCount = 0;

        foreach ($this->releaseDirectories as $directory) {
            $sourceDirectory = $sourceRoot.DIRECTORY_SEPARATOR.$directory;
            if (! is_dir($sourceDirectory)) {
                throw new RuntimeException("Required installer package source directory [$sourceDirectory] was not found.");
            }

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($sourceDirectory, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::SELF_FIRST
            );

            foreach ($iterator as $item) {
                $absolutePath = $item->getPathname();
                $relativePath = $directory.'/'.ltrim(str_replace('\\', '/', substr($absolutePath, strlen($sourceDirectory))), '/');

                if ($this->shouldExcludeRelativePath($relativePath, $item->isDir())) {
                    continue;
                }

                if ($item->isDir()) {
                    hash_update($hashContext, 'dir:'.$relativePath."\n");
                    continue;
                }

                $fileCount++;
                hash_update($hashContext, implode('|', [
                    'file',
                    $relativePath,
                    (string) $item->getMTime(),
                    (string) $item->getSize(),
                ])."\n");
            }
        }

        foreach ($this->releaseFiles as $file) {
            $sourceFile = $sourceRoot.DIRECTORY_SEPARATOR.$file;
            if (! is_file($sourceFile)) {
                throw new RuntimeException("Required installer package source file [$sourceFile] was not found.");
            }

            $fileCount++;
            if ($file === 'release.json') {
                hash_update($hashContext, implode('|', [
                    'file',
                    'release.json',
                    hash('sha256', $this->encodeJson($releaseJson)),
                ])."\n");
                continue;
            }

            hash_update($hashContext, implode('|', [
                'file',
                str_replace('\\', '/', $file),
                (string) filemtime($sourceFile),
                (string) filesize($sourceFile),
            ])."\n");
        }

        foreach ($this->storageDirectories as $directory) {
            hash_update($hashContext, 'storage:'.str_replace('\\', '/', $directory)."\n");
        }

        return [
            'fingerprint' => hash_final($hashContext),
            'files' => $fileCount,
        ];
    }

    private function addStorageDirectoriesToZip(ZipArchive $archive): void
    {
        $archive->addEmptyDir('storage');

        foreach ($this->storageDirectories as $directory) {
            $zipPath = 'storage/'.trim(str_replace('\\', '/', $directory), '/');
            $archive->addEmptyDir($zipPath);
        }
    }

    private function addDirectoryToZip(ZipArchive $archive, string $sourceDirectory, string $zipPrefix): void
    {
        if (! is_dir($sourceDirectory)) {
            throw new RuntimeException("Source directory [$sourceDirectory] was not found for installer packaging.");
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($sourceDirectory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $absolutePath = $item->getPathname();
            $relativePath = ltrim(str_replace('\\', '/', substr($absolutePath, strlen($sourceDirectory))), '/');
            $zipPath = trim($zipPrefix.'/'.ltrim($relativePath, '/'), '/');

            if ($this->shouldExcludeRelativePath($zipPath, $item->isDir())) {
                continue;
            }

            if ($item->isDir()) {
                $archive->addEmptyDir($zipPath);
                continue;
            }

            $archive->addFile($absolutePath, $zipPath);
        }
    }

    private function copyDirectory(string $source, string $target): void
    {
        if (! is_dir($source)) {
            throw new RuntimeException("Required installer package source directory [$source] was not found.");
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $absolutePath = $item->getPathname();
            $relativePath = ltrim(str_replace('\\', '/', substr($absolutePath, strlen($source))), '/');

            if ($this->shouldExcludeRelativePath($relativePath, $item->isDir())) {
                continue;
            }

            $targetPath = $target.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relativePath);

            if ($item->isDir()) {
                $this->ensureDirectory($targetPath);

                continue;
            }

            $this->copyFile($absolutePath, $targetPath);
        }
    }

    private function copyFile(string $source, string $target): void
    {
        if (! is_file($source)) {
            throw new RuntimeException("Required installer package source file [$source] was not found.");
        }

        $directory = dirname($target);
        $this->ensureDirectory($directory);

        if (! copy($source, $target)) {
            throw new RuntimeException("Installer package file [$source] could not be copied to [$target].");
        }
    }

    private function shouldExcludeRelativePath(string $relativePath, bool $isDirectory): bool
    {
        $normalized = trim(str_replace('\\', '/', $relativePath), '/');
        if ($normalized === '') {
            return false;
        }

        $segments = array_values(array_filter(explode('/', $normalized), static fn ($segment) => $segment !== ''));
        if ($segments === []) {
            return false;
        }

        foreach ($segments as $segment) {
            if (str_starts_with($segment, '.git')) {
                return true;
            }

            if (in_array(strtolower($segment), $this->excludedDirectoryNames, true)) {
                return true;
            }
        }

        $basename = strtolower((string) end($segments));

        if (! $isDirectory && str_starts_with($basename, '.git')) {
            return true;
        }

        if (! $isDirectory && in_array($basename, $this->excludedFileNames, true)) {
            return true;
        }

        foreach ($this->releaseExcludedFiles as $excludedPath) {
            if ($normalized === $excludedPath) {
                return true;
            }
        }

        foreach ($this->releaseExcludedDirectoryPrefixes as $excludedPath) {
            if ($normalized === $excludedPath || str_starts_with($normalized, $excludedPath.'/')) {
                return true;
            }
        }

        if (
            str_starts_with($normalized, 'public/vendor/helpers.pbb.ph/js/ui/')
            && $normalized !== 'public/vendor/helpers.pbb.ph/js/ui/ui.loader.js'
        ) {
            return true;
        }

        return false;
    }

    private function copyHelperRuntime(string $source, string $target): void
    {
        if (! is_dir($source)) {
            throw new RuntimeException("Required installer package source directory [$source] was not found.");
        }

        foreach (['dist'] as $directory) {
            $sourceDirectory = $source.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $directory);
            if (is_dir($sourceDirectory)) {
                $this->copyDirectory($sourceDirectory, $target.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $directory));
            }
        }

        $loader = $source.DIRECTORY_SEPARATOR.'js'.DIRECTORY_SEPARATOR.'ui'.DIRECTORY_SEPARATOR.'ui.loader.js';
        if (is_file($loader)) {
            $this->copyFile($loader, $target.DIRECTORY_SEPARATOR.'js'.DIRECTORY_SEPARATOR.'ui'.DIRECTORY_SEPARATOR.'ui.loader.js');
        }

        foreach (glob($source.DIRECTORY_SEPARATOR.'boot.*.json') ?: [] as $sourceFile) {
            if (is_file($sourceFile)) {
                $this->copyFile($sourceFile, $target.DIRECTORY_SEPARATOR.basename($sourceFile));
            }
        }

        $vendoredFile = $source.DIRECTORY_SEPARATOR.'VENDORED.md';
        if (is_file($vendoredFile)) {
            $this->copyFile($vendoredFile, $target.DIRECTORY_SEPARATOR.'VENDORED.md');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function buildReleaseJson(string $sourceRoot): array
    {
        $releaseJson = $this->readJsonFile($sourceRoot.DIRECTORY_SEPARATOR.'release.json');
        $app = (string) ($releaseJson['app'] ?? 'pbb-relay');
        $repository = (string) ($releaseJson['repository'] ?? self::OFFICIAL_REPOSITORY_URL);
        $milestone = (int) ($releaseJson['milestone'] ?? 1);
        $version = (string) ($releaseJson['version'] ?? config('relay.version.package', '1.1.0'));
        $builtAt = new \DateTimeImmutable('now', new \DateTimeZone('Asia/Manila'));
        $timestamp = $builtAt->format('Ymd.His');
        $gitCommit = $this->resolveGitCommit($sourceRoot, $repository);

        $releaseJson['repository'] = $repository;
        $releaseJson['milestone'] = $milestone;
        $releaseJson['version'] = $version;
        $releaseJson['display_version'] = sprintf('v%d-%s', $milestone, $version);
        $releaseJson['installer'] = is_array($releaseJson['installer'] ?? null) ? $releaseJson['installer'] : [];
        $releaseJson['installer']['database'] = [
            'fresh_install_strategy' => 'baseline_schema',
            'baseline_schema' => [
                'path' => 'database/schema/mysql-schema.sql',
                'engine' => 'mysql',
                'generated_at' => $builtAt->format(\DateTimeInterface::ATOM),
                'source' => 'current-release-schema',
                'migration_count' => $this->countMigrationFiles($sourceRoot),
                'sha256' => $this->baselineSchemaHash($sourceRoot),
            ],
            'upgrade_strategy' => 'laravel_migrations',
        ];
        $releaseJson['installer']['web_server'] = [
            'owner' => 'kit',
            'requirements' => [],
            'notes' => 'Relay has no app-specific websocket or reverse-proxy route requirements. Kit owns final vhost/include rendering and standard Laravel document-root/rewrite handling.',
        ];
        $releaseJson['build'] = [
            'version' => $version,
            'id' => sprintf('%s-m%d-%s-%s', $app, $milestone, $version, $timestamp),
            'built_at' => $builtAt->format(\DateTimeInterface::ATOM),
            'git_commit' => $gitCommit,
            'repository' => $repository,
            'builder' => self::class,
        ];
        $releaseJson['update'] = array_merge([
            'contract_version' => 1,
            'channel' => 'testing',
            'immutable_release' => false,
            'from_versions' => [$version],
            'compatibility' => 'same-version-rebuild',
            'requires_database_migration' => false,
            'requires_data_prep_rerun' => true,
            'requires_service_restart' => true,
            'rollback_supported' => true,
        ], is_array($releaseJson['update'] ?? null) ? $releaseJson['update'] : []);
        $releaseJson['database'] = [
            'baseline_schema' => [
                'driver' => 'mysql',
                'path' => 'database/schema/mysql-schema.sql',
                'migration_count' => $this->countMigrationFiles($sourceRoot),
                'sha256' => $this->baselineSchemaHash($sourceRoot),
                'generated_from' => 'current release schema',
            ],
        ];

        return $releaseJson;
    }

    private function addZipChecksums(string $packagePath): void
    {
        $archive = new ZipArchive();
        $opened = $archive->open($packagePath);

        if ($opened !== true) {
            throw new RuntimeException("Release package [$packagePath] could not be reopened for checksums.");
        }

        $archive->addFromString('checksums.sha256', $this->zipChecksums($archive));
        $archive->close();
    }

    private function zipChecksums(ZipArchive $archive): string
    {
        $lines = [];

        for ($index = 0, $count = $archive->numFiles; $index < $count; $index++) {
            $stat = $archive->statIndex($index);
            if (! is_array($stat)) {
                continue;
            }

            $name = (string) ($stat['name'] ?? '');
            if ($name === '' || str_ends_with($name, '/')) {
                continue;
            }

            $contents = $archive->getFromIndex($index);
            if (! is_string($contents)) {
                continue;
            }

            $lines[] = hash('sha256', $contents).'  '.$name;
        }

        sort($lines, SORT_STRING);

        return implode(PHP_EOL, $lines).PHP_EOL;
    }

    private function countMigrationFiles(string $sourceRoot): int
    {
        $files = glob($sourceRoot.DIRECTORY_SEPARATOR.'database'.DIRECTORY_SEPARATOR.'migrations'.DIRECTORY_SEPARATOR.'*.php');

        return is_array($files) ? count($files) : 0;
    }

    private function baselineSchemaHash(string $sourceRoot): ?string
    {
        $schemaPath = $sourceRoot.DIRECTORY_SEPARATOR.'database'.DIRECTORY_SEPARATOR.'schema'.DIRECTORY_SEPARATOR.'mysql-schema.sql';

        return is_file($schemaPath) ? hash_file('sha256', $schemaPath) : null;
    }

    private function writeJsonFile(string $path, array $payload): void
    {
        $json = $this->encodeJson($payload);
        $this->writeFileAtomically($path, $json);
    }

    private function encodeJson(array $payload): string
    {
        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (! is_string($json)) {
            throw new RuntimeException('JSON encoding failed.');
        }

        return $json.PHP_EOL;
    }

    private function resolveGitCommit(string $sourceRoot, string $repository): ?string
    {
        $environmentCommit = trim((string) getenv('RELAY_BUILD_GIT_COMMIT'));
        if ($environmentCommit !== '') {
            return $environmentCommit;
        }

        if (! is_dir($sourceRoot.DIRECTORY_SEPARATOR.'.git')) {
            return $this->resolveRemoteGitCommit($repository);
        }

        $command = 'git -C '.escapeshellarg($sourceRoot).' rev-parse --short HEAD 2>NUL';
        $output = [];
        $exitCode = 1;
        @exec($command, $output, $exitCode);

        if ($exitCode !== 0 || ! isset($output[0])) {
            return null;
        }

        $commit = trim((string) $output[0]);

        return $commit !== '' ? $commit : null;
    }

    private function resolveRemoteGitCommit(string $repository): ?string
    {
        $repository = trim($repository);
        if ($repository === '') {
            return null;
        }

        $command = 'git ls-remote '.escapeshellarg($repository).' HEAD 2>NUL';
        $output = [];
        $exitCode = 1;
        @exec($command, $output, $exitCode);

        if ($exitCode !== 0 || ! isset($output[0])) {
            return null;
        }

        $parts = preg_split('/\s+/', trim((string) $output[0]));
        $commit = is_array($parts) ? trim((string) ($parts[0] ?? '')) : '';

        return $commit !== '' ? $commit : null;
    }

    /**
     * @param  list<string>  $relativePaths
     */
    private function writeChecksums(string $root, array $relativePaths): void
    {
        $lines = [];

        foreach ($relativePaths as $relativePath) {
            $path = $root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
            if (! is_file($path)) {
                continue;
            }

            $lines[] = hash_file('sha256', $path).'  '.str_replace('\\', '/', $relativePath);
        }

        $this->writeFileAtomically(
            $root.DIRECTORY_SEPARATOR.'checksums.sha256',
            implode(PHP_EOL, $lines).PHP_EOL
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function readJsonFile(string $path): array
    {
        if (! is_file($path)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? $decoded : [];
    }

    private function writeFileAtomically(string $path, string $contents): void
    {
        $directory = dirname($path);
        $this->ensureDirectory($directory);

        $tempPath = $path.'.tmp';
        if (file_put_contents($tempPath, $contents) === false) {
            throw new RuntimeException("File [$path] could not be written.");
        }

        $this->replaceFileAtomically($tempPath, $path);
    }

    private function replaceFileAtomically(string $tempPath, string $targetPath): void
    {
        if (is_file($targetPath) && ! @unlink($targetPath)) {
            throw new RuntimeException("Existing file [$targetPath] could not be replaced.");
        }

        if (@rename($tempPath, $targetPath)) {
            return;
        }

        if (@copy($tempPath, $targetPath)) {
            @unlink($tempPath);
            return;
        }

        throw new RuntimeException("Temporary file [$tempPath] could not be moved to [$targetPath].");
    }

    private function bootstrapEntrypoint(): string
    {
        return <<<'PHP'
<?php

if (! defined('LARAVEL_START')) {
    define('LARAVEL_START', microtime(true));
}

$installRoot = __DIR__;
$installerLock = $installRoot.'/.relay-installed.lock';
$installerZip = $installRoot.'/installer.zip';
$installerRoot = $installRoot.'/.installer';
$installerRuntimeRoot = $installerRoot.'/runtime';
$installerRuntimeEntrypoint = $installerRuntimeRoot.'/installer-runtime/public/index.php';
$installerRuntimePublicRoot = $installerRuntimeRoot.'/installer-runtime/public';
$installedAppEntrypoint = $installRoot.'/.relay/app/public/index.php';
$installedAppPublicRoot = $installRoot.'/.relay/app/public';
$requestPath = rawurldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
$isInstallerRequest = $requestPath === '/install'
    || str_starts_with($requestPath, '/install/');

$renderBootstrapError = static function (string $title, string $detail, int $status = 500): never {
    http_response_code($status);
    header('Content-Type: text/html; charset=utf-8');

    $safeTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
    $safeDetail = htmlspecialchars($detail, ENT_QUOTES, 'UTF-8');

    echo <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>{$safeTitle}</title>
  <style>
    body { margin: 0; background: #101418; color: #e8eef4; font: 16px/1.5 system-ui, sans-serif; }
    main { max-width: 720px; margin: 8vh auto; padding: 32px; }
    .panel { background: #182028; border: 1px solid #2d3946; border-radius: 16px; padding: 24px; }
    h1 { margin: 0 0 12px; font-size: 24px; }
    p { margin: 0; color: #b8c6d4; white-space: pre-wrap; }
  </style>
</head>
<body>
  <main>
    <section class="panel">
      <h1>{$safeTitle}</h1>
      <p>{$safeDetail}</p>
    </section>
  </main>
</body>
</html>
HTML;
    exit;
};

$relativeRequestPath = ltrim($requestPath, '/');
$serveStaticFile = static function (string $path): never {
    $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    $contentTypes = [
        'css' => 'text/css; charset=utf-8',
        'js' => 'application/javascript; charset=utf-8',
        'json' => 'application/json; charset=utf-8',
        'map' => 'application/json; charset=utf-8',
        'svg' => 'image/svg+xml',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'woff' => 'font/woff',
        'woff2' => 'font/woff2',
    ];

    header('Content-Type: '.($contentTypes[$extension] ?? 'application/octet-stream'));
    readfile($path);
    exit;
};

if ($relativeRequestPath !== '' && strpos($relativeRequestPath, '..') === false) {
    $normalizedRequestPath = str_replace('/', DIRECTORY_SEPARATOR, $relativeRequestPath);
    $installerAsset = $installerRuntimePublicRoot.DIRECTORY_SEPARATOR.$normalizedRequestPath;
    if (is_file($installerAsset) && ($isInstallerRequest || ! file_exists($installerLock))) {
        $serveStaticFile($installerAsset);
    }

    $installedAsset = $installedAppPublicRoot.DIRECTORY_SEPARATOR.$normalizedRequestPath;
    if (file_exists($installerLock) && is_file($installedAsset)) {
        $serveStaticFile($installedAsset);
    }
}

if ($isInstallerRequest && file_exists($installerRuntimeEntrypoint)) {
    require $installerRuntimeEntrypoint;
    return;
}

if (file_exists($installerLock) && file_exists($installedAppEntrypoint)) {
    require $installedAppEntrypoint;
    return;
}

if (! file_exists($installerLock)) {
    if (! file_exists($installerRuntimeEntrypoint)) {
        if (! file_exists($installerZip)) {
            $renderBootstrapError('Relay Installer Package Missing', 'Fresh install mode expected installer.zip in the install root, but the file was not found.');
        }

        if (! class_exists(ZipArchive::class)) {
            $renderBootstrapError('ZIP Support Required', 'The PHP ZipArchive extension is required before the Relay installer can extract its runtime package.');
        }

        if (! is_dir($installerRuntimeRoot) && ! mkdir($installerRuntimeRoot, 0777, true) && ! is_dir($installerRuntimeRoot)) {
            $renderBootstrapError('Installer Runtime Path Unavailable', 'The Relay installer could not create its working runtime directory under .installer/runtime.');
        }

        $archive = new ZipArchive();
        $opened = $archive->open($installerZip);

        if ($opened !== true) {
            $renderBootstrapError('Installer Package Open Failed', 'The Relay installer package could not be opened for extraction.');
        }

        if (! $archive->extractTo($installerRuntimeRoot)) {
            $archive->close();
            $renderBootstrapError('Installer Package Extraction Failed', 'The Relay installer package could not be extracted into the working runtime directory.');
        }

        $archive->close();
    }

    if (file_exists($installerRuntimeEntrypoint)) {
        require $installerRuntimeEntrypoint;
        return;
    }

    $renderBootstrapError('Installer Runtime Missing', 'Relay could not locate installer-runtime/public/index.php after extraction. The installer package may be incomplete.');
}

if (file_exists($installerLock) && ! file_exists($installedAppEntrypoint)) {
    $renderBootstrapError('Installed Relay Runtime Missing', 'Relay is marked installed, but the deployed application entrypoint was not found at .relay/app/public/index.php.');
}
PHP;
    }

    private function rootHtaccess(): string
    {
        return <<<'HTACCESS'
<IfModule mod_rewrite.c>
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^ index.php [L]
</IfModule>
HTACCESS;
    }

    private function ensureDirectory(string $directory): void
    {
        if (! is_dir($directory) && ! mkdir($directory, 0777, true) && ! is_dir($directory)) {
            throw new RuntimeException("Installer package directory [$directory] could not be created.");
        }
    }

    private function resetDirectory(string $directory): void
    {
        if (is_dir($directory)) {
            $this->deleteDirectory($directory);
        }

        $this->ensureDirectory($directory);
    }

    private function deleteDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            $path = $item->getPathname();

            if ($item->isDir()) {
                @rmdir($path);
                continue;
            }

            if (file_exists($path)) {
                @chmod($path, 0777);
                @unlink($path);
            }
        }

        @rmdir($directory);
    }

    private function sourceRoot(): string
    {
        return $this->normalizePath($this->sourceRoot ?? base_path());
    }

    private function normalizePath(string $path): string
    {
        return rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path), DIRECTORY_SEPARATOR);
    }
}
