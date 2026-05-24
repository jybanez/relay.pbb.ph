<?php

declare(strict_types=1);

final class RelayKitInstaller
{
    public const APP_ID = 'pbb-relay';
    public const APP_NAME = 'PBB Relay';
    public const VERSION = '1.1.0';

    public static function releaseRoot(): string
    {
        return dirname(__DIR__, 2);
    }

    public static function parseArgs(array $argv): array
    {
        $options = [
            'config' => null,
            'report' => null,
            'mode' => null,
            'dry_run' => false,
            'no_service_register' => false,
            'verbose' => false,
        ];

        for ($i = 1, $count = count($argv); $i < $count; $i++) {
            $arg = (string) $argv[$i];
            switch ($arg) {
                case '--config':
                    $options['config'] = $argv[++$i] ?? null;
                    break;
                case '--report':
                    $options['report'] = $argv[++$i] ?? null;
                    break;
                case '--mode':
                    $options['mode'] = $argv[++$i] ?? null;
                    break;
                case '--dry-run':
                    $options['dry_run'] = true;
                    break;
                case '--no-service-register':
                    $options['no_service_register'] = true;
                    break;
                case '--verbose':
                    $options['verbose'] = true;
                    break;
                case '--help':
                case '-h':
                    $options['help'] = true;
                    break;
                default:
                    throw new InvalidArgumentException("Unknown argument: {$arg}");
            }
        }

        return $options;
    }

    public static function readJsonFile(string $path): array
    {
        $json = @file_get_contents($path);
        if ($json === false) {
            throw new RuntimeException("Unable to read JSON file [{$path}].");
        }

        $decoded = json_decode($json, true);
        if (! is_array($decoded)) {
            throw new RuntimeException("JSON file [{$path}] is invalid.");
        }

        return $decoded;
    }

    public static function writeJsonFile(string $path, array $payload): void
    {
        $directory = dirname($path);
        if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new RuntimeException("Directory [{$directory}] could not be created.");
        }

        file_put_contents($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);
    }

    public static function normalizeConfig(array $config, ?string $modeOverride = null): array
    {
        $mode = $modeOverride ?: (string) ($config['mode'] ?? 'fresh');
        $config['mode'] = $mode;
        $config['app'] = array_merge([
            'app_env' => 'production',
            'app_debug' => false,
            'public_path' => '',
        ], is_array($config['app'] ?? null) ? $config['app'] : []);
        $config['database'] = array_merge([
            'driver' => 'mysql',
            'host' => '127.0.0.1',
            'port' => 3306,
            'database' => '',
            'username' => '',
            'password' => '',
            'sqlite_path' => '',
        ], is_array($config['database'] ?? null) ? $config['database'] : []);
        $config['admin'] = array_merge([
            'strategy' => 'create_if_missing',
            'overwrite_existing' => false,
            'must_change_password' => false,
            'name' => '',
            'email' => '',
            'password' => '',
        ], is_array($config['admin'] ?? null) ? $config['admin'] : []);
        $config['relay'] = array_merge([
            'hub_id' => '',
            'hq_hub_id' => '',
            'hq_api_base_url' => 'https://hub.pbb.ph',
            'hq_api_token' => '',
            'targets' => [],
            'hubs' => [],
            'maestro_enabled' => false,
        ], is_array($config['relay'] ?? null) ? $config['relay'] : []);
        $config['dependencies'] = is_array($config['dependencies'] ?? null) ? $config['dependencies'] : [];
        $config['platform'] = array_merge([
            'mysql_binary' => '',
        ], is_array($config['platform'] ?? null) ? $config['platform'] : []);
        $config['services'] = array_merge([
            'target_os' => PHP_OS_FAMILY,
            'manager' => PHP_OS_FAMILY === 'Windows' ? 'windows-service' : 'systemd',
            'startup_mode' => 'automatic',
            'registration_mode' => 'generate',
        ], is_array($config['services'] ?? null) ? $config['services'] : []);
        $config['options'] = array_merge([
            'run_migrations' => true,
            'database_setup' => 'baseline_schema',
            'write_env' => true,
            'cache_config' => false,
            'validate_after_install' => true,
            'overwrite_env' => false,
        ], is_array($config['options'] ?? null) ? $config['options'] : []);

        return $config;
    }

    public static function validateConfig(array $config): array
    {
        $errors = [];
        if (! in_array((string) ($config['mode'] ?? ''), ['preflight', 'fresh', 'repair', 'upgrade'], true)) {
            $errors['mode'] = 'mode must be one of preflight, fresh, repair, or upgrade.';
        }
        if (trim((string) ($config['app']['install_path'] ?? '')) === '') {
            $errors['app.install_path'] = 'app.install_path is required.';
        }
        if (trim((string) ($config['app']['app_url'] ?? '')) === '') {
            $errors['app.app_url'] = 'app.app_url is required.';
        }
        if (! in_array((string) ($config['database']['driver'] ?? ''), ['mysql', 'sqlite'], true)) {
            $errors['database.driver'] = 'database.driver must be mysql or sqlite.';
        }
        if (($config['database']['driver'] ?? '') === 'mysql') {
            foreach (['host', 'database', 'username'] as $key) {
                if (trim((string) ($config['database'][$key] ?? '')) === '') {
                    $errors["database.{$key}"] = "database.{$key} is required for MySQL.";
                }
            }
        }
        if (($config['database']['driver'] ?? '') === 'sqlite' && trim((string) ($config['database']['sqlite_path'] ?? '')) === '') {
            $errors['database.sqlite_path'] = 'database.sqlite_path is required for SQLite.';
        }
        foreach (['name', 'email', 'password'] as $key) {
            if (trim((string) ($config['admin'][$key] ?? '')) === '') {
                $errors["admin.{$key}"] = "admin.{$key} is required.";
            }
        }
        if (! in_array((string) ($config['admin']['strategy'] ?? 'create_if_missing'), ['create_if_missing'], true)) {
            $errors['admin.strategy'] = 'admin.strategy must be create_if_missing.';
        }
        $passwordError = self::adminPasswordError((string) ($config['admin']['password'] ?? ''));
        if ($passwordError !== null) {
            $errors['admin.password'] = $passwordError;
        }

        return $errors;
    }

    public static function preflight(array $config): array
    {
        $checks = [];
        $checks[] = self::check('php.version', 'PHP version', version_compare(PHP_VERSION, '8.2.0', '>='), 'PHP ' . PHP_VERSION . ' detected.', 'Install PHP 8.2 or newer.');

        foreach (['json', 'openssl', 'mbstring', 'fileinfo', 'zip', 'pdo'] as $extension) {
            $checks[] = self::check("php.extension.{$extension}", strtoupper($extension) . ' extension', extension_loaded($extension), "Extension [{$extension}] is loaded.", "Enable PHP extension [{$extension}].");
        }

        $driver = (string) ($config['database']['driver'] ?? 'mysql');
        if ($driver === 'mysql') {
            $checks[] = self::check('php.extension.pdo_mysql', 'PDO MySQL extension', extension_loaded('pdo_mysql'), 'PDO MySQL is available.', 'Enable pdo_mysql.');
            $checks[] = self::mysqlClientCheck($config);
        } else {
            $checks[] = self::check('php.extension.pdo_sqlite', 'PDO SQLite extension', extension_loaded('pdo_sqlite'), 'PDO SQLite is available.', 'Enable pdo_sqlite.');
        }

        $installPath = (string) $config['app']['install_path'];
        $checks[] = self::pathCheck('filesystem.install_path', 'Install path', $installPath);

        $publicPath = (string) ($config['app']['public_path'] ?? '');
        if ($publicPath !== '') {
            $checks[] = self::pathCheck('filesystem.public_path', 'Public path', $publicPath);
        }

        $source = self::releaseRoot();
        if (self::hasRootLaravelRelease($source)) {
            foreach (['app', 'bootstrap', 'config', 'database', 'public', 'resources', 'routes', 'vendor', 'artisan', '.env.example'] as $path) {
                $checks[] = self::check('release.' . str_replace(['/', '\\', '.'], '_', $path), "Release {$path}", file_exists($source . DIRECTORY_SEPARATOR . $path), "Release path [{$path}] exists.", "Release path [{$path}] is missing.");
            }
        } else {
            $checks[] = self::check(
                'release.installer_zip',
                'Compact installer package',
                self::compactInstallerPackageIsReadable($source),
                'Compact Relay installer package is readable.',
                'Expected app files at release root or a readable installer.zip with embedded relay-release/relay-release.zip.'
            );
        }

        $dbCheck = self::databaseCheck($config);
        $checks[] = $dbCheck;

        $failed = array_values(array_filter($checks, static fn (array $check): bool => $check['status'] === 'failed'));

        return [
            'status' => $failed === [] ? 'passed' : 'failed',
            'checks' => $checks,
        ];
    }

    public static function install(array $config, string $mode): array
    {
        $steps = [];
        $installPath = self::normalizePath((string) $config['app']['install_path']);

        self::copyReleaseToInstallPath($installPath, $mode);
        $steps[] = self::step('filesystem', 'success', 'Application files are present in the install path.');

        if ((bool) ($config['options']['write_env'] ?? true)) {
            self::writeEnvironment($installPath, $config);
            $steps[] = self::step('environment', 'success', '.env was written.');
        } else {
            $steps[] = self::step('environment', 'skipped', '.env writing was disabled.');
        }

        self::prepareDatabaseTarget($config);

        $databaseInstall = [
            'migrations_ran' => false,
            'migration_exit_code' => null,
            'strategy' => 'skipped',
            'baseline_schema' => self::baselineSchemaRelativePath(),
            'baseline_schema_used' => false,
            'migration_rows' => 0,
            'upgrade_strategy' => self::upgradeStrategy(),
            'schema_strategy' => 'skipped',
        ];

        if ((bool) ($config['options']['run_migrations'] ?? true)) {
            $databaseInstall = self::runMigrations($installPath, $config);
            $steps[] = self::step(
                'migrate',
                'success',
                ($databaseInstall['baseline_schema_used'] ?? false)
                    ? 'Database baseline schema loaded; pending migrations checked.'
                    : 'Database migrations completed.'
            );
        } else {
            $steps[] = self::step('migrate', 'skipped', 'Database migrations were disabled.');
        }

        self::bootstrapAdmin($installPath, $config);
        $steps[] = self::step('admin', 'success', 'Initial admin account is present.');

        if ((bool) ($config['options']['cache_config'] ?? false)) {
            self::runArtisan($installPath, ['config:cache']);
            $steps[] = self::step('optimize', 'success', 'Configuration cache generated.');
        } else {
            $steps[] = self::step('optimize', 'skipped', 'Configuration cache was not requested.');
        }

        self::prunePostEnvironmentInstallPaths($installPath);

        if (trim((string) ($config['app']['public_path'] ?? '')) !== '') {
            self::preparePublicPath($installPath, self::normalizePath((string) $config['app']['public_path']));
            $steps[] = self::step('public-path', 'success', 'Public handoff path prepared.');
        }

        $serviceArtifact = self::writeServiceArtifact($installPath, $config);
        $steps[] = self::step('services', 'success', 'Relay worker service artifact generated.');

        $manifest = self::manifest($config, $serviceArtifact, $databaseInstall);
        self::writeJsonFile($installPath . DIRECTORY_SEPARATOR . 'storage/app/installer/install-manifest.json', $manifest);
        self::writeJsonFile($installPath . DIRECTORY_SEPARATOR . '.relay-installed.lock', [
            'installed_at' => date(DATE_ATOM),
            'relay_release_version' => self::VERSION,
            'relay_hub_id' => (string) ($config['relay']['hub_id'] ?? ''),
            'app_url' => (string) ($config['app']['app_url'] ?? ''),
        ]);

        return [
            'steps' => $steps,
            'manifest' => $manifest,
            'service_artifact' => $serviceArtifact,
            'database' => $databaseInstall,
            'filesystem' => $manifest['filesystem'],
        ];
    }

    public static function status(?string $installPath = null): array
    {
        $root = $installPath ?: self::releaseRoot();
        $manifestPath = $root . DIRECTORY_SEPARATOR . 'storage/app/installer/install-manifest.json';
        $lockPath = $root . DIRECTORY_SEPARATOR . '.relay-installed.lock';
        $installed = is_file($manifestPath) || is_file($lockPath);
        $manifest = is_file($manifestPath) ? self::readJsonFile($manifestPath) : [];

        return [
            'schema_version' => 1,
            'app' => self::APP_ID,
            'version' => self::VERSION,
            'installed' => $installed,
            'status' => $installed ? 'healthy' : 'not-installed',
            'mode' => $installed ? 'installed' : 'new',
            'health' => [
                'http' => 'unknown',
                'ready' => $installed ? 'ok' : 'unknown',
                'details' => [
                    'manifest_exists' => is_file($manifestPath),
                    'lock_exists' => is_file($lockPath),
                    'env_exists' => is_file($root . DIRECTORY_SEPARATOR . '.env'),
                ],
            ],
            'services' => $manifest['services'] ?? [],
            'warnings' => [],
        ];
    }

    public static function report(array $config, string $status, array $steps, array $extra = []): array
    {
        $startedAt = (string) ($extra['started_at'] ?? date(DATE_ATOM));
        $mode = (string) ($config['mode'] ?? 'fresh');

        return [
            'schema_version' => 1,
            'app' => self::APP_ID,
            'version' => self::VERSION,
            'run_id' => (string) ($config['kit']['run_id'] ?? ''),
            'mode' => $mode,
            'status' => $status,
            'started_at' => $startedAt,
            'finished_at' => date(DATE_ATOM),
            'summary' => (string) ($extra['summary'] ?? ($status === 'success' ? 'PBB Relay installer completed.' : 'PBB Relay installer failed.')),
            'steps' => $steps,
            'urls' => [
                'app' => (string) ($config['app']['app_url'] ?? ''),
                'status' => rtrim((string) ($config['app']['app_url'] ?? ''), '/') . '/api/status',
            ],
            'services' => isset($extra['service_artifact']) ? [[
                'id' => 'pbb-relay-worker',
                'status' => 'artifact-generated',
                'message' => 'Relay worker service artifact generated; registration is left to Kit Setup or an operator.',
                'artifact' => $extra['service_artifact']['artifact_path'] ?? null,
            ]] : [],
            'warnings' => $extra['warnings'] ?? [],
            'errors' => $extra['errors'] ?? [],
        ];
    }

    public static function step(string $id, string $status, string $message): array
    {
        return ['id' => $id, 'status' => $status, 'message' => $message];
    }

    private static function check(string $id, string $label, bool $passes, string $message, ?string $remediation = null): array
    {
        return [
            'id' => $id,
            'label' => $label,
            'status' => $passes ? 'passed' : 'failed',
            'message' => $passes ? $message : (string) $remediation,
            'remediation' => $passes ? null : $remediation,
        ];
    }

    private static function pathCheck(string $id, string $label, string $path): array
    {
        $parent = self::nearestExistingParent($path);
        $passes = $parent !== null && is_writable($parent);

        return self::check($id, $label, $passes, "Path [{$path}] can be written.", "Ensure a writable parent exists for [{$path}].");
    }

    private static function databaseCheck(array $config): array
    {
        try {
            $driver = (string) ($config['database']['driver'] ?? 'mysql');
            if ($driver === 'sqlite') {
                $path = (string) ($config['database']['sqlite_path'] ?? '');
                $directory = dirname($path);
                if (is_file($path)) {
                    new PDO('sqlite:' . $path);
                } else {
                    $parent = self::nearestExistingParent($directory);
                    if ($parent === null || ! is_writable($parent)) {
                        throw new RuntimeException("SQLite path [{$path}] does not have a writable parent directory.");
                    }
                }
            } else {
                new PDO(
                    sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', (string) $config['database']['host'], (int) $config['database']['port'], (string) $config['database']['database']),
                    (string) $config['database']['username'],
                    (string) $config['database']['password']
                );
            }
        } catch (Throwable $e) {
            return [
                'id' => 'database.connection',
                'label' => 'Database connection',
                'status' => 'failed',
                'message' => $e->getMessage(),
                'remediation' => 'Verify database settings and PHP database extensions.',
            ];
        }

        return [
            'id' => 'database.connection',
            'label' => 'Database connection',
            'status' => 'passed',
            'message' => 'Database connection is available.',
            'remediation' => null,
        ];
    }

    private static function mysqlClientCheck(array $config): array
    {
        $binary = self::mysqlBinaryPath($config);

        return self::check(
            'platform.mysql_binary',
            'MySQL client binary',
            $binary !== null,
            "MySQL client binary [{$binary}] is available.",
            'Kit did not provide a valid platform.mysql_binary / PBB_MYSQL_BINARY.'
        );
    }

    private static function prepareDatabaseTarget(array $config): void
    {
        if (($config['database']['driver'] ?? '') !== 'sqlite') {
            return;
        }

        $path = (string) ($config['database']['sqlite_path'] ?? '');
        if ($path === '') {
            throw new RuntimeException('SQLite path is required.');
        }

        $directory = dirname($path);
        if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new RuntimeException("SQLite directory [{$directory}] could not be created.");
        }

        if (! is_file($path) && file_put_contents($path, '') === false) {
            throw new RuntimeException("SQLite database file [{$path}] could not be created.");
        }
    }

    private static function copyReleaseToInstallPath(string $installPath, string $mode): void
    {
        $source = self::releaseRoot();
        if (self::samePath($source, $installPath)) {
            if (! self::hasRootLaravelRelease($source)) {
                self::extractCompactInstallerRelease($source, $installPath);
                self::pruneNonRuntimeInstallPaths($installPath);
                self::ensureStorageDirectories($installPath);
                self::sanitizeRuntimeArtisanSurface($installPath);
                return;
            }

            self::pruneNonRuntimeInstallPaths($installPath);
            self::ensureStorageDirectories($installPath);
            self::sanitizeRuntimeArtisanSurface($installPath);
            return;
        }

        if ($mode === 'fresh' && is_dir($installPath) && self::directoryHasContents($installPath)) {
            throw new RuntimeException("Fresh install target [{$installPath}] already contains files.");
        }

        if (! is_dir($installPath) && ! mkdir($installPath, 0775, true) && ! is_dir($installPath)) {
            throw new RuntimeException("Install path [{$installPath}] could not be created.");
        }

        if (! self::hasRootLaravelRelease($source)) {
            self::extractCompactInstallerRelease($source, $installPath);
            self::copyPath($source . DIRECTORY_SEPARATOR . 'installer', $installPath . DIRECTORY_SEPARATOR . 'installer');
            self::copyPath($source . DIRECTORY_SEPARATOR . 'release.json', $installPath . DIRECTORY_SEPARATOR . 'release.json');
            self::ensureStorageDirectories($installPath);
            self::sanitizeRuntimeArtisanSurface($installPath);
            return;
        }

        foreach (['app', 'bootstrap', 'config', 'database', 'public', 'resources', 'routes', 'vendor', 'installer'] as $directory) {
            self::copyPath($source . DIRECTORY_SEPARATOR . $directory, $installPath . DIRECTORY_SEPARATOR . $directory);
        }

        foreach (['artisan', 'composer.json', 'composer.lock', '.env.example', 'release.json'] as $file) {
            self::copyPath($source . DIRECTORY_SEPARATOR . $file, $installPath . DIRECTORY_SEPARATOR . $file);
        }

        self::ensureStorageDirectories($installPath);
        self::pruneNonRuntimeInstallPaths($installPath);
        self::sanitizeRuntimeArtisanSurface($installPath);
    }

    private static function hasRootLaravelRelease(string $source): bool
    {
        foreach (['app', 'bootstrap', 'config', 'database', 'public', 'resources', 'routes', 'vendor', 'artisan', '.env.example'] as $path) {
            if (! file_exists($source . DIRECTORY_SEPARATOR . $path)) {
                return false;
            }
        }

        return true;
    }

    private static function compactInstallerPackageIsReadable(string $source): bool
    {
        $installerZip = $source . DIRECTORY_SEPARATOR . 'installer.zip';
        if (! is_file($installerZip) || ! class_exists(ZipArchive::class)) {
            return false;
        }

        $archive = new ZipArchive();
        if ($archive->open($installerZip) !== true) {
            return false;
        }

        $ok = $archive->locateName('relay-release/relay-release.zip') !== false;
        $archive->close();

        return $ok;
    }

    private static function extractCompactInstallerRelease(string $source, string $installPath): void
    {
        $installerZip = $source . DIRECTORY_SEPARATOR . 'installer.zip';
        if (! is_file($installerZip)) {
            throw new RuntimeException('Compact installer.zip was not found.');
        }

        $outer = new ZipArchive();
        if ($outer->open($installerZip) !== true) {
            throw new RuntimeException('Compact installer.zip could not be opened.');
        }

        $embedded = $outer->getFromName('relay-release/relay-release.zip');
        $outer->close();

        if (! is_string($embedded) || $embedded === '') {
            throw new RuntimeException('Compact installer.zip does not contain relay-release/relay-release.zip.');
        }

        $tempZip = $installPath . DIRECTORY_SEPARATOR . 'storage/app/installer/relay-release.zip';
        self::ensureStorageDirectories($installPath);
        file_put_contents($tempZip, $embedded);

        $release = new ZipArchive();
        if ($release->open($tempZip) !== true) {
            throw new RuntimeException('Embedded relay-release.zip could not be opened.');
        }

        if (! $release->extractTo($installPath)) {
            $release->close();
            throw new RuntimeException('Embedded relay-release.zip could not be extracted.');
        }

        $release->close();
        @unlink($tempZip);
    }

    private static function writeEnvironment(string $installPath, array $config): void
    {
        $envPath = $installPath . DIRECTORY_SEPARATOR . '.env';
        $overwrite = (bool) ($config['options']['overwrite_env'] ?? false);

        if (is_file($envPath) && ! $overwrite && in_array((string) $config['mode'], ['repair', 'upgrade'], true)) {
            return;
        }

        $content = is_file($envPath)
            ? (string) file_get_contents($envPath)
            : (is_file($installPath . DIRECTORY_SEPARATOR . '.env.example') ? (string) file_get_contents($installPath . DIRECTORY_SEPARATOR . '.env.example') : '');

        $database = $config['database'];
        $relay = $config['relay'];
        $dependencies = $config['dependencies'];
        $maestro = is_array($dependencies['maestro'] ?? null) ? $dependencies['maestro'] : [];

        $values = [
            'APP_NAME' => 'PBB - Hub Relay Server',
            'APP_ENV' => (string) ($config['app']['app_env'] ?? 'production'),
            'APP_DEBUG' => ((bool) ($config['app']['app_debug'] ?? false)) ? 'true' : 'false',
            'APP_URL' => (string) $config['app']['app_url'],
            'APP_KEY' => self::existingEnvValue($content, 'APP_KEY') ?: 'base64:' . base64_encode(random_bytes(32)),
            'DB_CONNECTION' => (string) $database['driver'],
            'DB_HOST' => $database['driver'] === 'mysql' ? (string) $database['host'] : null,
            'DB_PORT' => $database['driver'] === 'mysql' ? (string) $database['port'] : null,
            'DB_DATABASE' => $database['driver'] === 'sqlite' ? (string) $database['sqlite_path'] : (string) $database['database'],
            'DB_USERNAME' => $database['driver'] === 'mysql' ? (string) $database['username'] : null,
            'DB_PASSWORD' => $database['driver'] === 'mysql' ? (string) $database['password'] : null,
            'QUEUE_CONNECTION' => 'database',
            'SESSION_DRIVER' => 'database',
            'CACHE_STORE' => 'database',
            'RELAY_LOCAL_HUB_ID' => (string) ($relay['hub_id'] ?? ''),
            'RELAY_HQ_API_ENABLED' => trim((string) ($relay['hq_api_token'] ?? '')) !== '' ? 'true' : 'false',
            'RELAY_HQ_API_BASE_URL' => (string) ($relay['hq_api_base_url'] ?? 'https://hub.pbb.ph'),
            'RELAY_HQ_API_TOKEN' => (string) ($relay['hq_api_token'] ?? ''),
            'RELAY_HQ_LOCAL_RELAY_HUB_ID' => (string) ($relay['hub_id'] ?? ''),
            'RELAY_HQ_LOCAL_HQ_ID' => (string) ($relay['hq_hub_id'] ?? ''),
            'RELAY_HQ_SYNC_ENABLED' => trim((string) ($relay['hq_api_token'] ?? '')) !== '' ? 'true' : 'false',
            'RELAY_MAESTRO_ENABLED' => ((bool) ($relay['maestro_enabled'] ?? false)) ? 'true' : 'false',
            'RELAY_MAESTRO_BASE_URL' => (string) ($maestro['base_url'] ?? ''),
            'RELAY_MAESTRO_APP_CODE' => (string) ($maestro['app_code'] ?? 'relay'),
            'RELAY_MAESTRO_TELEMETRY_TOKEN' => (string) ($maestro['telemetry_token'] ?? ''),
            'INSTALLER_ENABLED' => 'false',
        ];

        foreach ($values as $key => $value) {
            $content = self::setEnvValue($content, $key, self::stringifyEnvValue($value));
        }

        file_put_contents($envPath, $content);
    }

    private static function bootstrapAdmin(string $installPath, array $config): void
    {
        $bootstrapRoot = str_replace('\\', '/', $installPath);
        $overwriteExisting = (bool) ($config['admin']['overwrite_existing'] ?? false);
        $script = <<<'PHP'
<?php
require '__INSTALL_PATH__/vendor/autoload.php';
$app = require '__INSTALL_PATH__/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$userClass = App\Models\User::class;
$user = $userClass::where('email', $argv[2])->first();
$overwrite = ($argv[4] ?? '0') === '1';
if ($user === null) {
    $userClass::create([
        'name' => $argv[1],
        'email' => $argv[2],
        'password' => Illuminate\Support\Facades\Hash::make($argv[3]),
        'role' => $userClass::ROLE_ADMIN,
        'is_active' => true,
    ]);
    exit(0);
}
if ($overwrite) {
    $user->forceFill([
        'name' => $argv[1],
        'email' => $argv[2],
        'password' => Illuminate\Support\Facades\Hash::make($argv[3]),
        'role' => $userClass::ROLE_ADMIN,
        'is_active' => true,
    ])->save();
}
PHP;
        $script = str_replace('__INSTALL_PATH__', $bootstrapRoot, $script);
        $scriptPath = $installPath . DIRECTORY_SEPARATOR . 'storage/app/installer/bootstrap-admin.php';
        self::writeTextFile($scriptPath, $script);
        self::runPhp($installPath, [
            $scriptPath,
            (string) $config['admin']['name'],
            (string) $config['admin']['email'],
            (string) $config['admin']['password'],
            $overwriteExisting ? '1' : '0',
        ]);
        @unlink($scriptPath);
    }

    private static function adminPasswordError(string $password): ?string
    {
        $password = trim($password);
        if ($password === '') {
            return 'admin.password is required.';
        }

        $lower = strtolower($password);
        $placeholders = [
            'password',
            'password123',
            'changeme',
            'change-me',
            'change-me-now',
            'replace-with-real-password',
            'provided-once-in-kit-setup',
            'admin',
            'admin123',
            'pbb',
            'pbb123',
        ];

        if (in_array($lower, $placeholders, true) || str_contains($lower, 'replace-with') || str_contains($lower, 'provided-once')) {
            return 'admin.password must not be blank, placeholder, or weak.';
        }

        if (strlen($password) < 12) {
            return 'admin.password must be at least 12 characters.';
        }

        $classes = 0;
        $classes += preg_match('/[a-z]/', $password) === 1 ? 1 : 0;
        $classes += preg_match('/[A-Z]/', $password) === 1 ? 1 : 0;
        $classes += preg_match('/[0-9]/', $password) === 1 ? 1 : 0;
        $classes += preg_match('/[^A-Za-z0-9]/', $password) === 1 ? 1 : 0;

        if ($classes < 3) {
            return 'admin.password must include at least three character classes.';
        }

        return null;
    }

    private static function writeServiceArtifact(string $installPath, array $config): array
    {
        $generatedRoot = $installPath . DIRECTORY_SEPARATOR . 'storage/app/installer/generated';
        if (! is_dir($generatedRoot) && ! mkdir($generatedRoot, 0775, true) && ! is_dir($generatedRoot)) {
            throw new RuntimeException("Generated artifact directory [{$generatedRoot}] could not be created.");
        }

        $php = PHP_BINARY;
        $command = $php . ' artisan queue:work --queue=relay-deliveries,relay-handlers';
        $targetOs = strtolower((string) ($config['services']['target_os'] ?? PHP_OS_FAMILY));

        if (str_contains($targetOs, 'win')) {
            $artifactPath = $generatedRoot . DIRECTORY_SEPARATOR . 'pbb-relay-worker.ps1';
            $content = '$ErrorActionPreference = "Stop"' . PHP_EOL
                . 'Set-Location -LiteralPath "' . $installPath . '"' . PHP_EOL
                . '& "' . $php . '" artisan queue:work --queue=relay-deliveries,relay-handlers' . PHP_EOL;
        } else {
            $artifactPath = $generatedRoot . DIRECTORY_SEPARATOR . 'pbb-relay-worker.service';
            $content = "[Unit]\nDescription=PBB Relay Worker\nAfter=network.target\n\n[Service]\nWorkingDirectory={$installPath}\nExecStart={$command}\nRestart=always\n\n[Install]\nWantedBy=multi-user.target\n";
        }

        self::writeTextFile($artifactPath, $content);

        return [
            'id' => 'pbb-relay-worker',
            'name' => 'PBB Relay Worker',
            'kind' => 'worker',
            'command' => $command,
            'working_directory' => $installPath,
            'env_file' => $installPath . DIRECTORY_SEPARATOR . '.env',
            'log_file' => $installPath . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . 'worker.log',
            'startup_mode' => (string) ($config['services']['startup_mode'] ?? 'automatic'),
            'restart_policy' => 'always',
            'registered' => false,
            'artifact_path' => $artifactPath,
            'healthcheck' => [
                'type' => 'maestro',
                'app_code' => 'relay',
                'max_stale_seconds' => 60,
            ],
        ];
    }

    private static function runtimeServiceDeclaration(string $installPath): array
    {
        return [
            'id' => 'pbb-relay-worker',
            'name' => 'PBB Relay Worker',
            'type' => 'background_process',
            'required' => true,
            'required_for_smoke' => true,
            'manager' => 'kit',
            'working_directory' => $installPath,
            'command' => PHP_BINARY,
            'args' => ['artisan', 'queue:work', '--queue=relay-deliveries,relay-handlers'],
            'health_check' => [
                'type' => 'process',
                'timeout_seconds' => 3,
            ],
            'logs' => [
                'stdout' => 'storage/logs/pbb-relay-worker.out.log',
                'stderr' => 'storage/logs/pbb-relay-worker.err.log',
            ],
            'notes' => 'Kit starts and verifies this after Relay install so outbound deliveries, local handler dispatches, and Maestro worker telemetry can run. Maestro telemetry becomes active after Data Prep apply-settings writes the Relay telemetry token and Kit restarts the worker.',
        ];
    }

    private static function manifest(array $config, array $serviceArtifact, array $databaseInstall): array
    {
        $database = $config['database'];
        $installPath = self::normalizePath((string) $config['app']['install_path']);
        $runtimeServices = [self::runtimeServiceDeclaration($installPath)];

        return [
            'schema_version' => 1,
            'app' => self::APP_ID,
            'name' => self::APP_NAME,
            'version' => self::VERSION,
            'installed_at' => date(DATE_ATOM),
            'install_mode' => (string) $config['mode'],
            'install_path' => $installPath,
            'public_path' => (string) ($config['app']['public_path'] ?? ''),
            'app_url' => (string) $config['app']['app_url'],
            'environment' => (string) ($config['app']['app_env'] ?? 'production'),
            'database' => [
                'driver' => (string) $database['driver'],
                'host' => $database['driver'] === 'mysql' ? (string) $database['host'] : null,
                'port' => $database['driver'] === 'mysql' ? (int) $database['port'] : null,
                'database' => $database['driver'] === 'sqlite' ? (string) $database['sqlite_path'] : (string) $database['database'],
                'username' => $database['driver'] === 'mysql' ? (string) $database['username'] : null,
                'install_method' => (string) ($databaseInstall['strategy'] ?? ($databaseInstall['schema_strategy'] ?? 'unknown')),
                'baseline_schema' => $databaseInstall['baseline_schema'] ?? null,
                'baseline_schema_used' => (bool) ($databaseInstall['baseline_schema_used'] ?? false),
                'migrations_ran' => (bool) ($databaseInstall['migrations_ran'] ?? false),
            ],
            'database_setup' => self::databaseSetupReport($databaseInstall),
            'filesystem' => self::filesystemReport($config, $serviceArtifact),
            'web_server' => self::webServerReport($config),
            'services' => [$serviceArtifact],
            'runtime_services' => $runtimeServices,
            'health' => [
                'last_checked_at' => date(DATE_ATOM),
                'status' => 'pending',
            ],
        ];
    }

    private static function webServerReport(array $config): array
    {
        return [
            'owner' => 'kit',
            'requirements' => [],
            'app_url' => (string) ($config['app']['app_url'] ?? ''),
            'public_path' => (string) ($config['app']['public_path'] ?? ''),
            'install_blocking' => false,
            'notes' => 'Relay does not write global Apache/Nginx configuration and declares no app-specific websocket or reverse-proxy routes.',
        ];
    }

    private static function filesystemReport(array $config, array $serviceArtifact): array
    {
        $installPath = self::normalizePath((string) $config['app']['install_path']);
        $publicPath = trim((string) ($config['app']['public_path'] ?? ''));
        $sqlitePath = ($config['database']['driver'] ?? '') === 'sqlite'
            ? trim((string) ($config['database']['sqlite_path'] ?? ''))
            : '';

        $runtimePaths = [
            'app_root' => $installPath,
            'environment_file' => $installPath . DIRECTORY_SEPARATOR . '.env',
            'storage_root' => $installPath . DIRECTORY_SEPARATOR . 'storage',
            'installer_state_root' => $installPath . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'installer',
            'framework_cache' => $installPath . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'framework' . DIRECTORY_SEPARATOR . 'cache',
            'framework_sessions' => $installPath . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'framework' . DIRECTORY_SEPARATOR . 'sessions',
            'framework_views' => $installPath . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'framework' . DIRECTORY_SEPARATOR . 'views',
            'logs' => $installPath . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'logs',
            'bootstrap_cache' => $installPath . DIRECTORY_SEPARATOR . 'bootstrap' . DIRECTORY_SEPARATOR . 'cache',
            'install_manifest' => $installPath . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'installer' . DIRECTORY_SEPARATOR . 'install-manifest.json',
            'install_report' => $installPath . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'installer' . DIRECTORY_SEPARATOR . 'install-report.json',
            'install_lock' => $installPath . DIRECTORY_SEPARATOR . '.relay-installed.lock',
            'service_artifact' => (string) ($serviceArtifact['artifact_path'] ?? ''),
            'worker_log' => (string) ($serviceArtifact['log_file'] ?? ''),
        ];

        $externalPaths = [];
        if ($publicPath !== '') {
            $externalPaths['public_path'] = self::normalizePath($publicPath);
        }
        if ($sqlitePath !== '') {
            $externalPaths['sqlite_database'] = self::normalizePath($sqlitePath);
        }

        return [
            'install_path' => $installPath,
            'runtime_paths' => array_filter($runtimePaths, static fn (string $path): bool => $path !== ''),
            'external_paths' => $externalPaths,
            'created_or_relied_on' => array_values(array_filter(array_merge(array_values($runtimePaths), array_values($externalPaths)), static fn (string $path): bool => $path !== '')),
        ];
    }

    private static function preparePublicPath(string $installPath, string $publicPath): void
    {
        $sourcePublic = $installPath . DIRECTORY_SEPARATOR . 'public';
        if (self::samePath($sourcePublic, $publicPath)) {
            return;
        }

        self::copyPath($sourcePublic, $publicPath);
        $index = <<<'PHP'
<?php

define('LARAVEL_START', microtime(true));

require '__INSTALL_PATH__/vendor/autoload.php';
$app = require_once '__INSTALL_PATH__/bootstrap/app.php';
$app->handleRequest(Illuminate\Http\Request::capture());
PHP;
        $index = str_replace('__INSTALL_PATH__', str_replace('\\', '/', $installPath), $index);
        self::writeTextFile($publicPath . DIRECTORY_SEPARATOR . 'index.php', $index);
    }

    private static function runArtisan(string $installPath, array $arguments): void
    {
        self::runPhp($installPath, array_merge([$installPath . DIRECTORY_SEPARATOR . 'artisan'], $arguments));
    }

    private static function runMigrations(string $installPath, array $config): array
    {
        if (($config['database']['driver'] ?? 'mysql') === 'mysql') {
            self::requireMysqlBinary($config);
        }

        $result = self::runPhp($installPath, [$installPath . DIRECTORY_SEPARATOR . 'artisan', 'migrate', '--force'], $config);
        $strategy = self::detectMigrationSchemaStrategy($result);
        $migrationRows = self::countMigrationRows($config);

        return [
            'migrations_ran' => true,
            'migration_exit_code' => $result['exit_code'],
            'strategy' => $strategy,
            'schema_strategy' => $strategy,
            'baseline_schema' => self::baselineSchemaRelativePath(),
            'baseline_schema_used' => $strategy === 'baseline_schema',
            'migration_rows' => $migrationRows,
            'upgrade_strategy' => self::upgradeStrategy(),
        ];
    }

    private static function baselineSchemaRelativePath(): string
    {
        return 'database/schema/mysql-schema.sql';
    }

    private static function detectMigrationSchemaStrategy(array $result): string
    {
        $output = (string) (($result['stdout'] ?? '') . "\n" . ($result['stderr'] ?? ''));

        if (stripos($output, 'Loading stored database schemas') !== false) {
            return 'baseline_schema';
        }

        return 'migrations';
    }

    private static function databaseSetupReport(array $databaseInstall): array
    {
        return [
            'strategy' => (string) ($databaseInstall['strategy'] ?? ($databaseInstall['schema_strategy'] ?? 'unknown')),
            'baseline_schema' => (string) ($databaseInstall['baseline_schema'] ?? self::baselineSchemaRelativePath()),
            'baseline_schema_used' => (bool) ($databaseInstall['baseline_schema_used'] ?? false),
            'migration_rows' => (int) ($databaseInstall['migration_rows'] ?? 0),
            'upgrade_strategy' => (string) ($databaseInstall['upgrade_strategy'] ?? self::upgradeStrategy()),
        ];
    }

    private static function upgradeStrategy(): string
    {
        return 'laravel_migrations';
    }

    private static function countMigrationRows(array $config): int
    {
        try {
            $database = $config['database'] ?? [];
            if (($database['driver'] ?? 'mysql') === 'sqlite') {
                $pdo = new PDO('sqlite:' . (string) ($database['sqlite_path'] ?? ''));
            } else {
                $pdo = new PDO(
                    sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', (string) $database['host'], (int) $database['port'], (string) $database['database']),
                    (string) $database['username'],
                    (string) $database['password'],
                    [PDO::ATTR_TIMEOUT => 5, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
                );
            }

            $statement = $pdo->query('SELECT COUNT(*) FROM migrations');

            return $statement !== false ? (int) $statement->fetchColumn() : 0;
        } catch (Throwable) {
            return 0;
        }
    }

    private static function runPhp(string $cwd, array $arguments, ?array $config = null): array
    {
        $command = escapeshellarg(PHP_BINARY);
        foreach ($arguments as $argument) {
            $command .= ' ' . escapeshellarg((string) $argument);
        }

        $descriptorSpec = [
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($command, $descriptorSpec, $pipes, $cwd, self::processEnvironment($config));
        if (! is_resource($process)) {
            throw new RuntimeException("Command could not be started: {$command}");
        }

        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        if ($exitCode !== 0) {
            throw new RuntimeException(trim($stderr . PHP_EOL . $stdout) ?: "Command failed with exit code {$exitCode}.");
        }

        return [
            'exit_code' => $exitCode,
            'stdout' => (string) $stdout,
            'stderr' => (string) $stderr,
        ];
    }

    private static function processEnvironment(?array $config = null): array
    {
        $environment = getenv();
        if (! is_array($environment)) {
            $environment = $_ENV;
        }

        $path = (string) (getenv('PATH') ?: getenv('Path') ?: '');
        $mysqlBinary = $config !== null ? self::mysqlBinaryPath($config) : self::envMysqlBinaryPath();
        $clientBin = $mysqlBinary !== null ? dirname($mysqlBinary) : null;

        if ($clientBin !== null && ! str_contains(strtolower($path), strtolower($clientBin))) {
            $path = $clientBin . PATH_SEPARATOR . $path;
        }

        $environment['PATH'] = $path;
        $environment['Path'] = $path;
        if ($mysqlBinary !== null) {
            $environment['PBB_MYSQL_BINARY'] = $mysqlBinary;
        }

        return $environment;
    }

    private static function requireMysqlBinary(array $config): string
    {
        $binary = self::mysqlBinaryPath($config);
        if ($binary === null) {
            throw new RuntimeException('Kit did not provide platform.mysql_binary / PBB_MYSQL_BINARY.');
        }

        return $binary;
    }

    private static function mysqlBinaryPath(array $config): ?string
    {
        $configured = trim((string) ($config['platform']['mysql_binary'] ?? ''));
        if ($configured !== '' && is_file($configured)) {
            return $configured;
        }

        return self::envMysqlBinaryPath();
    }

    private static function envMysqlBinaryPath(): ?string
    {
        $binary = trim((string) getenv('PBB_MYSQL_BINARY'));

        return $binary !== '' && is_file($binary) ? $binary : null;
    }

    private static function copyPath(string $source, string $destination): void
    {
        if (! file_exists($source)) {
            throw new RuntimeException("Release source path [{$source}] does not exist.");
        }

        if (is_file($source)) {
            $directory = dirname($destination);
            if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
                throw new RuntimeException("Directory [{$directory}] could not be created.");
            }
            copy($source, $destination);
            return;
        }

        if (! is_dir($destination) && ! mkdir($destination, 0775, true) && ! is_dir($destination)) {
            throw new RuntimeException("Directory [{$destination}] could not be created.");
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $relative = ltrim(str_replace('\\', '/', substr($item->getPathname(), strlen($source))), '/');
            if (self::shouldSkipCopyPath($relative)) {
                continue;
            }
            $target = $destination . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
            if ($item->isDir()) {
                if (! is_dir($target)) {
                    mkdir($target, 0775, true);
                }
            } else {
                $directory = dirname($target);
                if (! is_dir($directory)) {
                    mkdir($directory, 0775, true);
                }
                copy($item->getPathname(), $target);
            }
        }
    }

    private static function shouldSkipCopyPath(string $relative): bool
    {
        $segments = explode('/', trim($relative, '/'));
        foreach ($segments as $segment) {
            $segmentLower = strtolower($segment);
            if ($segment === '' || str_starts_with($segmentLower, '.git') || in_array($segmentLower, self::nonRuntimeDirectoryNames(), true)) {
                return true;
            }
        }

        $normalized = trim(str_replace('\\', '/', $relative), '/');
        return $normalized === '.env'
            || str_starts_with($normalized, 'storage/logs/')
            || str_starts_with($normalized, 'storage/framework/cache/')
            || str_starts_with($normalized, 'storage/framework/sessions/')
            || str_starts_with($normalized, 'storage/framework/views/');
    }

    private static function pruneNonRuntimeInstallPaths(string $installPath): void
    {
        $entries = scandir($installPath);
        if ($entries === false) {
            return;
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $installPath . DIRECTORY_SEPARATOR . $entry;
            $entryLower = strtolower($entry);

            if (is_dir($path) && in_array($entryLower, self::nonRuntimeTopLevelDirectories(), true)) {
                self::removePath($path);
                continue;
            }

            if (is_file($path) && self::isNonRuntimeTopLevelFile($entryLower)) {
                @unlink($path);
            }
        }
    }

    /**
     * @return list<string>
     */
    private static function nonRuntimeDirectoryNames(): array
    {
        return [
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
            'sdk',
            'temp',
            'test',
            'tests',
            'tmp',
        ];
    }

    /**
     * @return list<string>
     */
    private static function nonRuntimeTopLevelDirectories(): array
    {
        return [
            '.git',
            '.github',
            '.gitlab',
            '.idea',
            '.vscode',
            'docs',
            'installer-runtime-template',
            'node_modules',
            'scripts',
            'sdk',
            'tests',
        ];
    }

    private static function isNonRuntimeTopLevelFile(string $entry): bool
    {
        if (str_starts_with($entry, '.git') || str_starts_with($entry, 'tmp_')) {
            return true;
        }

        if (str_starts_with($entry, '.env.relay_') || str_starts_with($entry, 'phase')) {
            return true;
        }

        return in_array($entry, [
            '.editorconfig',
            '.phpunit.result.cache',
            'package.json',
            'pbb.ph.crt',
            'pbb.ph.key',
            'phpunit.xml',
            'README.md',
            'installer.zip',
            'vite.config.js',
        ], true);
    }

    private static function prunePostEnvironmentInstallPaths(string $installPath): void
    {
        foreach (['.env.example', 'composer.lock', 'README.md'] as $file) {
            $path = $installPath . DIRECTORY_SEPARATOR . $file;
            if (is_file($path)) {
                @unlink($path);
            }
        }
    }

    private static function sanitizeRuntimeArtisanSurface(string $installPath): void
    {
        foreach ([
            'app/Console/Commands/BuildRelayInstallerPackageCommand.php',
            'app/Console/Commands/CreateRelayUserCommand.php',
            'database/seeders',
            'installer/index.php',
            'public/relay-installer',
            'public/vendor/helpers.pbb.ph/CHANGELOG.upstream.md',
            'public/vendor/helpers.pbb.ph/README.upstream.md',
            'public/vendor/helpers.pbb.ph/css',
            'public/vendor/helpers.pbb.ph/docs',
        ] as $path) {
            self::removePath($installPath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path));
        }

        self::pruneHelperLoaderSource($installPath);

        $consoleRoutes = $installPath . DIRECTORY_SEPARATOR . 'routes' . DIRECTORY_SEPARATOR . 'console.php';
        if (is_file($consoleRoutes)) {
            file_put_contents($consoleRoutes, "<?php\n\n");
        }
    }

    private static function pruneHelperLoaderSource(string $installPath): void
    {
        $jsRoot = $installPath . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'helpers.pbb.ph' . DIRECTORY_SEPARATOR . 'js';
        if (! is_dir($jsRoot)) {
            return;
        }

        foreach (['demo', 'incident', 'vendor'] as $directory) {
            self::removePath($jsRoot . DIRECTORY_SEPARATOR . $directory);
        }

        $uiRoot = $jsRoot . DIRECTORY_SEPARATOR . 'ui';
        if (is_dir($uiRoot)) {
            $entries = scandir($uiRoot);
            if ($entries !== false) {
                foreach ($entries as $entry) {
                    if ($entry === '.' || $entry === '..' || strtolower($entry) === 'ui.loader.js') {
                        continue;
                    }

                    self::removePath($uiRoot . DIRECTORY_SEPARATOR . $entry);
                }
            }
        }
    }

    private static function removePath(string $path): void
    {
        if (is_file($path) || is_link($path)) {
            @unlink($path);
            return;
        }

        if (! is_dir($path)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            $itemPath = $item->getPathname();
            if ($item->isDir() && ! $item->isLink()) {
                @rmdir($itemPath);
            } else {
                @unlink($itemPath);
            }
        }

        @rmdir($path);
    }

    private static function ensureStorageDirectories(string $installPath): void
    {
        foreach (['storage/app/installer', 'storage/framework/cache', 'storage/framework/sessions', 'storage/framework/views', 'storage/logs', 'bootstrap/cache'] as $directory) {
            $path = $installPath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $directory);
            if (! is_dir($path)) {
                mkdir($path, 0775, true);
            }
        }
    }

    private static function writeTextFile(string $path, string $contents): void
    {
        $directory = dirname($path);
        if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new RuntimeException("Directory [{$directory}] could not be created.");
        }
        file_put_contents($path, $contents);
    }

    private static function setEnvValue(string $content, string $key, string $value): string
    {
        $line = $key . '=' . $value;
        $pattern = '/^' . preg_quote($key, '/') . '=.*$/m';
        if (preg_match($pattern, $content) === 1) {
            return (string) preg_replace_callback($pattern, static fn (): string => $line, $content);
        }
        if ($content !== '' && ! str_ends_with($content, "\n")) {
            $content .= PHP_EOL;
        }
        return $content . $line . PHP_EOL;
    }

    private static function existingEnvValue(string $content, string $key): ?string
    {
        if (preg_match('/^' . preg_quote($key, '/') . '=(.*)$/m', $content, $matches) !== 1) {
            return null;
        }
        $value = trim((string) $matches[1], "\"' \t\r\n");
        return $value !== '' ? $value : null;
    }

    private static function stringifyEnvValue(mixed $value): string
    {
        if ($value === null) {
            return '';
        }
        $string = (string) $value;
        return ($string === '' || preg_match('/\s/', $string) === 1)
            ? '"' . str_replace(["\\", '"', "\r", "\n"], ["\\\\", '\"', '\r', '\n'], $string) . '"'
            : $string;
    }

    private static function nearestExistingParent(string $path): ?string
    {
        $current = is_dir($path) ? $path : dirname($path);
        while ($current !== '' && $current !== '.' && ! is_dir($current)) {
            $next = dirname($current);
            if ($next === $current) {
                break;
            }
            $current = $next;
        }
        return is_dir($current) ? $current : null;
    }

    private static function directoryHasContents(string $directory): bool
    {
        $items = is_dir($directory) ? scandir($directory) : false;
        return is_array($items) && count(array_diff($items, ['.', '..'])) > 0;
    }

    private static function normalizePath(string $path): string
    {
        return rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path), DIRECTORY_SEPARATOR);
    }

    private static function samePath(string $left, string $right): bool
    {
        return self::normalizePath($left) === self::normalizePath($right);
    }
}
