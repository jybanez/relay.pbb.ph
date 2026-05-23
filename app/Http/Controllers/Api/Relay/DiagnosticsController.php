<?php

namespace App\Http\Controllers\Api\Relay;

use App\Http\Controllers\Controller;
use App\Relay\Diagnostics\RelayDiagnosticsService;
use Illuminate\Http\JsonResponse;

class DiagnosticsController extends Controller
{
    public function __construct(
        private RelayDiagnosticsService $diagnosticsService,
    ) {}

    /**
     * Get full diagnostics information
     */
    public function index(): JsonResponse
    {
        return response()->json($this->diagnosticsService->getDiagnostics());
    }

    /**
     * Get a lightweight relay status payload for hub heartbeat polling.
     */
    public function status(): JsonResponse
    {
        return response()->json($this->diagnosticsService->getStatusSnapshot());
    }

    /**
     * Get compatibility information for hub-to-hub communication
     */
    public function compatibility(): JsonResponse
    {
        $versionInfo = $this->diagnosticsService->getVersionInfo();
        $healthStatus = $this->diagnosticsService->getHealthStatus();

        return response()->json([
            'version' => $versionInfo,
            'health' => $healthStatus,
            'supported_auth_modes' => ['shared_key', 'hmac', 'mtls', 'mtls_hmac'],
            'supported_protocol_versions' => config('relay.version.supported_protocols', [(string) config('relay.version.protocol', '1.0')]),
            'relay_protocol_capabilities' => config('relay.version.capabilities', []),
            'api_endpoints' => [
                'local_api' => '/api/v1/',
                'hub_to_hub_api' => '/api/v1/receive',
            ],
        ]);
    }
}
