<?php

class RelayInstallerRuntime
{
    private string $runtimeRoot;
    private string $publicRoot;
    private string $installRoot;
    private string $bootstrapRoot;
    private string $statePath;
    private string $lockPath;
    private string $cleanupManifestPath;
    private string $executionStatePath;
    private string $executionLockPath;
    private string $cleanupRoot;
    private string $installedAppRoot;
    private string $envPath;
    private string $releasePackagePath;
    private string $hqApiBaseUrl;
    private mixed $installedConsoleKernel = null;

    public function __construct(string $runtimeRoot)
    {
        $this->runtimeRoot = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $runtimeRoot), DIRECTORY_SEPARATOR);
        $this->publicRoot = $this->runtimeRoot.DIRECTORY_SEPARATOR.'public';
        $this->installRoot = dirname(dirname(dirname($this->runtimeRoot)));
        $this->bootstrapRoot = $this->installRoot.DIRECTORY_SEPARATOR.'.installer';
        $this->statePath = $this->bootstrapRoot.DIRECTORY_SEPARATOR.'state.json';
        $this->lockPath = $this->installRoot.DIRECTORY_SEPARATOR.'.relay-installed.lock';
        $this->cleanupManifestPath = $this->bootstrapRoot.DIRECTORY_SEPARATOR.'cleanup.json';
        $this->executionStatePath = $this->bootstrapRoot.DIRECTORY_SEPARATOR.'execution-state.json';
        $this->executionLockPath = $this->bootstrapRoot.DIRECTORY_SEPARATOR.'locks'.DIRECTORY_SEPARATOR.'execution.lock';
        $this->cleanupRoot = $this->installRoot;
        $this->installedAppRoot = $this->installRoot.DIRECTORY_SEPARATOR.'.relay'.DIRECTORY_SEPARATOR.'app';
        $this->envPath = $this->installedAppRoot.DIRECTORY_SEPARATOR.'.env';
        $this->releasePackagePath = $this->bootstrapRoot.DIRECTORY_SEPARATOR.'runtime'.DIRECTORY_SEPARATOR.'relay-release'.DIRECTORY_SEPARATOR.'relay-release.zip';
        $this->hqApiBaseUrl = 'https://hub.pbb.ph';
    }

    public function handle(): void
    {
        $path = rawurldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

        if ($this->tryServeStaticAsset($path)) {
            return;
        }

        if (($path === '/' || $path === '/install') && $method === 'GET') {
            $this->renderShell();
            return;
        }

        if ($path === '/install/api/environment' && $method === 'GET') {
            $this->json($this->environmentChecks());
            return;
        }

        if ($path === '/install/api/environment/continue' && $method === 'POST') {
            $checks = $this->environmentChecks();
            if (! ($checks['can_continue'] ?? false)) {
                $this->json([
                    'message' => 'Environment checks must pass before continuing.',
                    'checks' => $checks,
                ], 422);
                return;
            }

            $this->json([
                'message' => 'Environment checks accepted.',
                'state' => $this->markEnvironmentChecked($checks['summary'] ?? []),
            ]);
            $this->writeExecutionState($this->defaultExecutionState());
            return;
        }

        if ($path === '/install/api/hq/validate' && $method === 'POST') {
            $this->handleHqValidation();
            return;
        }

        if ($path === '/install/api/settings' && $method === 'POST') {
            $this->handleSettings();
            return;
        }

        if ($path === '/install/api/execute' && $method === 'POST') {
            $this->handleExecution();
            return;
        }

        if ($path === '/install/api/progress' && $method === 'GET') {
            $this->json([
                'execution' => $this->executionState(),
            ]);
            return;
        }

        if ($path === '/install/api/execute/advance' && $method === 'POST') {
            $this->handleExecutionAdvance();
            return;
        }

        if ($path === '/install/api/execute/retry' && $method === 'POST') {
            $this->handleExecutionRetry();
            return;
        }

        if ($path === '/install/api/cleanup' && $method === 'POST') {
            $this->handleCleanup();
            return;
        }

        $this->json(['message' => 'Not found.'], 404);
    }

    private function tryServeStaticAsset(string $path): bool
    {
        $relative = ltrim($path, '/');
        if ($relative === '' || str_contains($relative, '..')) {
            return false;
        }

        $candidate = $this->publicRoot.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);
        if (! is_file($candidate)) {
            return false;
        }

        $types = [
            'css' => 'text/css; charset=utf-8',
            'js' => 'application/javascript; charset=utf-8',
            'json' => 'application/json; charset=utf-8',
            'svg' => 'image/svg+xml',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'woff' => 'font/woff',
            'woff2' => 'font/woff2',
        ];

        $ext = strtolower(pathinfo($candidate, PATHINFO_EXTENSION));
        header('Content-Type: '.($types[$ext] ?? 'application/octet-stream'));
        readfile($candidate);

        return true;
    }

    private function renderShell(): void
    {
        header('Content-Type: text/html; charset=utf-8');

        $state = $this->state();
        $sqlitePlaceholder = $this->installedAppRoot.DIRECTORY_SEPARATOR.'database'.DIRECTORY_SEPARATOR.'relay.sqlite';
        $configJson = json_encode([
            'state' => $state,
            'execution' => $this->executionState(),
            'endpoints' => [
                'environment' => '/install/api/environment',
                'continue' => '/install/api/environment/continue',
                'hqValidate' => '/install/api/hq/validate',
                'settings' => '/install/api/settings',
                'execute' => '/install/api/execute',
                'progress' => '/install/api/progress',
                'advanceExecution' => '/install/api/execute/advance',
                'retryExecution' => '/install/api/execute/retry',
                'cleanup' => '/install/api/cleanup',
            ],
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        require $this->runtimeRoot.DIRECTORY_SEPARATOR.'views'.DIRECTORY_SEPARATOR.'shell.php';
    }

    private function handleHqValidation(): void
    {
        try {
            $payload = $this->requireFields($this->requestPayload(), ['hq_hub_id', 'hq_token']);
            $hub = $this->validateHq((int) $payload['hq_hub_id'], (string) $payload['hq_token']);
            $state = $this->markHqValidated($hub);
            $this->writeExecutionState($this->defaultExecutionState());

            $this->json([
                'message' => 'HQ hub identity validated.',
                'state' => $state,
                'hub' => [
                    'hq_hub_id' => $hub['hq_hub_id'],
                    'relay_hub_id' => $hub['relay_hub_id'],
                    'name' => $hub['name'],
                    'deployment' => $hub['deployment'],
                    'status' => $hub['status'],
                    'app_url' => $hub['domain'],
                    'uplinks' => $hub['uplinks'],
                ],
            ]);
        } catch (RuntimeException $e) {
            $this->json(['message' => $e->getMessage()], 422);
        }
    }

    private function handleSettings(): void
    {
        try {
            $payload = $this->requestPayload();
            $settings = $this->validateSettings($payload);
            $state = $this->markSettingsCollected($settings, [
                'name' => (string) ($payload['admin_name'] ?? ''),
                'email' => (string) ($payload['admin_email'] ?? ''),
            ]);
            $this->writeExecutionState($this->defaultExecutionState());
            $this->json([
                'message' => 'Install settings saved.',
                'state' => $state,
                'settings' => $settings,
            ]);
        } catch (RuntimeException $e) {
            $this->json(['message' => $e->getMessage()], 422);
        }
    }

    private function handleExecution(): void
    {
        try {
            $this->json([
                'message' => 'Relay installation execution started.',
                'execution' => $this->startExecution(),
            ]);
        } catch (RuntimeException $e) {
            $this->json(['message' => $e->getMessage()], 422);
        }
    }

    private function handleExecutionAdvance(): void
    {
        try {
            $this->json([
                'message' => 'Relay installation execution advanced.',
                'execution' => $this->advanceExecution(),
            ]);
        } catch (RuntimeException $e) {
            $this->json(['message' => $e->getMessage()], 422);
        }
    }

    private function handleExecutionRetry(): void
    {
        try {
            $this->json([
                'message' => 'Relay installation execution retried.',
                'execution' => $this->retryExecution(),
            ]);
        } catch (RuntimeException $e) {
            $this->json(['message' => $e->getMessage()], 422);
        }
    }

    private function handleCleanup(): void
    {
        try {
            $this->json([
                'message' => 'Installer cleanup completed.',
                'cleanup' => $this->cleanup(),
            ]);
        } catch (RuntimeException $e) {
            $this->json(['message' => $e->getMessage()], 422);
        }
    }

    private function environmentChecks(): array
    {
        $checks = array_merge(
            $this->runtimeChecks(),
            $this->extensionChecks(),
            $this->archiveChecks(),
            $this->databaseChecks(),
            $this->filesystemChecks(),
        );

        $groups = [];
        $blockingFailures = 0;
        $warnings = 0;

        foreach ($checks as $check) {
            $groups[$check['group']][] = $check;
            if ($check['status'] === 'fail' && ($check['blocking'] ?? true)) {
                $blockingFailures++;
            }
            if ($check['status'] === 'warning') {
                $warnings++;
            }
        }

        return [
            'status' => $blockingFailures === 0 ? 'ready' : 'blocked',
            'can_continue' => $blockingFailures === 0,
            'summary' => [
                'total_checks' => count($checks),
                'blocking_failures' => $blockingFailures,
                'warnings' => $warnings,
                'groups' => array_keys($groups),
            ],
            'groups' => $groups,
        ];
    }

    private function runtimeChecks(): array
    {
        $minimum = '8.2.0';
        $passes = version_compare(PHP_VERSION, $minimum, '>=');

        return [[
            'key' => 'php_version',
            'group' => 'runtime',
            'label' => 'PHP Version',
            'status' => $passes ? 'pass' : 'fail',
            'message' => 'PHP '.PHP_VERSION.' detected.',
            'hint' => $passes ? null : 'Upgrade PHP to at least '.$minimum.'.',
            'blocking' => true,
        ]];
    }

    private function extensionChecks(): array
    {
        $checks = [];
        foreach (['json', 'openssl', 'mbstring', 'fileinfo', 'zip', 'pdo'] as $extension) {
            $loaded = extension_loaded($extension);
            $checks[] = [
                'key' => 'extension_'.$extension,
                'group' => 'extensions',
                'label' => strtoupper($extension).' Extension',
                'status' => $loaded ? 'pass' : 'fail',
                'message' => $loaded ? "Extension [$extension] is loaded." : "Extension [$extension] is not loaded.",
                'hint' => $loaded ? null : "Enable the PHP [$extension] extension.",
                'blocking' => true,
            ];
        }

        return $checks;
    }

    private function archiveChecks(): array
    {
        $available = class_exists(ZipArchive::class);

        return [[
            'key' => 'zip_archive',
            'group' => 'archive',
            'label' => 'ZIP Archive Support',
            'status' => $available ? 'pass' : 'fail',
            'message' => $available ? 'ZipArchive is available.' : 'ZipArchive is not available.',
            'hint' => $available ? null : 'Enable the PHP zip extension with ZipArchive support.',
            'blocking' => true,
        ]];
    }

    private function databaseChecks(): array
    {
        return [
            [
                'key' => 'pdo_mysql',
                'group' => 'database',
                'label' => 'MySQL Driver',
                'status' => extension_loaded('pdo_mysql') ? 'pass' : 'warning',
                'message' => extension_loaded('pdo_mysql') ? 'PDO MySQL is available.' : 'PDO MySQL is not available.',
                'hint' => extension_loaded('pdo_mysql') ? null : 'Enable PDO MySQL if this host will use MySQL.',
                'blocking' => false,
            ],
            [
                'key' => 'sqlite_driver',
                'group' => 'database',
                'label' => 'SQLite Driver',
                'status' => (extension_loaded('sqlite3') || extension_loaded('pdo_sqlite')) ? 'pass' : 'warning',
                'message' => (extension_loaded('sqlite3') || extension_loaded('pdo_sqlite')) ? 'SQLite support is available.' : 'SQLite support is not available.',
                'hint' => (extension_loaded('sqlite3') || extension_loaded('pdo_sqlite')) ? null : 'Enable SQLite if this host will use SQLite.',
                'blocking' => false,
            ],
        ];
    }

    private function filesystemChecks(): array
    {
        return [
            $this->pathCheck('install_root', 'filesystem', 'Install Root', $this->installRoot),
            $this->pathCheck('installer_root', 'filesystem', 'Installer Working Root', $this->bootstrapRoot),
            $this->pathCheck('env_target', 'filesystem', 'Environment File Target', $this->envPath),
            $this->pathCheck('installed_app_root', 'filesystem', 'Installed App Root', $this->installedAppRoot),
        ];
    }

    private function pathCheck(string $key, string $group, string $label, string $path): array
    {
        $parent = $this->nearestExistingParent($path);
        $ok = $parent !== null && is_writable($parent);

        return [
            'key' => $key,
            'group' => $group,
            'label' => $label,
            'status' => $ok ? 'pass' : 'fail',
            'message' => $ok ? "Path [$path] can be written." : "Path [$path] cannot be written.",
            'hint' => $ok ? null : 'Ensure a writable parent directory exists for ['.$path.'].',
            'blocking' => true,
        ];
    }

    private function validateHq(int $hubId, string $token): array
    {
        if ($hubId < 1 || strlen(trim($token)) < 20) {
            throw new RuntimeException('HQ Hub ID and assigned token are required.');
        }

        [$statusCode, $payload, $transportError] = $this->httpGetJson($this->hqApiBaseUrl.'/api/hubs/'.$hubId, [
            'Accept: application/json',
            'Authorization: Bearer '.$token,
        ]);

        if ($statusCode < 200 || $statusCode >= 300) {
            $message = is_array($payload) ? ($payload['error'] ?? $payload['message'] ?? 'Unable to validate HQ hub identity.') : 'Unable to validate HQ hub identity.';
            if ($statusCode === 0 && $transportError !== null) {
                $message .= ' Transport error: '.$transportError;
            }
            throw new RuntimeException("HQ validation failed ($statusCode): ".$message);
        }

        $hub = $payload['data']['hub'] ?? null;
        if (! is_array($hub)) {
            throw new RuntimeException('HQ returned an unexpected hub detail payload.');
        }

        $relayHubId = trim((string) ($hub['relay_hub_id'] ?? ''));
        if ($relayHubId === '') {
            throw new RuntimeException('HQ hub record does not expose a valid relay_hub_id.');
        }

        return [
            'hq_hub_id' => $hubId,
            'relay_hub_id' => $relayHubId,
            'name' => (string) ($hub['name'] ?? ''),
            'deployment' => (string) ($hub['deployment'] ?? 'other'),
            'status' => (string) ($hub['status'] ?? ''),
            'domain' => $this->normalizeUrl((string) ($hub['domain'] ?? '')),
            'hq_api_base_url' => $this->hqApiBaseUrl,
            'token' => $token,
            'uplinks' => is_array($hub['uplinks'] ?? null) ? $hub['uplinks'] : [],
            'raw_hub' => $hub,
        ];
    }

    private function validateSettings(array $payload): array
    {
        if (trim((string) ($payload['admin_name'] ?? '')) === '' || trim((string) ($payload['admin_email'] ?? '')) === '') {
            throw new RuntimeException('Admin name and admin email are required.');
        }

        $driver = (string) ($payload['database_driver'] ?? 'sqlite');
        if (! in_array($driver, ['sqlite', 'mysql'], true)) {
            throw new RuntimeException('Database driver must be mysql or sqlite.');
        }

        $settings = [
            'database_driver' => $driver,
            'database_host' => $driver === 'mysql' ? (string) ($payload['database_host'] ?? '') : null,
            'database_port' => $driver === 'mysql' ? (int) ($payload['database_port'] ?? 3306) : null,
            'database_name' => $driver === 'mysql' ? (string) ($payload['database_name'] ?? '') : null,
            'database_username' => $driver === 'mysql' ? (string) ($payload['database_username'] ?? '') : null,
            'database_password' => $driver === 'mysql' ? (string) ($payload['database_password'] ?? '') : null,
            'sqlite_path' => $driver === 'sqlite' ? (string) ($payload['sqlite_path'] ?? '') : null,
        ];

        if ($driver === 'sqlite' && trim((string) $settings['sqlite_path']) === '') {
            throw new RuntimeException('SQLite path is required when the SQLite driver is selected.');
        }

        if ($driver === 'mysql' && (
            trim((string) $settings['database_host']) === ''
            || trim((string) $settings['database_name']) === ''
            || trim((string) $settings['database_username']) === ''
        )) {
            throw new RuntimeException('MySQL host, port, database name, and username are required.');
        }

        return $settings;
    }

    private function startExecution(): array
    {
        $state = $this->executionState();

        if (($state['status'] ?? 'idle') === 'running') {
            return $state;
        }

        return $this->writeExecutionState([
            'status' => 'running',
            'started_at' => $state['started_at'] ?? date(DATE_ATOM),
            'updated_at' => date(DATE_ATOM),
            'current_step' => 'prepare_workspace',
            'last_completed_step' => null,
            'steps' => $this->defaultExecutionSteps(),
            'failure' => null,
            'retry_allowed' => false,
            'cleanup_pending' => false,
            'install_result' => null,
            'admin_credentials' => null,
        ]);
    }

    private function advanceExecution(): array
    {
        return $this->withExecutionLock(function (): array {
            $execution = $this->executionState();

            if (($execution['status'] ?? 'idle') === 'idle') {
                $execution = $this->startExecution();
            }

            if (in_array($execution['status'] ?? 'idle', ['completed', 'failed'], true)) {
                return $execution;
            }

            $step = (string) ($execution['current_step'] ?? '');
            if ($step === '') {
                return $execution;
            }

            return match ($step) {
                'prepare_workspace' => $this->runExecutionStep($step, function (): string {
                    $this->prepareWorkspace();

                    return 'Installer workspace prepared.';
                }),
                'extract_release' => $this->runExecutionStep($step, function (): string {
                    $release = $this->extractReleasePackage();
                    $result = is_array($this->executionState()['install_result'] ?? null) ? $this->executionState()['install_result'] : [];
                    $result['release'] = $release;
                    $this->storeExecutionResult($result);

                    return $release === null
                        ? 'Using current app runtime; no embedded release extraction required.'
                        : 'Embedded Relay release extracted.';
                }),
                'write_environment' => $this->runExecutionStep($step, function (): string {
                    [$hq, $settings] = $this->validatedInstallState();
                    $this->writeEnvironment($hq, $settings);

                    return 'Environment configuration written.';
                }),
                'verify_database' => $this->runExecutionStep($step, function (): string {
                    [, $settings] = $this->validatedInstallState();
                    $this->verifyDatabase($settings);

                    return 'Target database connection verified.';
                }),
                'run_migrations' => $this->runExecutionStep($step, function (): string {
                    $this->runArtisanCommand(['migrate', '--force']);

                    return 'Database migrations completed.';
                }),
                'create_admin' => $this->runExecutionStep($step, function (): string {
                    [, , $admin] = $this->validatedInstallState();
                    $password = 'relay-'.strtolower(bin2hex(random_bytes(8)));
                    $this->runArtisanCommand([
                        'relay:user:create',
                        (string) $admin['name'],
                        (string) $admin['email'],
                        $password,
                        '--role=admin',
                    ]);

                    $result = is_array($this->executionState()['install_result'] ?? null) ? $this->executionState()['install_result'] : [];
                    $result['admin'] = [
                        'email' => (string) $admin['email'],
                        'password' => $password,
                    ];
                    $this->storeExecutionResult($result);
                    $this->storeAdminCredentials($result['admin']);

                    return 'Initial Relay admin account created.';
                }),
                'write_install_lock' => $this->runExecutionStep($step, function (): string {
                    [$hq] = $this->validatedInstallState();
                    $lock = $this->writeInstallLock($hq);
                    $result = is_array($this->executionState()['install_result'] ?? null) ? $this->executionState()['install_result'] : [];
                    $result['lock'] = $lock;
                    $this->storeExecutionResult($result);

                    return 'Installed lock marker written.';
                }),
                'prepare_cleanup' => $this->runExecutionStep($step, function (): string {
                    $execution = $this->executionState();
                    $release = is_array($execution['install_result'] ?? null) ? ($execution['install_result']['release'] ?? null) : null;
                    $manifest = $this->writeCleanupManifest([
                        $this->installRoot.DIRECTORY_SEPARATOR.'installer.zip',
                        $this->bootstrapRoot,
                    ], [
                        $this->lockPath,
                        $this->envPath,
                        $this->installedAppRoot,
                    ]);

                    $result = is_array($this->executionState()['install_result'] ?? null) ? $this->executionState()['install_result'] : [];
                    $result['cleanup'] = [
                        'manifest' => $manifest,
                        'result' => null,
                        'auto_run' => false,
                    ];
                    if (! array_key_exists('release', $result)) {
                        $result['release'] = $release;
                    }
                    $this->storeExecutionResult($result);

                    return 'Installer cleanup manifest prepared.';
                }),
                'finalize_installed_state' => $this->finalizeExecutionState(),
                default => throw new RuntimeException("Unsupported installer execution step [$step]."),
            };
        });
    }

    private function retryExecution(): array
    {
        $execution = $this->executionState();
        if (($execution['status'] ?? 'idle') !== 'failed' || ! ($execution['retry_allowed'] ?? false)) {
            throw new RuntimeException('Installer execution is not in a retryable failed state.');
        }

        return $this->advanceExecution();
    }

    private function extractReleasePackage(): array
    {
        if (! is_file($this->releasePackagePath)) {
            throw new RuntimeException('Embedded Relay release package was not found.');
        }

        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('ZipArchive is required to extract the Relay release package.');
        }

        if (is_dir($this->installedAppRoot) && $this->directoryHasContents($this->installedAppRoot)) {
            throw new RuntimeException('Installed app root already contains files. Fresh install cannot overwrite an existing Relay runtime.');
        }

        $this->resetDirectory($this->installedAppRoot);

        $archive = new ZipArchive();
        if ($archive->open($this->releasePackagePath) !== true) {
            throw new RuntimeException('Embedded Relay release package could not be opened.');
        }

        if (! $archive->extractTo($this->installedAppRoot)) {
            $archive->close();
            throw new RuntimeException('Embedded Relay release package could not be extracted.');
        }

        $archive->close();

        $expected = ['app', 'bootstrap', 'config', 'database', 'public', 'resources', 'routes', 'storage', 'vendor'];
        foreach ($expected as $path) {
            if (! file_exists($this->installedAppRoot.DIRECTORY_SEPARATOR.$path)) {
                throw new RuntimeException('Embedded Relay release package is missing expected path ['.$path.'].');
            }
        }

        return [
            'package_path' => $this->releasePackagePath,
            'extract_root' => $this->installedAppRoot,
            'expected_paths' => $expected,
        ];
    }

    private function writeEnvironment(array $hq, array $settings): void
    {
        $this->ensureDirectory(dirname($this->envPath));
        $examplePath = $this->installedAppRoot.DIRECTORY_SEPARATOR.'.env.example';
        $content = is_file($examplePath) ? (string) file_get_contents($examplePath) : '';

        $values = [
            'APP_URL' => (string) ($hq['domain'] ?? ''),
            'APP_KEY' => 'base64:'.base64_encode(random_bytes(32)),
            'DB_CONNECTION' => (string) ($settings['database_driver'] ?? 'sqlite'),
            'DB_HOST' => $settings['database_host'] ?? null,
            'DB_PORT' => $settings['database_port'] ?? null,
            'DB_DATABASE' => ($settings['database_driver'] ?? 'sqlite') === 'sqlite' ? ($settings['sqlite_path'] ?? null) : ($settings['database_name'] ?? null),
            'DB_USERNAME' => $settings['database_username'] ?? null,
            'DB_PASSWORD' => $settings['database_password'] ?? null,
            'RELAY_LOCAL_HUB_ID' => $hq['relay_hub_id'] ?? null,
            'RELAY_HQ_API_ENABLED' => 'true',
            'RELAY_HQ_API_BASE_URL' => $hq['hq_api_base_url'] ?? $this->hqApiBaseUrl,
            'RELAY_HQ_API_TOKEN' => $hq['token'] ?? null,
            'RELAY_HQ_LOCAL_RELAY_HUB_ID' => $hq['relay_hub_id'] ?? null,
            'RELAY_HQ_LOCAL_HQ_ID' => $hq['hq_hub_id'] ?? null,
            'RELAY_HQ_SYNC_ENABLED' => 'true',
            'INSTALLER_ENABLED' => 'false',
        ];

        foreach ($values as $key => $value) {
            $content = $this->setEnvValue($content, $key, $this->stringifyEnvValue($value));
        }

        file_put_contents($this->envPath, $content);
    }

    private function verifyDatabase(array $settings): void
    {
        $driver = (string) ($settings['database_driver'] ?? 'sqlite');
        if ($driver === 'sqlite') {
            $path = (string) ($settings['sqlite_path'] ?? '');
            if ($path === '') {
                throw new RuntimeException('SQLite path is required.');
            }
            $this->ensureDirectory(dirname($path));
            if (! file_exists($path)) {
                file_put_contents($path, '');
            }
            new PDO('sqlite:'.$path);
            return;
        }

        new PDO(
            sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', (string) $settings['database_host'], (int) $settings['database_port'], (string) $settings['database_name']),
            (string) $settings['database_username'],
            (string) $settings['database_password']
        );
    }

    private function runArtisanCommand(array $arguments): void
    {
        $kernel = $this->installedConsoleKernel();
        $command = (string) array_shift($arguments);
        $params = $this->normalizeArtisanParameters($command, $arguments);

        $exitCode = $kernel->call($command, $params);
        $output = trim((string) $kernel->output());

        if ($exitCode !== 0) {
            throw new RuntimeException('Installer command failed: '.($output !== '' ? $output : 'Unknown console execution failure.'));
        }
    }

    private function normalizeArtisanParameters(string $command, array $arguments): array
    {
        if ($command === 'migrate') {
            return [
                '--force' => in_array('--force', $arguments, true),
            ];
        }

        if ($command === 'relay:user:create') {
            return [
                'name' => (string) ($arguments[0] ?? ''),
                'email' => (string) ($arguments[1] ?? ''),
                'password' => (string) ($arguments[2] ?? ''),
                '--role' => 'admin',
            ];
        }

        $params = [];

        foreach ($arguments as $index => $argument) {
            $params[(string) $index] = (string) $argument;
        }

        return $params;
    }

    private function installedConsoleKernel(): mixed
    {
        if ($this->installedConsoleKernel !== null) {
            return $this->installedConsoleKernel;
        }

        $autoloadPath = $this->installedAppRoot.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'autoload.php';
        $bootstrapPath = $this->installedAppRoot.DIRECTORY_SEPARATOR.'bootstrap'.DIRECTORY_SEPARATOR.'app.php';

        if (! is_file($autoloadPath) || ! is_file($bootstrapPath)) {
            throw new RuntimeException('Installed Relay runtime is incomplete. Laravel bootstrap files were not found after extraction.');
        }

        require_once $autoloadPath;
        $app = require $bootstrapPath;

        if (! is_object($app) || ! method_exists($app, 'make')) {
            throw new RuntimeException('Installed Relay application bootstrap did not return a valid Laravel application instance.');
        }

        $kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
        $kernel->bootstrap();

        $this->installedConsoleKernel = $kernel;

        return $this->installedConsoleKernel;
    }

    private function writeInstallLock(array $hq): array
    {
        $payload = [
            'installed_at' => date(DATE_ATOM),
            'relay_release_version' => '1.1.0',
            'hq_hub_id' => $hq['hq_hub_id'] ?? null,
            'relay_hub_id' => $hq['relay_hub_id'] ?? null,
            'app_url' => $hq['domain'] ?? null,
        ];

        file_put_contents($this->lockPath, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        return $payload;
    }

    private function cleanup(): array
    {
        if (! is_file($this->cleanupManifestPath)) {
            throw new RuntimeException('Cleanup manifest was not found.');
        }

        $manifest = json_decode((string) file_get_contents($this->cleanupManifestPath), true);
        if (! is_array($manifest)) {
            throw new RuntimeException('Cleanup manifest is invalid.');
        }

        $deleted = [];
        $preserve = array_values(array_filter((array) ($manifest['preserve'] ?? []), 'is_string'));

        foreach ((array) ($manifest['delete'] ?? []) as $target) {
            if (in_array($target, $preserve, true)) {
                continue;
            }

            $resolved = $this->resolveWithinCleanupRoot((string) $target);
            if (! file_exists($resolved)) {
                continue;
            }

            if (is_dir($resolved)) {
                $this->deleteDirectory($resolved);
            } else {
                @unlink($resolved);
            }

            $deleted[] = $target;
        }

        @unlink($this->cleanupManifestPath);

        return ['deleted' => $deleted];
    }

    private function finalizeExecutionState(): array
    {
        return $this->runExecutionStep('finalize_installed_state', function (): string {
            [$hq] = $this->validatedInstallState();
            $result = is_array($this->executionState()['install_result'] ?? null) ? $this->executionState()['install_result'] : [];
            $lock = is_array($result['lock'] ?? null) ? $result['lock'] : $this->writeInstallLock($hq);
            $release = $result['release'] ?? null;
            $cleanup = is_array($result['cleanup'] ?? null) ? $result['cleanup'] : ['manifest' => null, 'result' => null, 'auto_run' => false];

            $state = $this->markInstalled([
                'installed_at' => $lock['installed_at'] ?? date(DATE_ATOM),
                'app_url' => $lock['app_url'] ?? ($hq['domain'] ?? null),
                'relay_hub_id' => $lock['relay_hub_id'] ?? ($hq['relay_hub_id'] ?? null),
                'cleanup_manifest_path' => $this->cleanupManifestPath,
                'cleanup_pending' => ($cleanup['result'] ?? null) === null && ! empty($cleanup['manifest']),
                'cleanup_auto_run' => (bool) ($cleanup['auto_run'] ?? false),
                'release' => $release,
            ]);

            $payload = [
                'state' => $state,
                'lock' => $lock,
                'admin' => $this->executionState()['admin_credentials'] ?? null,
                'release' => $release,
                'cleanup' => $cleanup,
            ];

            $this->markExecutionCompleted($payload, $payload['admin']);

            return 'Finalizing installer state.';
        });
    }

    private function prepareWorkspace(): void
    {
        foreach ([$this->bootstrapRoot, dirname($this->envPath), $this->installedAppRoot] as $path) {
            $this->ensureDirectory($path);
        }
    }

    /**
     * @return array{0: array<string,mixed>,1: array<string,mixed>,2: array<string,mixed>}
     */
    private function validatedInstallState(): array
    {
        $state = $this->state();
        if (! is_array($state['hq'] ?? null) || ! is_array($state['admin'] ?? null) || ! is_array($state['settings'] ?? null)) {
            throw new RuntimeException('Installer state is incomplete. Environment, HQ identity, and settings must be completed first.');
        }

        return [$state['hq'], $state['settings'], $state['admin']];
    }

    private function runExecutionStep(string $stepKey, callable $callback): array
    {
        $this->markExecutionStepRunning($stepKey);

        try {
            $message = $callback();
        } catch (RuntimeException $e) {
            return $this->markExecutionFailed($stepKey, $e->getMessage(), ! in_array($stepKey, ['write_install_lock', 'finalize_installed_state'], true));
        }

        return $this->markExecutionStepCompleted($stepKey, is_string($message) ? $message : null);
    }

    private function executionState(): array
    {
        if (! is_file($this->executionStatePath)) {
            return $this->defaultExecutionState();
        }

        $decoded = json_decode((string) file_get_contents($this->executionStatePath), true);

        return is_array($decoded) ? array_merge($this->defaultExecutionState(), $decoded) : $this->defaultExecutionState();
    }

    private function defaultExecutionState(): array
    {
        return [
            'status' => 'idle',
            'started_at' => null,
            'updated_at' => date(DATE_ATOM),
            'current_step' => null,
            'last_completed_step' => null,
            'steps' => $this->defaultExecutionSteps(),
            'failure' => null,
            'retry_allowed' => false,
            'cleanup_pending' => false,
            'install_result' => null,
            'admin_credentials' => null,
        ];
    }

    private function defaultExecutionSteps(): array
    {
        return [
            ['key' => 'prepare_workspace', 'label' => 'Prepare Workspace', 'status' => 'pending', 'message' => null, 'pending_message' => 'Preparing installer workspace.'],
            ['key' => 'extract_release', 'label' => 'Extract Release', 'status' => 'pending', 'message' => null, 'pending_message' => 'Extracting embedded Relay release package.'],
            ['key' => 'write_environment', 'label' => 'Write Environment', 'status' => 'pending', 'message' => null, 'pending_message' => 'Writing environment configuration.'],
            ['key' => 'verify_database', 'label' => 'Verify Database', 'status' => 'pending', 'message' => null, 'pending_message' => 'Verifying target database connectivity.'],
            ['key' => 'run_migrations', 'label' => 'Run Migrations', 'status' => 'pending', 'message' => null, 'pending_message' => 'Applying database migrations.'],
            ['key' => 'create_admin', 'label' => 'Create Admin', 'status' => 'pending', 'message' => null, 'pending_message' => 'Creating the initial Relay admin account.'],
            ['key' => 'write_install_lock', 'label' => 'Write Install Lock', 'status' => 'pending', 'message' => null, 'pending_message' => 'Writing the installed lock marker.'],
            ['key' => 'prepare_cleanup', 'label' => 'Prepare Cleanup', 'status' => 'pending', 'message' => null, 'pending_message' => 'Preparing installer cleanup targets.'],
            ['key' => 'finalize_installed_state', 'label' => 'Finalize Installed State', 'status' => 'pending', 'message' => null, 'pending_message' => 'Finalizing installer state.'],
        ];
    }

    private function writeExecutionState(array $state): array
    {
        $this->ensureDirectory(dirname($this->executionStatePath));
        file_put_contents($this->executionStatePath, json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        return $state;
    }

    private function markExecutionStepRunning(string $stepKey): array
    {
        $state = $this->executionState();
        $steps = $state['steps'];

        foreach ($steps as &$step) {
            if (($step['key'] ?? null) === $stepKey) {
                $step['status'] = 'running';
                $step['message'] = $step['pending_message'] ?? null;
            }
        }

        return $this->writeExecutionState(array_merge($state, [
            'status' => 'running',
            'updated_at' => date(DATE_ATOM),
            'current_step' => $stepKey,
            'failure' => null,
            'retry_allowed' => false,
            'steps' => $steps,
        ]));
    }

    private function markExecutionStepCompleted(string $stepKey, ?string $message = null): array
    {
        $state = $this->executionState();
        $steps = $state['steps'];
        $nextStep = null;

        foreach ($steps as $index => &$step) {
            if (($step['key'] ?? null) === $stepKey) {
                $step['status'] = 'completed';
                $step['message'] = $message ?? ($step['pending_message'] ?? null);
                $nextStep = $steps[$index + 1]['key'] ?? null;
            }
        }

        return $this->writeExecutionState(array_merge($state, [
            'status' => $nextStep === null ? 'completed' : 'running',
            'updated_at' => date(DATE_ATOM),
            'current_step' => $nextStep,
            'last_completed_step' => $stepKey,
            'steps' => $steps,
        ]));
    }

    private function markExecutionFailed(string $stepKey, string $message, bool $retryAllowed): array
    {
        $state = $this->executionState();
        $steps = $state['steps'];

        foreach ($steps as &$step) {
            if (($step['key'] ?? null) === $stepKey) {
                $step['status'] = 'failed';
                $step['message'] = $message;
            }
        }

        return $this->writeExecutionState(array_merge($state, [
            'status' => 'failed',
            'updated_at' => date(DATE_ATOM),
            'current_step' => $stepKey,
            'steps' => $steps,
            'failure' => [
                'step' => $stepKey,
                'message' => $message,
                'detail' => $message,
            ],
            'retry_allowed' => $retryAllowed,
        ]));
    }

    private function markExecutionCompleted(array $result, ?array $adminCredentials): array
    {
        $state = $this->executionState();

        return $this->writeExecutionState(array_merge($state, [
            'status' => 'completed',
            'updated_at' => date(DATE_ATOM),
            'current_step' => null,
            'failure' => null,
            'retry_allowed' => false,
            'cleanup_pending' => ! empty($result['cleanup']['manifest'] ?? null),
            'install_result' => $result,
            'admin_credentials' => $adminCredentials,
        ]));
    }

    private function storeExecutionResult(array $result): void
    {
        $state = $this->executionState();
        $state['install_result'] = $result;
        $state['updated_at'] = date(DATE_ATOM);
        $this->writeExecutionState($state);
    }

    private function storeAdminCredentials(array $credentials): void
    {
        $state = $this->executionState();
        $state['admin_credentials'] = $credentials;
        $state['updated_at'] = date(DATE_ATOM);
        $this->writeExecutionState($state);
    }

    private function withExecutionLock(callable $callback): array
    {
        $this->ensureDirectory(dirname($this->executionLockPath));
        $handle = fopen($this->executionLockPath, 'c+');
        if (! is_resource($handle)) {
            throw new RuntimeException('Installer execution lock could not be opened.');
        }

        try {
            if (! flock($handle, LOCK_EX)) {
                throw new RuntimeException('Installer execution lock could not be acquired.');
            }

            return $callback();
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    private function httpGetJson(string $url, array $headers): array
    {
        $curlError = null;

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_TIMEOUT, 20);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_USERAGENT, 'PBB Relay Installer/1.0');
            $body = curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $curlError = $body === false ? curl_error($ch) : null;
            curl_close($ch);

            if ($body !== false && $status > 0) {
                return [$status, json_decode((string) $body, true), null];
            }
        }

        $streamError = null;
        $previousError = error_get_last();
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => implode("\r\n", $headers),
                'ignore_errors' => true,
                'timeout' => 20,
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);

        $body = @file_get_contents($url, false, $context);
        $status = 0;
        foreach ($http_response_header ?? [] as $header) {
            if (preg_match('/HTTP\/\S+\s+(\d+)/', $header, $matches) === 1) {
                $status = (int) $matches[1];
                break;
            }
        }

        if ($body === false) {
            $lastError = error_get_last();
            if ($lastError !== null && $lastError !== $previousError) {
                $streamError = (string) ($lastError['message'] ?? '');
            }
        }

        return [
            $status,
            json_decode((string) $body, true),
            $streamError ?: $curlError,
        ];
    }

    private function requestPayload(): array
    {
        $content = file_get_contents('php://input') ?: '';
        $decoded = json_decode($content, true);
        return is_array($decoded) ? $decoded : $_POST;
    }

    private function requireFields(array $payload, array $fields): array
    {
        foreach ($fields as $field) {
            if (trim((string) ($payload[$field] ?? '')) === '') {
                throw new RuntimeException('Missing required field ['.$field.'].');
            }
        }

        return $payload;
    }

    private function normalizeUrl(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        return preg_match('/^https?:\/\//i', $value) === 1 ? $value : 'https://'.$value;
    }

    private function state(): array
    {
        if (! is_file($this->statePath)) {
            return $this->defaultState();
        }

        $decoded = json_decode((string) file_get_contents($this->statePath), true);
        return is_array($decoded) ? array_merge($this->defaultState(), $decoded) : $this->defaultState();
    }

    private function defaultState(): array
    {
        $timestamp = date(DATE_ATOM);
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

    private function markEnvironmentChecked(array $summary): array
    {
        return $this->writeState([
            'status' => 'environment_checked',
            'current_step' => 'environment',
            'environment_summary' => $summary,
        ]);
    }

    private function markHqValidated(array $hq): array
    {
        return $this->writeState([
            'status' => 'hq_validated',
            'current_step' => 'hq_identity',
            'hq' => $hq,
        ]);
    }

    private function markSettingsCollected(array $settings, array $admin): array
    {
        return $this->writeState([
            'status' => 'settings_collected',
            'current_step' => 'install_settings',
            'settings' => $settings,
            'admin' => $admin,
        ]);
    }

    private function markInstalled(array $summary): array
    {
        return $this->writeState([
            'status' => 'installed',
            'current_step' => 'installed',
            'install_summary' => $summary,
        ]);
    }

    private function writeState(array $changes): array
    {
        $state = array_merge($this->state(), $changes, ['updated_at' => date(DATE_ATOM)]);
        $this->ensureDirectory(dirname($this->statePath));
        file_put_contents($this->statePath, json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        return $state;
    }

    private function writeCleanupManifest(array $delete, array $preserve): array
    {
        $manifest = [
            'delete' => array_values(array_unique($delete)),
            'preserve' => array_values(array_unique($preserve)),
        ];
        $this->ensureDirectory(dirname($this->cleanupManifestPath));
        file_put_contents($this->cleanupManifestPath, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        return $manifest;
    }

    private function setEnvValue(string $content, string $key, string $value): string
    {
        $pattern = '/^'.preg_quote($key, '/').'=.*$/m';
        $line = $key.'='.$value;

        if (preg_match($pattern, $content) === 1) {
            return (string) preg_replace($pattern, $line, $content);
        }

        if ($content !== '' && ! str_ends_with($content, "\n")) {
            $content .= PHP_EOL;
        }

        return $content.$line.PHP_EOL;
    }

    private function stringifyEnvValue(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        $string = (string) $value;
        return ($string === '' || preg_match('/\s/', $string) === 1)
            ? '"'.str_replace('"', '\"', $string).'"'
            : $string;
    }

    private function ensureDirectory(string $directory): void
    {
        if (! is_dir($directory) && ! mkdir($directory, 0777, true) && ! is_dir($directory)) {
            throw new RuntimeException('Required directory ['.$directory.'] could not be created.');
        }
    }

    private function nearestExistingParent(string $path): ?string
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

    private function resolveWithinCleanupRoot(string $path): string
    {
        $candidate = preg_match('/^[A-Za-z]:\\\\|^\//', $path) === 1
            ? $path
            : $this->cleanupRoot.DIRECTORY_SEPARATOR.ltrim($path, DIRECTORY_SEPARATOR);

        $root = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $this->cleanupRoot), DIRECTORY_SEPARATOR);
        $candidate = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $candidate);

        if (! ($candidate === $root || str_starts_with($candidate, $root.DIRECTORY_SEPARATOR))) {
            throw new RuntimeException('Cleanup target ['.$path.'] is outside the allowed cleanup root.');
        }

        return $candidate;
    }

    private function resetDirectory(string $directory): void
    {
        if (is_dir($directory)) {
            $this->deleteDirectory($directory);
        }

        mkdir($directory, 0777, true);
    }

    private function deleteDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            $path = $item->getPathname();
            if ($item->isDir()) {
                @rmdir($path);
            } else {
                @chmod($path, 0777);
                @unlink($path);
            }
        }

        @rmdir($directory);
    }

    private function directoryHasContents(string $directory): bool
    {
        if (! is_dir($directory)) {
            return false;
        }

        $items = scandir($directory);
        return is_array($items) && count(array_diff($items, ['.', '..'])) > 0;
    }

    private function json(array $payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
