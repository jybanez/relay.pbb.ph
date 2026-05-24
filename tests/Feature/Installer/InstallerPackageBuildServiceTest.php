<?php

namespace Tests\Feature\Installer;

use App\Installer\InstallerPackageBuildService;
use Illuminate\Support\Facades\File;
use Tests\TestCase;
use ZipArchive;

class InstallerPackageBuildServiceTest extends TestCase
{
    private string $sourceRoot;
    private string $outputRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sourceRoot = storage_path('framework/testing/installer-package-source');
        $this->outputRoot = storage_path('framework/testing/installer-package-output');

        File::deleteDirectory($this->sourceRoot);
        File::deleteDirectory($this->outputRoot);

        putenv('RELAY_BUILD_GIT_COMMIT=test-build-commit');
        $this->seedSourceTree($this->sourceRoot);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->sourceRoot);
        File::deleteDirectory($this->outputRoot);
        putenv('RELAY_BUILD_GIT_COMMIT');

        parent::tearDown();
    }

    public function test_builder_emits_bootstrap_index_and_installer_archive(): void
    {
        if (! class_exists(ZipArchive::class)) {
            $this->markTestSkipped('ZipArchive is required for installer package build tests.');
        }

        $builder = new InstallerPackageBuildService($this->sourceRoot);
        $result = $builder->build($this->outputRoot);

        $this->assertSame(
            $this->normalizePath($this->outputRoot),
            $this->normalizePath($result['output_root'])
        );
        $this->assertFileExists($this->outputRoot.DIRECTORY_SEPARATOR.'index.php');
        $this->assertFileExists($this->outputRoot.DIRECTORY_SEPARATOR.'.htaccess');
        $this->assertFileExists($this->outputRoot.DIRECTORY_SEPARATOR.'installer.zip');
        $this->assertFileExists($this->outputRoot.DIRECTORY_SEPARATOR.'release.json');
        $this->assertFileExists($this->outputRoot.DIRECTORY_SEPARATOR.'database'.DIRECTORY_SEPARATOR.'schema'.DIRECTORY_SEPARATOR.'mysql-schema.sql');
        $this->assertFileExists($this->outputRoot.DIRECTORY_SEPARATOR.'checksums.sha256');
        $this->assertFileExists($this->outputRoot.DIRECTORY_SEPARATOR.'installer'.DIRECTORY_SEPARATOR.'install-run.php');
        $this->assertDirectoryDoesNotExist($this->outputRoot.DIRECTORY_SEPARATOR.'.build');
        $this->assertFileExists($result['release_cache_package_path']);
        $this->assertFileExists($result['release_manifest_path']);
        $this->assertFileExists($result['release_fingerprint_path']);
        $this->assertTrue($result['release_built']);
        $this->assertFalse($result['release_reused']);

        $outputReleaseJson = json_decode((string) file_get_contents($this->outputRoot.DIRECTORY_SEPARATOR.'release.json'), true);
        $this->assertSame(1, $outputReleaseJson['milestone'] ?? null);
        $this->assertSame('1.1.0', $outputReleaseJson['version'] ?? null);
        $this->assertSame('v1-1.1.0', $outputReleaseJson['display_version'] ?? null);
        $this->assertSame('1.1.0', $outputReleaseJson['build']['version'] ?? null);
        $this->assertIsString($outputReleaseJson['build']['id'] ?? null);
        $this->assertIsString($outputReleaseJson['build']['built_at'] ?? null);
        $this->assertSame('https://github.com/jybanez/relay.pbb.ph', $outputReleaseJson['repository'] ?? null);
        $this->assertSame('https://github.com/jybanez/relay.pbb.ph', $outputReleaseJson['build']['repository'] ?? null);
        $this->assertSame('test-build-commit', $outputReleaseJson['build']['git_commit'] ?? null);
        $this->assertSame(InstallerPackageBuildService::class, $outputReleaseJson['build']['builder'] ?? null);
        $this->assertSame('baseline_schema', $outputReleaseJson['installer']['database']['fresh_install_strategy'] ?? null);
        $this->assertSame(1, $outputReleaseJson['update']['contract_version'] ?? null);
        $this->assertSame('testing', $outputReleaseJson['update']['channel'] ?? null);
        $this->assertSame(false, $outputReleaseJson['update']['immutable_release'] ?? null);
        $this->assertSame('same-version-rebuild', $outputReleaseJson['update']['compatibility'] ?? null);
        $this->assertSame(true, $outputReleaseJson['update']['requires_data_prep_rerun'] ?? null);
        $this->assertSame(true, $outputReleaseJson['update']['requires_service_restart'] ?? null);
        $this->assertSame('database/schema/mysql-schema.sql', $outputReleaseJson['installer']['database']['baseline_schema']['path'] ?? null);
        $this->assertSame('mysql', $outputReleaseJson['installer']['database']['baseline_schema']['engine'] ?? null);
        $this->assertSame('current-release-schema', $outputReleaseJson['installer']['database']['baseline_schema']['source'] ?? null);
        $this->assertSame('laravel_migrations', $outputReleaseJson['installer']['database']['upgrade_strategy'] ?? null);
        $this->assertSame(2, $outputReleaseJson['installer']['database']['baseline_schema']['migration_count'] ?? null);
        $this->assertIsString($outputReleaseJson['installer']['database']['baseline_schema']['generated_at'] ?? null);
        $this->assertIsString($outputReleaseJson['installer']['database']['baseline_schema']['sha256'] ?? null);
        $this->assertSame('kit', $outputReleaseJson['installer']['web_server']['owner'] ?? null);
        $this->assertSame([], $outputReleaseJson['installer']['web_server']['requirements'] ?? null);
        $this->assertSame(false, $outputReleaseJson['data_prep']['capabilities']['prepare_data'] ?? null);
        $this->assertSame(true, $outputReleaseJson['data_prep']['capabilities']['apply_settings'] ?? null);
        $this->assertSame('tools/data-prep/apply-settings.php', $outputReleaseJson['data_prep']['tools']['apply_settings']['path'] ?? null);
        $this->assertSame('pbb-relay-worker', $outputReleaseJson['runtime_services'][0]['id'] ?? null);
        $this->assertSame('background_process', $outputReleaseJson['runtime_services'][0]['type'] ?? null);
        $this->assertSame(true, $outputReleaseJson['runtime_services'][0]['required'] ?? null);
        $this->assertSame(true, $outputReleaseJson['runtime_services'][0]['required_for_smoke'] ?? null);
        $this->assertSame('kit', $outputReleaseJson['runtime_services'][0]['manager'] ?? null);
        $this->assertSame('{runtime.php_binary}', $outputReleaseJson['runtime_services'][0]['command'] ?? null);
        $this->assertSame(['artisan', 'queue:work', '--queue=relay-deliveries,relay-handlers'], $outputReleaseJson['runtime_services'][0]['args'] ?? null);
        $this->assertSame('process', $outputReleaseJson['runtime_services'][0]['health_check']['type'] ?? null);
        $this->assertStringContainsString(
            'database/schema/mysql-schema.sql',
            (string) file_get_contents($this->outputRoot.DIRECTORY_SEPARATOR.'checksums.sha256')
        );

        $installerZip = new ZipArchive();
        $this->assertTrue($installerZip->open($this->outputRoot.DIRECTORY_SEPARATOR.'installer.zip') === true);

        $this->assertNotFalse($installerZip->locateName('installer-runtime/manifest.json'));
        $this->assertNotFalse($installerZip->locateName('installer-runtime/public/index.php'));
        $this->assertNotFalse($installerZip->locateName('installer-runtime/src/RelayInstallerRuntime.php'));
        $this->assertNotFalse($installerZip->locateName('installer-runtime/views/shell.php'));
        $this->assertNotFalse($installerZip->locateName('installer-runtime/public/relay-installer/installer.js'));
        $this->assertNotFalse($installerZip->locateName('installer-runtime/public/vendor/helpers.pbb.ph/boot.alert.levels.json'));
        $this->assertNotFalse($installerZip->locateName('installer-runtime/public/vendor/helpers.pbb.ph/dist/helpers.ui.bundle.min.css'));
        $this->assertNotFalse($installerZip->locateName('installer-runtime/public/vendor/helpers.pbb.ph/dist/helpers.ui.bundle.min.js'));
        $this->assertNotFalse($installerZip->locateName('installer-runtime/public/vendor/helpers.pbb.ph/js/ui/ui.loader.js'));
        $this->assertNotFalse($installerZip->locateName('installer-runtime/public/vendor/helpers.pbb.ph/VENDORED.md'));
        $this->assertFalse($installerZip->locateName('installer-runtime/public/vendor/helpers.pbb.ph/css/ui/ui.tokens.css') !== false);
        $this->assertFalse($installerZip->locateName('installer-runtime/public/vendor/helpers.pbb.ph/docs/readme.md') !== false);
        $this->assertFalse($installerZip->locateName('installer-runtime/public/vendor/helpers.pbb.ph/css/incident/incident.css') !== false);
        $this->assertFalse($installerZip->locateName('installer-runtime/public/vendor/helpers.pbb.ph/js/demo/demo.shell.js') !== false);
        $this->assertFalse($installerZip->locateName('installer-runtime/public/vendor/helpers.pbb.ph/js/incident/incident.base.js') !== false);
        $this->assertFalse($installerZip->locateName('installer-runtime/public/vendor/helpers.pbb.ph/js/vendor/vendor.keep.js') !== false);
        $this->assertFalse($installerZip->locateName('installer-runtime/public/vendor/helpers.pbb.ph/README.upstream.md') !== false);
        $this->assertFalse($installerZip->locateName('installer-runtime/public/vendor/helpers.pbb.ph/.gitignore') !== false);
        $this->assertNotFalse($installerZip->locateName('relay-release/release-manifest.json'));
        $this->assertNotFalse($installerZip->locateName('relay-release/relay-release.zip'));

        $runtimeManifest = $installerZip->getFromName('installer-runtime/manifest.json');
        $this->assertIsString($runtimeManifest);
        $this->assertStringContainsString('"runtime_type": "standalone_php"', $runtimeManifest);

        $embeddedRelease = $installerZip->getFromName('relay-release/relay-release.zip');
        $this->assertIsString($embeddedRelease);

        $tempRelease = storage_path('framework/testing/installer-package-release.zip');
        File::put($tempRelease, $embeddedRelease);

        $releaseZip = new ZipArchive();
        $this->assertTrue($releaseZip->open($tempRelease) === true);
        $this->assertNotFalse($releaseZip->locateName('app/.keep'));
        $this->assertFalse($releaseZip->locateName('installer/index.php') !== false);
        $this->assertNotFalse($releaseZip->locateName('installer/install-run.php'));
        $this->assertNotFalse($releaseZip->locateName('composer.json'));
        $this->assertNotFalse($releaseZip->locateName('release.json'));
        $this->assertNotFalse($releaseZip->locateName('checksums.sha256'));
        $this->assertNotFalse($releaseZip->locateName('database/schema/mysql-schema.sql'));
        $this->assertNotFalse($releaseZip->locateName('tools/data-prep/apply-settings.php'));
        $this->assertNotFalse($releaseZip->locateName('tools/data-prep/verify.php'));
        $embeddedReleaseJson = json_decode((string) $releaseZip->getFromName('release.json'), true);
        $this->assertSame($outputReleaseJson['display_version'], $embeddedReleaseJson['display_version'] ?? null);
        $this->assertSame($outputReleaseJson['build']['id'], $embeddedReleaseJson['build']['id'] ?? null);
        $this->assertSame('pbb-relay-worker', $embeddedReleaseJson['runtime_services'][0]['id'] ?? null);
        $this->assertSame('process', $embeddedReleaseJson['runtime_services'][0]['health_check']['type'] ?? null);
        $this->assertStringContainsString('database/schema/mysql-schema.sql', (string) $releaseZip->getFromName('checksums.sha256'));
        $this->assertNotFalse($releaseZip->locateName('vendor/.keep'));
        $this->assertNotFalse($releaseZip->locateName('public/.keep'));
        $this->assertFalse($releaseZip->locateName('app/Console/Commands/BuildRelayInstallerPackageCommand.php') !== false);
        $this->assertFalse($releaseZip->locateName('app/Console/Commands/CreateRelayUserCommand.php') !== false);
        $this->assertNotFalse($releaseZip->locateName('app/Console/Commands/RelayHqSyncCommand.php'));
        $this->assertFalse($releaseZip->locateName('app/Installer/InstallerPackageBuildService.php') !== false);
        $this->assertFalse($releaseZip->locateName('database/seeders/DatabaseSeeder.php') !== false);
        $this->assertSame("<?php\n\n", $releaseZip->getFromName('routes/console.php'));
        $this->assertFalse($releaseZip->locateName('docs/leak.md') !== false);
        $this->assertFalse($releaseZip->locateName('installer-runtime-template/public/index.php') !== false);
        $this->assertFalse($releaseZip->locateName('sdk/README.md') !== false);
        $this->assertFalse($releaseZip->locateName('tests/Feature/LeakTest.php') !== false);
        $this->assertFalse($releaseZip->locateName('.editorconfig') !== false);
        $this->assertFalse($releaseZip->locateName('.env') !== false);
        $this->assertFalse($releaseZip->locateName('.env.relay_a') !== false);
        $this->assertFalse($releaseZip->locateName('.env.relay_b') !== false);
        $this->assertFalse($releaseZip->locateName('.gitattributes') !== false);
        $this->assertFalse($releaseZip->locateName('.gitignore') !== false);
        $this->assertFalse($releaseZip->locateName('.phpunit.result.cache') !== false);
        $this->assertFalse($releaseZip->locateName('package.json') !== false);
        $this->assertFalse($releaseZip->locateName('pbb.ph.crt') !== false);
        $this->assertFalse($releaseZip->locateName('pbb.ph.key') !== false);
        $this->assertFalse($releaseZip->locateName('PHASE1_IMPLEMENTATION.md') !== false);
        $this->assertFalse($releaseZip->locateName('PHASE1_SITREP.md') !== false);
        $this->assertFalse($releaseZip->locateName('phpunit.xml') !== false);
        $this->assertFalse($releaseZip->locateName('tmp_maestro_heartbeat.json') !== false);
        $this->assertFalse($releaseZip->locateName('tmp_ui.form.modal.upstream.js') !== false);
        $this->assertFalse($releaseZip->locateName('vite.config.js') !== false);
        $this->assertFalse($releaseZip->locateName('public/relay-installer/installer.js') !== false);
        $this->assertFalse($releaseZip->locateName('public/relay-installer/installer.css') !== false);
        $this->assertFalse($releaseZip->locateName('public/vendor/helpers.pbb.ph/docs/readme.md') !== false);
        $this->assertNotFalse($releaseZip->locateName('public/vendor/helpers.pbb.ph/dist/helpers.ui.bundle.min.js'));
        $this->assertNotFalse($releaseZip->locateName('public/vendor/helpers.pbb.ph/js/ui/ui.loader.js'));
        $this->assertFalse($releaseZip->locateName('public/vendor/helpers.pbb.ph/css/ui/ui.tokens.css') !== false);
        $this->assertFalse($releaseZip->locateName('public/vendor/helpers.pbb.ph/css/incident/incident.css') !== false);
        $this->assertFalse($releaseZip->locateName('public/vendor/helpers.pbb.ph/js/demo/demo.shell.js') !== false);
        $this->assertFalse($releaseZip->locateName('public/vendor/helpers.pbb.ph/js/ui/ui.modal.js') !== false);
        $this->assertFalse($releaseZip->locateName('public/vendor/helpers.pbb.ph/js/incident/incident.base.js') !== false);
        $this->assertFalse($releaseZip->locateName('public/vendor/helpers.pbb.ph/js/vendor/vendor.keep.js') !== false);
        $this->assertFalse($releaseZip->locateName('public/vendor/helpers.pbb.ph/README.upstream.md') !== false);
        $this->assertFalse($releaseZip->locateName('resources/vendor/helpers.pbb.ph/js/ui/ui.loader.js') !== false);
        $this->assertFalse($releaseZip->locateName('public/vendor/helpers.pbb.ph/.gitignore') !== false);
        $this->assertFalse($releaseZip->locateName('storage/app/.gitignore') !== false);
        $releaseZip->close();

        File::delete($tempRelease);
        $installerZip->close();
    }

    public function test_runtime_only_build_reuses_cached_release_artifact(): void
    {
        if (! class_exists(ZipArchive::class)) {
            $this->markTestSkipped('ZipArchive is required for installer package build tests.');
        }

        $builder = new InstallerPackageBuildService($this->sourceRoot);
        $first = $builder->build($this->outputRoot);
        $releaseChecksum = hash_file('sha256', $first['release_cache_package_path']);
        $releaseFingerprint = json_decode((string) file_get_contents($first['release_fingerprint_path']), true);

        File::put(
            $this->sourceRoot.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'relay-installer'.DIRECTORY_SEPARATOR.'installer.css',
            'body { color: #fff; }'
        );

        $second = $builder->build($this->outputRoot, ['runtime_only' => true]);

        $this->assertFalse($second['release_built']);
        $this->assertTrue($second['release_reused']);
        $this->assertSame($releaseChecksum, hash_file('sha256', $second['release_cache_package_path']));
        $this->assertSame(
            $releaseFingerprint['fingerprint'] ?? null,
            json_decode((string) file_get_contents($second['release_fingerprint_path']), true)['fingerprint'] ?? null
        );
    }

    public function test_compact_package_extracts_when_release_root_is_install_path(): void
    {
        if (! class_exists(ZipArchive::class)) {
            $this->markTestSkipped('ZipArchive is required for compact installer extraction tests.');
        }

        $root = storage_path('framework/testing/installer-compact-same-path');
        $releaseZipPath = storage_path('framework/testing/installer-compact-release.zip');
        $scriptPath = storage_path('framework/testing/installer-compact-same-path.php');

        File::deleteDirectory($root);
        File::delete($releaseZipPath);
        File::delete($scriptPath);

        File::ensureDirectoryExists($root.DIRECTORY_SEPARATOR.'installer'.DIRECTORY_SEPARATOR.'lib');
        File::copy(
            base_path('installer/lib/RelayKitInstaller.php'),
            $root.DIRECTORY_SEPARATOR.'installer'.DIRECTORY_SEPARATOR.'lib'.DIRECTORY_SEPARATOR.'RelayKitInstaller.php'
        );
        File::put($root.DIRECTORY_SEPARATOR.'release.json', '{"app":"pbb-relay"}');

        $releaseZip = new ZipArchive();
        $this->assertTrue($releaseZip->open($releaseZipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true);
        foreach (['app', 'bootstrap', 'config', 'database', 'public', 'resources', 'routes', 'vendor'] as $directory) {
            $releaseZip->addEmptyDir($directory);
            $releaseZip->addFromString($directory.'/.keep', 'fixture');
        }
        $releaseZip->addFromString('artisan', '#!/usr/bin/env php');
        $releaseZip->addFromString('composer.json', '{"autoload":{"psr-4":{"App\\\\":"app/"}}}');
        $releaseZip->addFromString('composer.lock', '{"packages":[]}');
        $releaseZip->addFromString('.env.example', 'APP_NAME="PBB Relay"');
        $releaseZip->addFromString('routes/console.php', '<?php Artisan::command("relay:test-only", fn () => null);');
        $releaseZip->addFromString('app/Console/Commands/CreateRelayUserCommand.php', '<?php');
        $releaseZip->addFromString('app/Console/Commands/RelayHqSyncCommand.php', '<?php');
        $releaseZip->addFromString('database/seeders/DatabaseSeeder.php', '<?php');
        $releaseZip->addFromString('installer/index.php', '<?php echo "browser installer";');
        $releaseZip->addFromString('public/relay-installer/installer.js', 'console.log("installer");');
        $releaseZip->addFromString('public/vendor/helpers.pbb.ph/docs/readme.md', '# helper docs');
        $releaseZip->addFromString('public/vendor/helpers.pbb.ph/css/ui/ui.tokens.css', ':root{}');
        $releaseZip->addFromString('public/vendor/helpers.pbb.ph/js/ui/ui.loader.js', 'export default {};');
        $releaseZip->addFromString('public/vendor/helpers.pbb.ph/js/ui/ui.modal.js', 'export default {};');
        $releaseZip->addFromString('public/vendor/helpers.pbb.ph/dist/helpers.ui.bundle.min.js', 'console.log("bundle");');
        $releaseZip->addFromString('release.json', '{"app":"pbb-relay","version":"1.1.0"}');
        $releaseZip->close();

        $outerZip = new ZipArchive();
        $this->assertTrue($outerZip->open($root.DIRECTORY_SEPARATOR.'installer.zip', ZipArchive::CREATE | ZipArchive::OVERWRITE) === true);
        $outerZip->addFile($releaseZipPath, 'relay-release/relay-release.zip');
        $outerZip->close();

        File::put($scriptPath, <<<'PHP'
<?php

require $argv[1].DIRECTORY_SEPARATOR.'installer'.DIRECTORY_SEPARATOR.'lib'.DIRECTORY_SEPARATOR.'RelayKitInstaller.php';

$method = new ReflectionMethod(RelayKitInstaller::class, 'copyReleaseToInstallPath');
$method->setAccessible(true);
$method->invoke(null, $argv[1], 'fresh');

$cleanup = new ReflectionMethod(RelayKitInstaller::class, 'prunePostEnvironmentInstallPaths');
$cleanup->setAccessible(true);
$cleanup->invoke(null, $argv[1]);

$snapshot = new ReflectionMethod(RelayKitInstaller::class, 'writeHubSnapshot');
$snapshot->setAccessible(true);
$snapshot->invoke(null, $argv[1], [
    'app' => ['app_url' => 'https://relay.pbb.ph'],
    'relay' => [
        'hub_id' => '072217029',
        'hq_hub_id' => 12,
        'hq_api_base_url' => 'https://hub.pbb.ph',
        'hub' => [
            'id' => 12,
            'relay_hub_id' => '072217029',
            'name' => 'Guadalupe, CEBU CITY, CEBU',
            'deployment' => 'barangay',
            'domain' => 'https://guadalupe-cebu-cebu.pbb.ph',
            'status' => 'active',
            'token' => ['has_token' => true],
            'uplinks' => [[
                'id' => 29,
                'uplink_hub_id' => 11,
                'uplink_type' => 'hierarchy',
                'uplink_domain' => 'cebu-cebu.pbb.ph',
                'priority' => 1,
                'is_primary' => true,
                'hub' => [
                    'id' => 11,
                    'name' => 'CEBU CITY, CEBU',
                    'code' => 'cebu-cebu',
                    'deployment' => 'city',
                    'domain' => 'cebu-cebu.pbb.ph',
                    'status' => 'active',
                    'token' => 'do-not-write',
                ],
            ]],
        ],
    ],
]);

if (! is_file($argv[1].DIRECTORY_SEPARATOR.'artisan')) {
    fwrite(STDERR, "artisan missing\n");
    exit(2);
}

if (! is_file($argv[1].DIRECTORY_SEPARATOR.'composer.json')) {
    fwrite(STDERR, "composer.json missing\n");
    exit(3);
}

if (is_file($argv[1].DIRECTORY_SEPARATOR.'composer.lock')) {
    fwrite(STDERR, "composer.lock was not pruned\n");
    exit(4);
}

if (is_dir($argv[1].DIRECTORY_SEPARATOR.'database'.DIRECTORY_SEPARATOR.'seeders')) {
    fwrite(STDERR, "seeders were not pruned\n");
    exit(5);
}

$hubJson = $argv[1].DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'hub.json';
if (! is_file($hubJson)) {
    fwrite(STDERR, "hub.json missing\n");
    exit(7);
}
$hub = json_decode(file_get_contents($hubJson), true);
if (($hub['hub_id'] ?? null) !== 12 || ($hub['relay_hub_id'] ?? null) !== '072217029' || isset($hub['token'])) {
    fwrite(STDERR, "hub.json payload invalid\n");
    exit(8);
}
if (($hub['uplinks'][0]['hub']['token'] ?? null) !== null) {
    fwrite(STDERR, "hub.json leaked nested token\n");
    exit(9);
}

$console = $argv[1].DIRECTORY_SEPARATOR.'routes'.DIRECTORY_SEPARATOR.'console.php';
if (is_file($console) && file_get_contents($console) !== "<?php\n\n") {
    fwrite(STDERR, "console routes were not sanitized\n");
    exit(6);
}
PHP);

        $command = escapeshellarg(PHP_BINARY).' '.escapeshellarg($scriptPath).' '.escapeshellarg($root).' 2>&1';
        exec($command, $output, $exitCode);

        $this->assertSame(0, $exitCode, implode(PHP_EOL, $output));
        $this->assertFileExists($root.DIRECTORY_SEPARATOR.'artisan');
        $this->assertFileExists($root.DIRECTORY_SEPARATOR.'composer.json');
        $this->assertFileExists($root.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'hub.json');
        $this->assertFileDoesNotExist($root.DIRECTORY_SEPARATOR.'composer.lock');
        $this->assertFileDoesNotExist($root.DIRECTORY_SEPARATOR.'installer.zip');
        $this->assertFileExists($root.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'Console'.DIRECTORY_SEPARATOR.'Commands'.DIRECTORY_SEPARATOR.'RelayHqSyncCommand.php');
        $this->assertFileDoesNotExist($root.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'Console'.DIRECTORY_SEPARATOR.'Commands'.DIRECTORY_SEPARATOR.'CreateRelayUserCommand.php');
        $this->assertDirectoryDoesNotExist($root.DIRECTORY_SEPARATOR.'database'.DIRECTORY_SEPARATOR.'seeders');
        $this->assertFileDoesNotExist($root.DIRECTORY_SEPARATOR.'installer'.DIRECTORY_SEPARATOR.'index.php');
        $this->assertDirectoryDoesNotExist($root.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'relay-installer');
        $this->assertDirectoryDoesNotExist($root.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'helpers.pbb.ph'.DIRECTORY_SEPARATOR.'css');
        $this->assertDirectoryDoesNotExist($root.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'helpers.pbb.ph'.DIRECTORY_SEPARATOR.'docs');
        $this->assertFileExists($root.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'helpers.pbb.ph'.DIRECTORY_SEPARATOR.'js'.DIRECTORY_SEPARATOR.'ui'.DIRECTORY_SEPARATOR.'ui.loader.js');
        $this->assertFileDoesNotExist($root.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'helpers.pbb.ph'.DIRECTORY_SEPARATOR.'js'.DIRECTORY_SEPARATOR.'ui'.DIRECTORY_SEPARATOR.'ui.modal.js');
        $this->assertFileExists($root.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'helpers.pbb.ph'.DIRECTORY_SEPARATOR.'dist'.DIRECTORY_SEPARATOR.'helpers.ui.bundle.min.js');
        $this->assertSame("<?php\n\n", (string) file_get_contents($root.DIRECTORY_SEPARATOR.'routes'.DIRECTORY_SEPARATOR.'console.php'));

        File::deleteDirectory($root);
        File::delete($releaseZipPath);
        File::delete($scriptPath);
    }

    public function test_kit_installer_escapes_windows_paths_in_generated_env(): void
    {
        $root = storage_path('framework/testing/installer-env-windows-path');
        $scriptPath = storage_path('framework/testing/installer-env-windows-path.php');

        File::deleteDirectory($root);
        File::delete($scriptPath);
        File::ensureDirectoryExists($root.DIRECTORY_SEPARATOR.'installer'.DIRECTORY_SEPARATOR.'lib');
        File::copy(
            base_path('installer/lib/RelayKitInstaller.php'),
            $root.DIRECTORY_SEPARATOR.'installer'.DIRECTORY_SEPARATOR.'lib'.DIRECTORY_SEPARATOR.'RelayKitInstaller.php'
        );
        File::put($root.DIRECTORY_SEPARATOR.'.env.example', "APP_NAME=\"PBB Relay\"\nDB_DATABASE=database/database.sqlite\n");

        File::put($scriptPath, <<<'PHP'
<?php

require $argv[1].DIRECTORY_SEPARATOR.'installer'.DIRECTORY_SEPARATOR.'lib'.DIRECTORY_SEPARATOR.'RelayKitInstaller.php';

$method = new ReflectionMethod(RelayKitInstaller::class, 'writeEnvironment');
$method->setAccessible(true);
$method->invoke(null, $argv[1], [
    'mode' => 'fresh',
    'app' => [
        'app_env' => 'production',
        'app_debug' => false,
        'app_url' => 'http://relay.test',
    ],
    'database' => [
        'driver' => 'sqlite',
        'sqlite_path' => 'C:\\Users\\Relay Tester\\AppData\\Local\\relay.sqlite',
    ],
    'relay' => [],
    'dependencies' => [],
    'options' => [
        'overwrite_env' => true,
    ],
]);

$env = file_get_contents($argv[1].DIRECTORY_SEPARATOR.'.env');
if (! is_string($env) || ! str_contains($env, 'DB_DATABASE="C:\\\\Users\\\\Relay Tester\\\\AppData\\\\Local\\\\relay.sqlite"')) {
    fwrite(STDERR, $env ?: 'env missing');
    exit(2);
}
PHP);

        $command = escapeshellarg(PHP_BINARY).' '.escapeshellarg($scriptPath).' '.escapeshellarg($root).' 2>&1';
        exec($command, $output, $exitCode);

        $this->assertSame(0, $exitCode, implode(PHP_EOL, $output));

        File::deleteDirectory($root);
        File::delete($scriptPath);
    }

    private function seedSourceTree(string $root): void
    {
        foreach (['app', 'bootstrap', 'config', 'database', 'public', 'resources', 'routes', 'vendor'] as $directory) {
            File::ensureDirectoryExists($root.DIRECTORY_SEPARATOR.$directory);
            File::put($root.DIRECTORY_SEPARATOR.$directory.DIRECTORY_SEPARATOR.'.keep', 'fixture');
        }
        File::ensureDirectoryExists($root.DIRECTORY_SEPARATOR.'database'.DIRECTORY_SEPARATOR.'migrations');
        File::ensureDirectoryExists($root.DIRECTORY_SEPARATOR.'database'.DIRECTORY_SEPARATOR.'schema');
        File::put($root.DIRECTORY_SEPARATOR.'database'.DIRECTORY_SEPARATOR.'migrations'.DIRECTORY_SEPARATOR.'0001_01_01_000000_create_users_table.php', '<?php');
        File::put($root.DIRECTORY_SEPARATOR.'database'.DIRECTORY_SEPARATOR.'migrations'.DIRECTORY_SEPARATOR.'2026_03_13_000001_create_hub_relay_messages_table.php', '<?php');
        File::put($root.DIRECTORY_SEPARATOR.'database'.DIRECTORY_SEPARATOR.'schema'.DIRECTORY_SEPARATOR.'mysql-schema.sql', 'CREATE TABLE `migrations` (`id` int unsigned NOT NULL AUTO_INCREMENT, `migration` varchar(191) NOT NULL, `batch` int NOT NULL, PRIMARY KEY (`id`));');

        File::ensureDirectoryExists($root.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'Console'.DIRECTORY_SEPARATOR.'Commands');
        File::ensureDirectoryExists($root.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'Installer');
        File::put(
            $root.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'Console'.DIRECTORY_SEPARATOR.'Commands'.DIRECTORY_SEPARATOR.'BuildRelayInstallerPackageCommand.php',
            '<?php class BuildRelayInstallerPackageCommand {}'
        );
        File::put(
            $root.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'Console'.DIRECTORY_SEPARATOR.'Commands'.DIRECTORY_SEPARATOR.'CreateRelayUserCommand.php',
            '<?php class CreateRelayUserCommand {}'
        );
        File::put(
            $root.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'Console'.DIRECTORY_SEPARATOR.'Commands'.DIRECTORY_SEPARATOR.'RelayHqSyncCommand.php',
            '<?php class RelayHqSyncCommand {}'
        );
        File::put(
            $root.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'Installer'.DIRECTORY_SEPARATOR.'InstallerPackageBuildService.php',
            '<?php class InstallerPackageBuildService {}'
        );

        File::ensureDirectoryExists($root.DIRECTORY_SEPARATOR.'docs');
        File::put($root.DIRECTORY_SEPARATOR.'docs'.DIRECTORY_SEPARATOR.'leak.md', '# excluded');
        File::ensureDirectoryExists($root.DIRECTORY_SEPARATOR.'sdk');
        File::put($root.DIRECTORY_SEPARATOR.'sdk'.DIRECTORY_SEPARATOR.'README.md', '# excluded');
        File::ensureDirectoryExists($root.DIRECTORY_SEPARATOR.'tests'.DIRECTORY_SEPARATOR.'Feature');
        File::put($root.DIRECTORY_SEPARATOR.'tests'.DIRECTORY_SEPARATOR.'Feature'.DIRECTORY_SEPARATOR.'LeakTest.php', '<?php');
        File::ensureDirectoryExists($root.DIRECTORY_SEPARATOR.'database'.DIRECTORY_SEPARATOR.'seeders');
        File::put($root.DIRECTORY_SEPARATOR.'database'.DIRECTORY_SEPARATOR.'seeders'.DIRECTORY_SEPARATOR.'DatabaseSeeder.php', '<?php class DatabaseSeeder {}');
        File::put($root.DIRECTORY_SEPARATOR.'routes'.DIRECTORY_SEPARATOR.'console.php', '<?php Artisan::command("relay:maestro-probe", fn () => null);');

        File::ensureDirectoryExists($root.DIRECTORY_SEPARATOR.'installer-runtime-template'.DIRECTORY_SEPARATOR.'public');
        File::ensureDirectoryExists($root.DIRECTORY_SEPARATOR.'installer-runtime-template'.DIRECTORY_SEPARATOR.'src');
        File::ensureDirectoryExists($root.DIRECTORY_SEPARATOR.'installer-runtime-template'.DIRECTORY_SEPARATOR.'views');
        File::put($root.DIRECTORY_SEPARATOR.'installer-runtime-template'.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'index.php', '<?php echo "installer";');
        File::put($root.DIRECTORY_SEPARATOR.'installer-runtime-template'.DIRECTORY_SEPARATOR.'src'.DIRECTORY_SEPARATOR.'RelayInstallerRuntime.php', '<?php class RelayInstallerRuntime {}');
        File::put($root.DIRECTORY_SEPARATOR.'installer-runtime-template'.DIRECTORY_SEPARATOR.'views'.DIRECTORY_SEPARATOR.'shell.php', '<html></html>');

        File::ensureDirectoryExists($root.DIRECTORY_SEPARATOR.'installer'.DIRECTORY_SEPARATOR.'schema');
        File::put($root.DIRECTORY_SEPARATOR.'installer'.DIRECTORY_SEPARATOR.'index.php', '<?php echo "kit installer";');
        File::put($root.DIRECTORY_SEPARATOR.'installer'.DIRECTORY_SEPARATOR.'install-run.php', '<?php echo "kit install";');
        File::put($root.DIRECTORY_SEPARATOR.'installer'.DIRECTORY_SEPARATOR.'status.php', '<?php echo "kit status";');
        File::put($root.DIRECTORY_SEPARATOR.'installer'.DIRECTORY_SEPARATOR.'schema'.DIRECTORY_SEPARATOR.'install.schema.json', '{}');

        File::ensureDirectoryExists($root.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'relay-installer');
        File::put($root.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'relay-installer'.DIRECTORY_SEPARATOR.'installer.js', 'console.log("installer");');
        File::put($root.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'relay-installer'.DIRECTORY_SEPARATOR.'installer.css', 'body{}');
        File::ensureDirectoryExists($root.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'helpers.pbb.ph');
        File::put($root.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'helpers.pbb.ph'.DIRECTORY_SEPARATOR.'boot.alert.levels.json', '{}');
        File::ensureDirectoryExists($root.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'helpers.pbb.ph'.DIRECTORY_SEPARATOR.'css'.DIRECTORY_SEPARATOR.'ui');
        File::put($root.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'helpers.pbb.ph'.DIRECTORY_SEPARATOR.'css'.DIRECTORY_SEPARATOR.'ui'.DIRECTORY_SEPARATOR.'ui.tokens.css', ':root{}');
        File::ensureDirectoryExists($root.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'helpers.pbb.ph'.DIRECTORY_SEPARATOR.'css'.DIRECTORY_SEPARATOR.'incident');
        File::put($root.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'helpers.pbb.ph'.DIRECTORY_SEPARATOR.'css'.DIRECTORY_SEPARATOR.'incident'.DIRECTORY_SEPARATOR.'incident.css', '.incident{}');
        File::ensureDirectoryExists($root.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'helpers.pbb.ph'.DIRECTORY_SEPARATOR.'dist');
        File::put($root.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'helpers.pbb.ph'.DIRECTORY_SEPARATOR.'dist'.DIRECTORY_SEPARATOR.'helpers.ui.bundle.min.js', 'console.log("bundle");');
        File::put($root.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'helpers.pbb.ph'.DIRECTORY_SEPARATOR.'dist'.DIRECTORY_SEPARATOR.'helpers.ui.bundle.min.css', '.ui{}');
        File::ensureDirectoryExists($root.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'helpers.pbb.ph'.DIRECTORY_SEPARATOR.'js'.DIRECTORY_SEPARATOR.'ui');
        File::put($root.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'helpers.pbb.ph'.DIRECTORY_SEPARATOR.'js'.DIRECTORY_SEPARATOR.'ui'.DIRECTORY_SEPARATOR.'ui.loader.js', 'export const uiLoader = {};');
        File::ensureDirectoryExists($root.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'helpers.pbb.ph'.DIRECTORY_SEPARATOR.'js'.DIRECTORY_SEPARATOR.'vendor');
        File::put($root.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'helpers.pbb.ph'.DIRECTORY_SEPARATOR.'js'.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'vendor.keep.js', 'export default {};');
        File::ensureDirectoryExists($root.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'helpers.pbb.ph'.DIRECTORY_SEPARATOR.'js'.DIRECTORY_SEPARATOR.'demo');
        File::put($root.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'helpers.pbb.ph'.DIRECTORY_SEPARATOR.'js'.DIRECTORY_SEPARATOR.'demo'.DIRECTORY_SEPARATOR.'demo.shell.js', 'console.log("demo");');
        File::ensureDirectoryExists($root.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'helpers.pbb.ph'.DIRECTORY_SEPARATOR.'js'.DIRECTORY_SEPARATOR.'incident');
        File::put($root.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'helpers.pbb.ph'.DIRECTORY_SEPARATOR.'js'.DIRECTORY_SEPARATOR.'incident'.DIRECTORY_SEPARATOR.'incident.base.js', 'export default {};');
        File::ensureDirectoryExists($root.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'helpers.pbb.ph'.DIRECTORY_SEPARATOR.'docs');
        File::put($root.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'helpers.pbb.ph'.DIRECTORY_SEPARATOR.'docs'.DIRECTORY_SEPARATOR.'readme.md', '# excluded');
        File::put($root.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'helpers.pbb.ph'.DIRECTORY_SEPARATOR.'README.upstream.md', '# excluded');
        File::put($root.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'helpers.pbb.ph'.DIRECTORY_SEPARATOR.'VENDORED.md', 'vendored from upstream');
        File::put($root.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'helpers.pbb.ph'.DIRECTORY_SEPARATOR.'.gitignore', "*\n");

        File::ensureDirectoryExists($root.DIRECTORY_SEPARATOR.'resources'.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'helpers.pbb.ph'.DIRECTORY_SEPARATOR.'js'.DIRECTORY_SEPARATOR.'ui');
        File::put($root.DIRECTORY_SEPARATOR.'resources'.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'helpers.pbb.ph'.DIRECTORY_SEPARATOR.'js'.DIRECTORY_SEPARATOR.'ui'.DIRECTORY_SEPARATOR.'ui.loader.js', 'export const resourceCopy = {};');

        File::ensureDirectoryExists($root.DIRECTORY_SEPARATOR.'tools'.DIRECTORY_SEPARATOR.'data-prep');
        File::put($root.DIRECTORY_SEPARATOR.'tools'.DIRECTORY_SEPARATOR.'data-prep'.DIRECTORY_SEPARATOR.'apply-settings.php', '<?php');
        File::put($root.DIRECTORY_SEPARATOR.'tools'.DIRECTORY_SEPARATOR.'data-prep'.DIRECTORY_SEPARATOR.'verify.php', '<?php');

        File::put($root.DIRECTORY_SEPARATOR.'artisan', '#!/usr/bin/env php');
        File::put($root.DIRECTORY_SEPARATOR.'composer.json', '{"name":"pbb/relay"}');
        File::put($root.DIRECTORY_SEPARATOR.'composer.lock', '{"packages":[]}');
        File::put($root.DIRECTORY_SEPARATOR.'.editorconfig', 'root=true');
        File::put($root.DIRECTORY_SEPARATOR.'.env', 'APP_KEY=leak');
        File::put($root.DIRECTORY_SEPARATOR.'.env.example', "APP_NAME=\"PBB - Hub Relay Server\"\n");
        File::put($root.DIRECTORY_SEPARATOR.'.env.relay_a', 'APP_NAME=A');
        File::put($root.DIRECTORY_SEPARATOR.'.env.relay_b', 'APP_NAME=B');
        File::put($root.DIRECTORY_SEPARATOR.'.gitattributes', '* text=auto');
        File::put($root.DIRECTORY_SEPARATOR.'.gitignore', "/vendor\n");
        File::put($root.DIRECTORY_SEPARATOR.'.phpunit.result.cache', '{}');
        File::put($root.DIRECTORY_SEPARATOR.'package.json', '{}');
        File::put($root.DIRECTORY_SEPARATOR.'pbb.ph.crt', 'cert');
        File::put($root.DIRECTORY_SEPARATOR.'pbb.ph.key', 'key');
        File::put($root.DIRECTORY_SEPARATOR.'PHASE1_IMPLEMENTATION.md', '# excluded');
        File::put($root.DIRECTORY_SEPARATOR.'PHASE1_SITREP.md', '# excluded');
        File::put($root.DIRECTORY_SEPARATOR.'phpunit.xml', '<phpunit/>');
        File::put($root.DIRECTORY_SEPARATOR.'tmp_maestro_heartbeat.json', '{}');
        File::put($root.DIRECTORY_SEPARATOR.'tmp_ui.form.modal.upstream.js', 'console.log("tmp");');
        File::put($root.DIRECTORY_SEPARATOR.'vite.config.js', 'export default {};');
        File::put($root.DIRECTORY_SEPARATOR.'release.json', '{"app":"pbb-relay","repository":"https://github.com/jybanez/relay.pbb.ph","milestone":1,"version":"1.1.0","display_version":"v1-1.1.0","runtime_services":[{"id":"pbb-relay-worker","name":"PBB Relay Worker","type":"background_process","required":true,"required_for_smoke":true,"manager":"kit","working_directory":"{app.install_path}","command":"{runtime.php_binary}","args":["artisan","queue:work","--queue=relay-deliveries,relay-handlers"],"health_check":{"type":"process","timeout_seconds":3},"logs":{"stdout":"storage/logs/pbb-relay-worker.out.log","stderr":"storage/logs/pbb-relay-worker.err.log"},"notes":"Kit starts and verifies this after Relay install."}],"data_prep":{"version":1,"capabilities":{"prepare_data":false,"apply_settings":true,"verify":true},"tools":{"apply_settings":{"path":"tools/data-prep/apply-settings.php","config_section":"relay.data_prep.apply_settings"},"verify":{"path":"tools/data-prep/verify.php","config_section":"relay.data_prep.verify"}}}}');
    }

    private function normalizePath(string $path): string
    {
        return rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path), DIRECTORY_SEPARATOR);
    }
}
