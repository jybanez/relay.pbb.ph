<?php

namespace App\Http\Controllers;

use App\Models\HubRelayClient;
use App\Models\HubRelayDelivery;
use App\Models\HubRelayHandler;
use App\Models\HubRelayReceipt;
use App\Relay\Diagnostics\RelayDiagnosticsService;
use Illuminate\Contracts\View\View;

class RelayPublicHomeController extends Controller
{
    public function __construct(
        private RelayDiagnosticsService $diagnostics,
    ) {}

    public function __invoke(): View
    {
        $diagnostics = $this->diagnostics->getDiagnostics();
        $health = $this->diagnostics->getHealthStatus();

        return view('relay.public-home', [
            'appName' => config('app.name'),
            'appUrl' => config('app.url'),
            'diagnostics' => $diagnostics,
            'health' => $health,
            'activeClientsCount' => HubRelayClient::query()->where('is_active', true)->count(),
            'activeHandlersCount' => HubRelayHandler::query()->where('is_active', true)->count(),
            'deadDeliveriesCount' => HubRelayDelivery::query()->where('status', HubRelayDelivery::STATUS_DEAD)->count(),
            'totalReceiptsCount' => HubRelayReceipt::query()->count(),
        ]);
    }
}
