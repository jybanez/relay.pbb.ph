<?php

namespace App\Relay\Maestro;

use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class HttpRelayMaestroTelemetry implements RelayMaestroTelemetry
{
    private bool $started = false;

    private int $processedCount = 0;

    private int $failedCount = 0;

    private ?string $currentJobType = null;

    private string|int|null $currentJobId = null;

    public function __construct(
        private HttpFactory $http,
        private RelayWorkerIdentity $identity,
    ) {}

    public function workerStarted(?string $queueName = null): void
    {
        if ($this->started) {
            return;
        }

        $this->started = true;

        $this->postEvent('worker.started', $queueName, null, null, 'success', 'Relay worker process started.');
        $this->workerHeartbeat($queueName);
    }

    public function workerHeartbeat(?string $queueName = null): void
    {
        $this->postHeartbeat($queueName, $this->currentJobType === null ? 'idle' : 'busy');
    }

    public function jobStarted(string $queueName, ?string $jobType, string|int|null $jobId): void
    {
        $this->workerStarted($queueName);

        $this->currentJobType = $jobType;
        $this->currentJobId = $jobId;

        $this->postEvent('job.started', $queueName, $jobType, $jobId, 'success', 'Relay queue job started.');
        $this->postHeartbeat($queueName, 'busy');
    }

    public function jobCompleted(string $queueName, ?string $jobType, string|int|null $jobId): void
    {
        $this->workerStarted($queueName);

        $this->processedCount++;
        $this->currentJobType = null;
        $this->currentJobId = null;

        $this->postEvent('job.completed', $queueName, $jobType, $jobId, 'success', 'Relay queue job completed.');
        $this->postHeartbeat($queueName, 'idle');
    }

    public function jobFailed(string $queueName, ?string $jobType, string|int|null $jobId, ?string $error = null): void
    {
        $this->workerStarted($queueName);

        $this->failedCount++;
        $this->currentJobType = null;
        $this->currentJobId = null;

        $this->postEvent('job.failed', $queueName, $jobType, $jobId, 'failed', $error ?: 'Relay queue job failed.');
        $this->postHeartbeat($queueName, 'idle');
    }

    private function postHeartbeat(?string $queueName, string $status): void
    {
        $this->send(config('relay.maestro.heartbeat_path'), [
            'app_code' => (string) config('relay.maestro.app_code', 'relay'),
            'worker_id' => $this->identity->workerId(),
            'host_name' => $this->identity->hostName(),
            'queue_name' => $queueName,
            'process_id' => $this->identity->processId(),
            'status' => $status,
            'started_at' => $this->identity->startedAt()->toIso8601String(),
            'last_heartbeat_at' => now()->toIso8601String(),
            'current_job_type' => $this->currentJobType,
            'current_job_id' => $this->normalizeJobId($this->currentJobId),
            'processed_count' => $this->processedCount,
            'failed_count' => $this->failedCount,
            'memory_mb' => round(memory_get_usage(true) / 1048576, 2),
            'meta' => [
                'relay_package_version' => (string) config('relay.version.package'),
                'relay_protocol_version' => (string) config('relay.version.protocol'),
                'queue_connection' => (string) config('queue.default'),
                'heartbeat_interval_seconds' => (int) config('relay.maestro.heartbeat_interval_seconds', 15),
            ],
        ]);
    }

    private function postEvent(
        string $eventType,
        ?string $queueName,
        ?string $jobType,
        string|int|null $jobId,
        string $outcome,
        string $notes
    ): void {
        $this->send(config('relay.maestro.events_path'), [
            'event_id' => (string) Str::uuid(),
            'app_code' => (string) config('relay.maestro.app_code', 'relay'),
            'worker_id' => $this->identity->workerId(),
            'event_type' => $eventType,
            'queue_name' => $queueName,
            'job_type' => $jobType,
            'job_id' => $this->normalizeJobId($jobId),
            'outcome' => $outcome,
            'notes' => $notes,
            'occurred_at' => now()->toIso8601String(),
            'payload' => [
                'process_id' => $this->identity->processId(),
                'host_name' => $this->identity->hostName(),
            ],
        ]);
    }

    private function send(?string $path, array $payload): void
    {
        $baseUrl = config('relay.maestro.base_url');
        $token = config('relay.maestro.telemetry_token');

        if (! is_string($baseUrl) || $baseUrl === '' || ! is_string($path) || $path === '' || ! is_string($token) || $token === '') {
            return;
        }

        try {
            $this->http
                ->acceptJson()
                ->asJson()
                ->withHeaders([
                    'X-Telemetry-Token' => $token,
                ])
                ->withOptions([
                    'verify' => $this->tlsVerifyOption(),
                ])
                ->connectTimeout((int) config('relay.maestro.connect_timeout_seconds', 3))
                ->timeout((int) config('relay.maestro.timeout_seconds', 5))
                ->post(rtrim($baseUrl, '/') . '/' . ltrim($path, '/'), $payload)
                ->throw();
        } catch (Throwable $e) {
            Log::warning('Relay Maestro telemetry send failed.', [
                'message' => $e->getMessage(),
                'path' => $path,
                'worker_id' => $this->identity->workerId(),
            ]);
        }
    }

    private function normalizeJobId(string|int|null $jobId): string|int|null
    {
        if ($jobId === null || $jobId === '') {
            return null;
        }

        return $jobId;
    }

    private function tlsVerifyOption(): bool|string
    {
        if (! (bool) config('relay.maestro.tls_verify', true)) {
            return false;
        }

        $caBundle = config('relay.maestro.ca_bundle');
        if (is_string($caBundle) && trim($caBundle) !== '' && is_file($caBundle)) {
            return $caBundle;
        }

        return true;
    }
}
