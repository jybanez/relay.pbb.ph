<?php

namespace App\Relay\HqHeartbeat;

use App\Installer\HubSnapshotWriter;
use App\Models\HubRelayDelivery;
use App\Models\HubRelayHandlerDispatch;
use App\Relay\Registry\RelayNodeIdentityService;
use Illuminate\Support\Facades\Log;
use Throwable;

class RelayHqHeartbeatService
{
    public function __construct(
        private RelayHqHeartbeatClient $client,
        private HubSnapshotWriter $snapshotWriter,
        private RelayNodeIdentityService $identity,
    ) {}

    /**
     * @return array{sent:bool,snapshot_written:bool,error:?string}
     */
    public function heartbeat(): array
    {
        if (! (bool) config('relay.hq_heartbeat.enabled', false)) {
            return [
                'sent' => false,
                'snapshot_written' => false,
                'error' => 'HQ heartbeat is disabled.',
            ];
        }

        try {
            $response = $this->client->send($this->payload());
            $this->snapshotWriter->writeForHeartbeat(
                public_path('hub.json'),
                $response['hub'],
                $response['snapshot_version'],
                $response['snapshot_hash'],
            );

            Log::info('Relay HQ heartbeat accepted.', [
                'snapshot_version' => $response['snapshot_version'],
            ]);

            return [
                'sent' => true,
                'snapshot_written' => true,
                'error' => null,
            ];
        } catch (Throwable $e) {
            Log::warning('Relay HQ heartbeat failed.', [
                'error' => $e->getMessage(),
            ]);

            return [
                'sent' => false,
                'snapshot_written' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        $currentSnapshot = $this->currentSnapshot();

        return [
            'schema_version' => 1,
            'app' => 'pbb-relay',
            'version' => (string) config('relay.version.package', '1.1.0'),
            'build_id' => $this->buildId(),
            'relay_hub_id' => $this->identity->localHubId(),
            'hub_id' => $this->numericHubId(),
            'app_url' => rtrim((string) config('app.url'), '/'),
            'heartbeat_at' => now()->toIso8601String(),
            'services' => [
                'queue_worker' => [
                    'service_id' => 'pbb-relay-worker',
                    'status' => 'unknown',
                ],
                'hq_heartbeat' => [
                    'service_id' => 'pbb-relay-hq-heartbeat',
                    'status' => 'running',
                ],
            ],
            'health' => $this->health(),
            'current_snapshot' => $currentSnapshot === [] ? null : $currentSnapshot,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function health(): array
    {
        try {
            $queuedDeliveries = HubRelayDelivery::query()
                ->whereIn('status', [HubRelayDelivery::STATUS_QUEUED, HubRelayDelivery::STATUS_SENDING])
                ->count();
            $failedDeliveries = HubRelayDelivery::query()
                ->where('status', HubRelayDelivery::STATUS_FAILED)
                ->count();
            $deadDeliveries = HubRelayDelivery::query()
                ->where('status', HubRelayDelivery::STATUS_DEAD)
                ->count();
            $queuedHandlers = HubRelayHandlerDispatch::query()
                ->whereIn('status', [HubRelayHandlerDispatch::STATUS_QUEUED, HubRelayHandlerDispatch::STATUS_SENDING])
                ->count();
            $failedHandlers = HubRelayHandlerDispatch::query()
                ->whereIn('status', [HubRelayHandlerDispatch::STATUS_FAILED, HubRelayHandlerDispatch::STATUS_DEAD])
                ->count();

            return [
                'status' => ($deadDeliveries > 0 || $failedHandlers > 0) ? 'degraded' : 'healthy',
                'queued_deliveries' => $queuedDeliveries,
                'failed_deliveries' => $failedDeliveries,
                'dead_deliveries' => $deadDeliveries,
                'queued_handler_dispatches' => $queuedHandlers,
                'failed_handler_dispatches' => $failedHandlers,
            ];
        } catch (Throwable $e) {
            Log::warning('Relay HQ heartbeat health summary failed.', [
                'error' => $e->getMessage(),
            ]);

            return [
                'status' => 'unhealthy',
                'queued_deliveries' => 0,
                'failed_deliveries' => 0,
                'dead_deliveries' => 0,
                'queued_handler_dispatches' => 0,
                'failed_handler_dispatches' => 0,
            ];
        }
    }

    private function numericHubId(): ?int
    {
        $hubId = $this->identity->localHqId();

        return is_string($hubId) && ctype_digit($hubId) ? (int) $hubId : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function currentSnapshot(): array
    {
        $path = public_path('hub.json');
        if (! is_file($path)) {
            return [];
        }

        $json = json_decode((string) file_get_contents($path), true);
        if (! is_array($json)) {
            return [];
        }

        return array_filter([
            'hash' => is_string($json['snapshot_hash'] ?? null) ? $json['snapshot_hash'] : $this->snapshotWriter->snapshotHash($json),
            'hydrated_at' => is_string($json['hydrated_at'] ?? null) ? $json['hydrated_at'] : null,
            'snapshot_version' => is_string($json['snapshot_version'] ?? null) ? $json['snapshot_version'] : null,
        ], static fn (mixed $value): bool => $value !== null);
    }

    private function buildId(): ?string
    {
        $releasePath = base_path('release.json');
        if (! is_file($releasePath)) {
            return null;
        }

        $release = json_decode((string) file_get_contents($releasePath), true);
        if (! is_array($release)) {
            return null;
        }

        $buildId = $release['build']['id'] ?? null;

        return is_string($buildId) && $buildId !== '' ? $buildId : null;
    }
}
