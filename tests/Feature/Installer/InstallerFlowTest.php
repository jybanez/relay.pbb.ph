<?php

namespace Tests\Feature\Installer;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InstallerFlowTest extends TestCase
{
    use RefreshDatabase;

    private string $statePath;
    private string $lockPath;
    private string $bootstrapRoot;
    private string $envPath;
    private string $sqlitePath;
    private string $releasePackagePath;
    private string $releaseExtractRoot;
    private string $cleanupManifestPath;
    private string $cleanupRoot;
    private string $executionStatePath;
    private string $executionLockPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->statePath = storage_path('framework/testing/installer-state.json');
        $this->lockPath = storage_path('framework/testing/relay-installed.lock');
        $this->envPath = storage_path('framework/testing/installer.env');
        $this->sqlitePath = storage_path('framework/testing/installer-relay.sqlite');
        $this->cleanupManifestPath = storage_path('framework/testing/installer-cleanup.json');
        $this->cleanupRoot = storage_path('framework/testing/installer-cleanup-root');
        $this->bootstrapRoot = $this->cleanupRoot.DIRECTORY_SEPARATOR.'installer-root';
        $this->releasePackagePath = $this->cleanupRoot.DIRECTORY_SEPARATOR.'installer-release.zip';
        $this->releaseExtractRoot = $this->cleanupRoot.DIRECTORY_SEPARATOR.'installer-release';
        $this->executionStatePath = storage_path('framework/testing/installer-execution-state.json');
        $this->executionLockPath = storage_path('framework/testing/installer-execution.lock');

        File::delete($this->statePath);
        File::delete($this->lockPath);
        File::delete($this->envPath);
        File::delete($this->sqlitePath);
        File::delete($this->releasePackagePath);
        File::delete($this->cleanupManifestPath);
        File::delete($this->executionStatePath);
        File::delete($this->executionLockPath);
        File::deleteDirectory($this->bootstrapRoot);
        File::deleteDirectory($this->releaseExtractRoot);
        File::deleteDirectory($this->cleanupRoot);
        File::ensureDirectoryExists($this->cleanupRoot);

        config([
            'installer.enabled' => true,
            'installer.state_path' => $this->statePath,
            'installer.execution_state_path' => $this->executionStatePath,
            'installer.execution_lock_path' => $this->executionLockPath,
            'installer.lock_path' => $this->lockPath,
            'installer.bootstrap_root' => $this->bootstrapRoot,
            'installer.env_path' => $this->envPath,
            'installer.release_package_path' => '',
            'installer.release_extract_root' => $this->releaseExtractRoot,
            'installer.cleanup_manifest_path' => $this->cleanupManifestPath,
            'installer.cleanup_root' => $this->cleanupRoot,
            'installer.cleanup_auto_run' => false,
        ]);
    }

    protected function tearDown(): void
    {
        File::delete($this->statePath);
        File::delete($this->lockPath);
        File::delete($this->envPath);
        File::delete($this->sqlitePath);
        File::delete($this->releasePackagePath);
        File::delete($this->cleanupManifestPath);
        File::delete($this->executionStatePath);
        File::delete($this->executionLockPath);
        File::deleteDirectory($this->bootstrapRoot);
        File::deleteDirectory($this->releaseExtractRoot);
        File::deleteDirectory($this->cleanupRoot);

        parent::tearDown();
    }

    public function test_root_renders_installer_shell_when_installer_mode_is_enabled(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Fresh Host Setup')
            ->assertSee('Environment Check');
    }

    public function test_environment_endpoint_returns_grouped_checks(): void
    {
        $this->getJson('/install/api/environment')
            ->assertOk()
            ->assertJsonStructure([
                'status',
                'can_continue',
                'summary' => ['total_checks', 'blocking_failures', 'warnings', 'groups'],
                'groups',
            ]);
    }

    public function test_environment_endpoint_allows_creatable_nested_installer_paths(): void
    {
        $nestedRoot = $this->cleanupRoot.DIRECTORY_SEPARATOR.'fresh-root';

        config([
            'installer.bootstrap_root' => $nestedRoot.DIRECTORY_SEPARATOR.'.installer',
            'installer.env_path' => $nestedRoot.DIRECTORY_SEPARATOR.'.relay'.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'.env',
        ]);

        File::ensureDirectoryExists($nestedRoot);

        $response = $this->getJson('/install/api/environment');

        $response->assertOk()
            ->assertJsonPath('status', 'ready')
            ->assertJsonPath('can_continue', true);
    }

    public function test_continue_environment_persists_environment_checked_state(): void
    {
        $response = $this->postJson('/install/api/environment/continue');

        $response->assertOk()
            ->assertJsonPath('state.status', 'environment_checked')
            ->assertJsonPath('state.current_step', 'environment');

        $this->assertTrue(File::exists($this->statePath));

        $state = json_decode((string) File::get($this->statePath), true);

        $this->assertSame('environment_checked', $state['status'] ?? null);
        $this->assertSame('environment', $state['current_step'] ?? null);
        $this->assertIsArray($state['environment_summary'] ?? null);
    }

    public function test_install_routes_are_hidden_when_installer_mode_is_disabled(): void
    {
        config(['installer.enabled' => false]);

        $this->get('/install')->assertNotFound();
    }

    public function test_hq_validation_persists_hq_validated_state(): void
    {
        Http::fake([
            'https://hub.pbb.ph/api/hubs/10' => Http::response([
                'status' => true,
                'data' => [
                    'hub' => [
                        'id' => 10,
                        'relay_hub_id' => '072217043',
                        'name' => 'Lusaran, CEBU CITY, CEBU',
                        'deployment' => 'barangay',
                        'domain' => 'lusaran.cebu.cebu.relay.pbb.ph',
                        'status' => 'active',
                        'token' => [
                            'has_token' => true,
                            'is_active' => true,
                        ],
                        'uplinks' => [
                            [
                                'uplink_domain' => 'cebu.cebu.relay.pbb.ph',
                                'hub' => [
                                    'name' => 'CEBU CITY, CEBU',
                                    'domain' => 'cebu.cebu.relay.pbb.ph',
                                ],
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $this->postJson('/install/api/hq/validate', [
            'hq_hub_id' => 10,
            'hq_token' => '9N995BbN2k6YJkkNp9NrzY40ijh6g7qeczC99QN4EYkpdro61kmega19JaSMiSSu',
        ])
            ->assertOk()
            ->assertJsonPath('state.status', 'hq_validated')
            ->assertJsonPath('hub.relay_hub_id', '072217043')
            ->assertJsonPath('hub.app_url', 'https://lusaran.cebu.cebu.relay.pbb.ph');

        $state = json_decode((string) File::get($this->statePath), true);

        $this->assertSame('hq_validated', $state['status'] ?? null);
        $this->assertSame('072217043', data_get($state, 'hq.relay_hub_id'));
        $this->assertNull(data_get($state, 'admin.name'));
        $this->assertNull(data_get($state, 'admin.email'));
    }

    public function test_hq_validation_returns_error_when_hq_rejects_the_request(): void
    {
        Http::fake([
            'https://hub.pbb.ph/api/hubs/10' => Http::response([
                'status' => false,
                'error' => 'Hub token required.',
            ], 401),
        ]);

        $this->postJson('/install/api/hq/validate', [
            'hq_hub_id' => 10,
            'hq_token' => 'invalid-token-value-invalid-token-value',
        ])->assertStatus(422);
    }

    public function test_settings_persistence_supports_sqlite(): void
    {
        $response = $this->postJson('/install/api/settings', [
            'admin_name' => 'Relay Admin',
            'admin_email' => 'admin@lusaran.relay.local',
            'database_driver' => 'sqlite',
            'sqlite_path' => base_path('database/lusaran.sqlite'),
        ]);

        $response->assertOk()
            ->assertJsonPath('state.status', 'settings_collected')
            ->assertJsonPath('settings.database_driver', 'sqlite');

        $state = json_decode((string) File::get($this->statePath), true);
        $this->assertSame('settings_collected', $state['status'] ?? null);
        $this->assertSame(base_path('database/lusaran.sqlite'), data_get($state, 'settings.sqlite_path'));
        $this->assertSame('Relay Admin', data_get($state, 'admin.name'));
        $this->assertSame('admin@lusaran.relay.local', data_get($state, 'admin.email'));
    }

    public function test_settings_persistence_supports_mysql(): void
    {
        $response = $this->postJson('/install/api/settings', [
            'admin_name' => 'Relay Admin',
            'admin_email' => 'admin@lusaran.relay.local',
            'database_driver' => 'mysql',
            'database_host' => 'localhost',
            'database_port' => 3306,
            'database_name' => 'pbb_relay',
            'database_username' => 'root',
            'database_password' => 'secret',
        ]);

        $response->assertOk()
            ->assertJsonPath('state.status', 'settings_collected')
            ->assertJsonPath('settings.database_driver', 'mysql')
            ->assertJsonPath('settings.database_name', 'pbb_relay');
    }

    public function test_execute_installation_writes_env_migrates_sqlite_and_creates_admin(): void
    {
        $this->seedReadyInstallerState();

        $start = $this->postJson('/install/api/execute');
        $start->assertOk()
            ->assertJsonPath('execution.status', 'running');

        $response = $this->advanceExecutionToTerminal();

        $response->assertOk()
            ->assertJsonPath('execution.status', 'completed')
            ->assertJsonPath('execution.install_result.state.status', 'installed')
            ->assertJsonPath('execution.install_result.admin.email', 'admin@lusaran.relay.local')
            ->assertJsonPath('execution.install_result.lock.relay_hub_id', '072217043')
            ->assertJsonPath('execution.install_result.cleanup.auto_run', false)
            ->assertJsonPath('execution.install_result.cleanup.manifest.delete.0', $this->bootstrapRoot);

        $this->assertTrue(File::exists($this->lockPath));
        $this->assertTrue(File::exists($this->envPath));
        $this->assertTrue(File::exists($this->sqlitePath));
        $this->assertTrue(File::exists($this->cleanupManifestPath));

        $envContent = (string) File::get($this->envPath);
        $this->assertStringContainsString('RELAY_LOCAL_HUB_ID=072217043', $envContent);
        $this->assertStringContainsString('RELAY_HQ_LOCAL_HQ_ID=10', $envContent);
        $this->assertStringContainsString('INSTALLER_ENABLED=false', $envContent);

        $pdo = new \PDO('sqlite:'.$this->sqlitePath);
        $count = (int) $pdo->query("select count(*) from users where email = 'admin@lusaran.relay.local'")->fetchColumn();
        $this->assertSame(1, $count);

        $this->get('/install')->assertNotFound();
    }

    public function test_execute_installation_extracts_release_package_when_configured(): void
    {
        if (! class_exists(\ZipArchive::class)) {
            $this->markTestSkipped('ZipArchive is required for release extraction tests.');
        }

        $this->seedReadyInstallerState();
        $this->createReleasePackage($this->releasePackagePath);

        config([
            'installer.release_package_path' => $this->releasePackagePath,
        ]);

        $this->postJson('/install/api/execute')->assertOk();
        $response = $this->advanceExecutionToTerminal();

        $response->assertOk()
            ->assertJsonPath('execution.status', 'completed')
            ->assertJsonPath('execution.install_result.release.package_path', $this->releasePackagePath)
            ->assertJsonPath('execution.install_result.state.install_summary.release.package_path', $this->releasePackagePath);

        foreach ((array) config('installer.release_expected_paths') as $path) {
            $this->assertTrue(File::exists($this->releaseExtractRoot.DIRECTORY_SEPARATOR.$path));
        }

        $manifest = json_decode((string) File::get($this->cleanupManifestPath), true);

        $this->assertContains($this->releaseExtractRoot, $manifest['delete'] ?? []);
        $this->assertContains($this->releasePackagePath, $manifest['delete'] ?? []);
        $this->assertContains($this->bootstrapRoot, $manifest['delete'] ?? []);
    }

    public function test_cleanup_endpoint_removes_configured_installer_artifacts_within_cleanup_root(): void
    {
        File::ensureDirectoryExists($this->cleanupRoot);
        File::ensureDirectoryExists($this->bootstrapRoot);
        File::put($this->bootstrapRoot.DIRECTORY_SEPARATOR.'runtime.txt', 'bootstrap');
        File::put($this->cleanupRoot.DIRECTORY_SEPARATOR.'installer.zip', 'zip');

        app(\App\Installer\InstallerCleanupService::class)->createManifest([
            $this->bootstrapRoot,
            $this->cleanupRoot.DIRECTORY_SEPARATOR.'installer.zip',
        ], [
            $this->lockPath,
            $this->envPath,
        ]);

        $this->postJson('/install/api/cleanup')
            ->assertOk()
            ->assertJsonPath('cleanup.deleted.0', $this->bootstrapRoot)
            ->assertJsonPath('cleanup.deleted.1', $this->cleanupRoot.DIRECTORY_SEPARATOR.'installer.zip');

        $this->assertDirectoryDoesNotExist($this->bootstrapRoot);
        $this->assertFileDoesNotExist($this->cleanupRoot.DIRECTORY_SEPARATOR.'installer.zip');
        $this->assertFileDoesNotExist($this->cleanupManifestPath);
    }

    public function test_root_returns_normal_app_when_install_lock_exists(): void
    {
        File::put($this->lockPath, json_encode(['installed_at' => now()->toIso8601String()]));

        $this->get('/')
            ->assertOk()
            ->assertSee('Public Relay Status')
            ->assertDontSee('Fresh Host Setup');
    }

    private function seedReadyInstallerState(): void
    {
        app(\App\Installer\InstallerStateStore::class)->markEnvironmentChecked([
            'total_checks' => 1,
            'blocking_failures' => 0,
            'warnings' => 0,
            'groups' => ['runtime'],
        ]);

        app(\App\Installer\InstallerStateStore::class)->markHqValidated([
            'hq_hub_id' => 10,
            'relay_hub_id' => '072217043',
            'name' => 'Lusaran, CEBU CITY, CEBU',
            'deployment' => 'barangay',
            'status' => 'active',
            'domain' => 'https://lusaran.cebu.cebu.relay.pbb.ph',
            'hq_api_base_url' => 'https://hub.pbb.ph',
            'token' => 'hq-installer-token',
            'uplinks' => [],
            'raw_hub' => [],
        ], [
            'name' => 'Relay Admin',
            'email' => 'admin@lusaran.relay.local',
        ]);

        app(\App\Installer\InstallerStateStore::class)->markSettingsCollected([
            'database_driver' => 'sqlite',
            'database_host' => null,
            'database_port' => null,
            'database_name' => null,
            'database_username' => null,
            'database_password' => null,
            'sqlite_path' => $this->sqlitePath,
        ]);
    }

    private function advanceExecutionToTerminal(): \Illuminate\Testing\TestResponse
    {
        for ($attempt = 0; $attempt < 12; $attempt++) {
            $response = $this->postJson('/install/api/execute/advance');
            $response->assertOk();
            $status = $response->json('execution.status');

            if (in_array($status, ['completed', 'failed'], true)) {
                return $response;
            }
        }

        $this->fail('Installer execution did not reach a terminal state within the expected number of steps. Last response: '.$response->getContent());
    }

    private function createReleasePackage(string $path): void
    {
        File::ensureDirectoryExists(dirname($path));

        $archive = new \ZipArchive();
        $opened = $archive->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

        $this->assertTrue($opened === true, 'Release package zip could not be created for the installer test.');

        foreach ((array) config('installer.release_expected_paths') as $expectedPath) {
            $archive->addEmptyDir($expectedPath);
            $archive->addFromString($expectedPath.'/.keep', 'installer-test');
        }

        $archive->close();
    }
}
