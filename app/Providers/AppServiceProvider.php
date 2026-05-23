<?php

namespace App\Providers;

use App\Relay\Maestro\HttpRelayMaestroTelemetry;
use App\Relay\Maestro\NullRelayMaestroTelemetry;
use App\Relay\Maestro\RelayMaestroTelemetry;
use App\Relay\Maestro\RelayQueueTelemetry;
use App\Relay\Maestro\RelayWorkerIdentity;
use App\Relay\Registry\HqHubRegistryClient;
use App\Relay\Registry\HqHubRegistrySyncService;
use App\Relay\Registry\RelayNodeIdentityService;
use App\Relay\Registry\RelayPeerResolver;
use App\Relay\Envelope\RelayEnvelopeValidator;
use App\Relay\Delivery\RelayRetryPolicy;
use App\Relay\Auth\RelayHubTransportAuth;
use App\Relay\Handlers\LocalHandlerDispatchService;
use App\Relay\Outbound\RelaySubmissionService;
use App\Relay\Outbound\RelayDeliveryService;
use App\Relay\Outbound\RelayForwardingTopologyService;
use App\Relay\Inbound\RelayReceiveService;
use App\Relay\Inbound\RelayIdempotencyService;
use App\Relay\Diagnostics\RelayDiagnosticsService;
use App\Relay\Transport\RelayHttpSender;
use App\Relay\Transport\RelayTargetResolver;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\Looping;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Register Relay services
        $this->app->singleton(RelayEnvelopeValidator::class);
        $this->app->singleton(RelayDiagnosticsService::class);
        $this->app->singleton(RelayIdempotencyService::class);
        $this->app->singleton(RelayHubTransportAuth::class);
        $this->app->singleton(HqHubRegistryClient::class);
        $this->app->singleton(HqHubRegistrySyncService::class);
        $this->app->singleton(LocalHandlerDispatchService::class);
        $this->app->singleton(RelayRetryPolicy::class);
        $this->app->singleton(RelayNodeIdentityService::class);
        $this->app->singleton(RelayPeerResolver::class);
        $this->app->singleton(RelayTargetResolver::class);
        $this->app->singleton(RelayHttpSender::class);
        $this->app->singleton(RelayDeliveryService::class);
        $this->app->singleton(RelayForwardingTopologyService::class);
        $this->app->singleton(RelayWorkerIdentity::class);
        $this->app->singleton(RelayMaestroTelemetry::class, function ($app) {
            $enabled = (bool) config('relay.maestro.enabled', false);
            $baseUrl = config('relay.maestro.base_url');
            $token = config('relay.maestro.telemetry_token');

            if (! $enabled || ! is_string($baseUrl) || $baseUrl === '' || ! is_string($token) || $token === '') {
                return new NullRelayMaestroTelemetry();
            }

            return new HttpRelayMaestroTelemetry(
                $app->make(\Illuminate\Http\Client\Factory::class),
                $app->make(RelayWorkerIdentity::class),
            );
        });
        $this->app->singleton(RelayQueueTelemetry::class);

        $this->app->bind(RelaySubmissionService::class, function ($app) {
            return new RelaySubmissionService(
                $app->make(RelayEnvelopeValidator::class),
                $app->make(RelayForwardingTopologyService::class),
            );
        });

        $this->app->bind(RelayReceiveService::class, function ($app) {
            return new RelayReceiveService(
                $app->make(RelayEnvelopeValidator::class),
                $app->make(RelayIdempotencyService::class),
                $app->make(LocalHandlerDispatchService::class),
                $app->make(RelayForwardingTopologyService::class),
                $app->make(RelayNodeIdentityService::class),
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Schema::defaultStringLength(191);

        if (! $this->app->runningInConsole()) {
            return;
        }

        $command = $_SERVER['argv'][1] ?? null;
        $workerCommands = ['queue:work', 'queue:listen', 'relay:work'];

        if (! in_array($command, $workerCommands, true)) {
            return;
        }

        /** @var RelayQueueTelemetry $queueTelemetry */
        $queueTelemetry = $this->app->make(RelayQueueTelemetry::class);
        $queueTelemetry->bootWorker();

        Queue::before(function (JobProcessing $event) use ($queueTelemetry): void {
            $queueTelemetry->handleJobProcessing($event);
        });

        Queue::after(function (JobProcessed $event) use ($queueTelemetry): void {
            $queueTelemetry->handleJobProcessed($event);
        });

        Queue::failing(function (JobFailed $event) use ($queueTelemetry): void {
            $queueTelemetry->handleJobFailed($event);
        });

        Queue::looping(function (Looping $event) use ($queueTelemetry): void {
            $queueTelemetry->heartbeatIfDue();
        });
    }
}
