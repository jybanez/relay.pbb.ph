<?php

namespace App\Http\Controllers\Installer;

use App\Http\Controllers\Controller;
use App\Installer\EnvironmentCheckService;
use App\Installer\HqInstallerValidationService;
use App\Installer\InstallerCleanupService;
use App\Installer\InstallerExecutionRunner;
use App\Installer\InstallerExecutionStateStore;
use App\Installer\InstallerMode;
use App\Installer\InstallerStateStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Contracts\View\View;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class InstallerController extends Controller
{
    public function __construct(
        private InstallerMode $installerMode,
        private InstallerStateStore $stateStore,
        private InstallerExecutionStateStore $executionState,
        private EnvironmentCheckService $environmentChecks,
        private HqInstallerValidationService $hqValidation,
        private InstallerExecutionRunner $execution,
        private InstallerCleanupService $cleanup,
    ) {}

    public function show(): View
    {
        $this->abortUnlessInstallerMode();

        return view('installer.shell', [
            'appName' => config('app.name'),
            'installerState' => $this->stateStore->current(),
            'executionState' => $this->executionState->current(),
        ]);
    }

    public function environment(): JsonResponse
    {
        $this->abortUnlessInstallerMode();

        return response()->json($this->environmentChecks->run());
    }

    public function continueEnvironment(Request $request): JsonResponse
    {
        $this->abortUnlessInstallerMode();

        $result = $this->environmentChecks->run();

        if (! ($result['can_continue'] ?? false)) {
            return response()->json([
                'message' => 'Environment checks must pass before continuing.',
                'checks' => $result,
            ], 422);
        }

        $state = $this->stateStore->markEnvironmentChecked($result['summary'] ?? []);
        $this->executionState->reset();

        return response()->json([
            'message' => 'Environment checks accepted.',
            'state' => $state,
        ]);
    }

    public function validateHq(Request $request): JsonResponse
    {
        $this->abortUnlessInstallerMode();

        $validated = $request->validate([
            'hq_hub_id' => ['required', 'integer', 'min:1'],
            'hq_token' => ['required', 'string', 'min:20'],
        ]);

        try {
            $result = $this->hqValidation->validate(
                (int) $validated['hq_hub_id'],
                (string) $validated['hq_token'],
            );
        } catch (RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }

        $state = $this->stateStore->markHqValidated($result);
        $this->executionState->reset();

        return response()->json([
            'message' => 'HQ hub identity validated.',
            'state' => $state,
            'hub' => [
                'hq_hub_id' => $result['hq_hub_id'],
                'relay_hub_id' => $result['relay_hub_id'],
                'name' => $result['name'],
                'deployment' => $result['deployment'],
                'status' => $result['status'],
                'app_url' => $result['domain'],
                'uplinks' => $result['uplinks'],
            ],
        ]);
    }

    public function saveSettings(Request $request): JsonResponse
    {
        $this->abortUnlessInstallerMode();

        $validated = $request->validate([
            'admin_name' => ['required', 'string', 'max:255'],
            'admin_email' => ['required', 'email', 'max:255'],
            'database_driver' => ['required', 'in:mysql,sqlite'],
            'database_host' => ['nullable', 'string', 'max:255'],
            'database_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'database_name' => ['nullable', 'string', 'max:255'],
            'database_username' => ['nullable', 'string', 'max:255'],
            'database_password' => ['nullable', 'string', 'max:255'],
            'sqlite_path' => ['nullable', 'string', 'max:500'],
        ]);

        if ($validated['database_driver'] === 'sqlite' && blank($validated['sqlite_path'] ?? null)) {
            return response()->json([
                'message' => 'SQLite path is required when the SQLite driver is selected.',
            ], 422);
        }

        if ($validated['database_driver'] === 'mysql') {
            foreach (['database_host', 'database_port', 'database_name', 'database_username'] as $field) {
                if (blank($validated[$field] ?? null)) {
                    return response()->json([
                        'message' => 'MySQL host, port, database name, and username are required.',
                    ], 422);
                }
            }
        }

        $settings = [
            'database_driver' => (string) $validated['database_driver'],
            'database_host' => $validated['database_driver'] === 'mysql' ? (string) ($validated['database_host'] ?? '') : null,
            'database_port' => $validated['database_driver'] === 'mysql' ? (int) ($validated['database_port'] ?? 3306) : null,
            'database_name' => $validated['database_driver'] === 'mysql' ? (string) ($validated['database_name'] ?? '') : null,
            'database_username' => $validated['database_driver'] === 'mysql' ? (string) ($validated['database_username'] ?? '') : null,
            'database_password' => $validated['database_driver'] === 'mysql' ? (string) ($validated['database_password'] ?? '') : null,
            'sqlite_path' => $validated['database_driver'] === 'sqlite' ? (string) ($validated['sqlite_path'] ?? '') : null,
        ];

        $state = $this->stateStore->markSettingsCollected($settings, [
            'name' => (string) $validated['admin_name'],
            'email' => (string) $validated['admin_email'],
        ]);
        $this->executionState->reset();

        return response()->json([
            'message' => 'Install settings saved.',
            'state' => $state,
            'settings' => $settings,
        ]);
    }

    public function execute(): JsonResponse
    {
        $this->abortUnlessInstallerMode();

        try {
            $result = $this->execution->start();
        } catch (RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'message' => 'Relay installation execution started.',
            'execution' => $result,
        ]);
    }

    public function executionProgress(): JsonResponse
    {
        $this->abortUnlessExecutionVisible();

        return response()->json([
            'execution' => $this->execution->progress(),
        ]);
    }

    public function advanceExecution(): JsonResponse
    {
        $this->abortUnlessExecutionVisible();

        try {
            $result = $this->execution->advance();
        } catch (RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'message' => 'Relay installation execution advanced.',
            'execution' => $result,
        ]);
    }

    public function retryExecution(): JsonResponse
    {
        $this->abortUnlessExecutionVisible();

        try {
            $result = $this->execution->retry();
        } catch (RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'message' => 'Relay installation execution retried.',
            'execution' => $result,
        ]);
    }

    public function cleanup(): JsonResponse
    {
        $this->abortUnlessCleanupMode();

        try {
            $result = $this->cleanup->cleanup();
        } catch (RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'message' => 'Installer cleanup completed.',
            'cleanup' => $result,
        ]);
    }

    private function abortUnlessInstallerMode(): void
    {
        if (! $this->installerMode->shouldServeInstaller()) {
            throw new NotFoundHttpException();
        }
    }

    private function abortUnlessCleanupMode(): void
    {
        if (! $this->cleanup->hasManifest()) {
            throw new NotFoundHttpException();
        }
    }

    private function abortUnlessExecutionVisible(): void
    {
        $executionStatus = (string) ($this->executionState->current()['status'] ?? 'idle');

        if ($this->installerMode->shouldServeInstaller() || $executionStatus !== 'idle') {
            return;
        }

        throw new NotFoundHttpException();
    }
}
