<?php

namespace App\Relay\Diagnostics;

use App\Models\HubRelayDelivery;
use App\Models\HubRelayMessage;
use App\Models\HubRelayReceipt;
use App\Relay\Registry\RelayNodeIdentityService;

/**
 * RelayDiagnosticsService
 *
 * Provides system health, version, and status information.
 */
class RelayDiagnosticsService
{
    public function __construct(
        private RelayNodeIdentityService $nodeIdentity,
    ) {}

    /**
     * Get relay package and protocol version
     */
    public function getVersionInfo(): array
    {
        return [
            'relay_package_version' => (string) config('relay.version.package', '1.1.0'),
            'relay_protocol_version' => (string) config('relay.version.protocol', '1.0'),
            'minimum_supported_protocol_version' => (string) config('relay.version.minimum_supported_protocol', '1.0'),
            'php_version' => phpversion(),
            'laravel_version' => app()->version(),
        ];
    }

    /**
     * Get relay diagnostic summary
     */
    public function getDiagnostics(): array
    {
        return [
            'version' => $this->getVersionInfo(),
            'queue_status' => $this->getQueueStatus(),
            'delivery_summary' => $this->getDeliverySummary(),
            'inbox_summary' => $this->getInboxSummary(),
            'timestamp' => now()->toIso8601String(),
        ];
    }

    /**
     * Get a lightweight status payload for heartbeat polling.
     */
    public function getStatusSnapshot(): array
    {
        $version = $this->getVersionInfo();
        $health = $this->getHealthStatus();
        $queueStatus = $this->getQueueStatus();
        $inboxSummary = $this->getInboxSummary();

        return [
            'status' => $health['status'],
            'hub_id' => (string) ($this->nodeIdentity->localHubId() ?? ''),
            'timestamp' => now()->toIso8601String(),
            'version' => [
                'relay_package_version' => $version['relay_package_version'],
                'relay_protocol_version' => $version['relay_protocol_version'],
                'minimum_supported_protocol_version' => $version['minimum_supported_protocol_version'],
            ],
            'health' => $health,
            'queue' => [
                'queued' => $queueStatus['total_queued'],
                'failed' => $queueStatus['failed_deliveries'],
                'dead' => $queueStatus['dead_letter_deliveries'],
                'total_messages' => $queueStatus['total_messages'],
                'total_deliveries' => $queueStatus['total_deliveries'],
            ],
            'inbox' => [
                'total_receipts' => $inboxSummary['total_receipts'],
            ],
        ];
    }

    /**
     * Get outbound queue status
     */
    public function getQueueStatus(): array
    {
        return [
            'total_queued' => HubRelayDelivery::where('status', HubRelayDelivery::STATUS_QUEUED)->count(),
            'total_messages' => HubRelayMessage::count(),
            'total_deliveries' => HubRelayDelivery::count(),
            'failed_deliveries' => HubRelayDelivery::where('status', HubRelayDelivery::STATUS_FAILED)->count(),
            'dead_letter_deliveries' => HubRelayDelivery::where('status', HubRelayDelivery::STATUS_DEAD)->count(),
        ];
    }

    /**
     * Get delivery summary by target
     */
    public function getDeliverySummary(): array
    {
        return [
            'by_status' => [
                'queued' => HubRelayDelivery::where('status', HubRelayDelivery::STATUS_QUEUED)->count(),
                'sending' => HubRelayDelivery::where('status', HubRelayDelivery::STATUS_SENDING)->count(),
                'delivered' => HubRelayDelivery::where('status', HubRelayDelivery::STATUS_DELIVERED)->count(),
                'failed' => HubRelayDelivery::where('status', HubRelayDelivery::STATUS_FAILED)->count(),
                'dead' => HubRelayDelivery::where('status', HubRelayDelivery::STATUS_DEAD)->count(),
            ],
            'by_target_hub' => $this->getDeliveriesByTargetHub(),
        ];
    }

    /**
     * Get deliveries grouped by target hub
     */
    private function getDeliveriesByTargetHub(): array
    {
        $deliveries = HubRelayDelivery::query()
            ->groupBy('target_hub_id', 'status')
            ->selectRaw('target_hub_id, status, count(*) as count')
            ->get()
            ->groupBy('target_hub_id');

        $result = [];
        foreach ($deliveries as $hubId => $statuses) {
            $result[$hubId] = [];
            foreach ($statuses as $statusGroup) {
                $result[$hubId][$statusGroup->status] = $statusGroup->count;
            }
        }
        return $result;
    }

    /**
     * Get inbound inbox summary
     */
    public function getInboxSummary(): array
    {
        return [
            'total_receipts' => HubRelayReceipt::count(),
            'by_status' => [
                'received' => HubRelayReceipt::where('status', HubRelayReceipt::STATUS_RECEIVED)->count(),
                'processed' => HubRelayReceipt::where('status', HubRelayReceipt::STATUS_PROCESSED)->count(),
                'duplicate' => HubRelayReceipt::where('status', HubRelayReceipt::STATUS_DUPLICATE)->count(),
                'rejected' => HubRelayReceipt::where('status', HubRelayReceipt::STATUS_REJECTED)->count(),
            ],
            'by_source_hub' => $this->getReceiptsBySourceHub(),
        ];
    }

    /**
     * Get receipts grouped by source hub
     */
    private function getReceiptsBySourceHub(): array
    {
        $receipts = HubRelayReceipt::query()
            ->groupBy('source_hub_id', 'status')
            ->selectRaw('source_hub_id, status, count(*) as count')
            ->get()
            ->groupBy('source_hub_id');

        $result = [];
        foreach ($receipts as $hubId => $statuses) {
            $result[$hubId] = [];
            foreach ($statuses as $statusGroup) {
                $result[$hubId][$statusGroup->status] = $statusGroup->count;
            }
        }
        return $result;
    }

    /**
     * Check overall system health
     */
    public function getHealthStatus(): array
    {
        $deadLetterCount = HubRelayDelivery::where('status', HubRelayDelivery::STATUS_DEAD)->count();
        $queuedCount = HubRelayDelivery::where('status', HubRelayDelivery::STATUS_QUEUED)->count();

        $status = 'healthy';
        if ($deadLetterCount > 100 || $queuedCount > 1000) {
            $status = 'degraded';
        }
        if ($deadLetterCount > 500) {
            $status = 'unhealthy';
        }

        return [
            'status' => $status,
            'dead_letter_count' => $deadLetterCount,
            'queued_count' => $queuedCount,
            'last_check' => now()->toIso8601String(),
        ];
    }
}
