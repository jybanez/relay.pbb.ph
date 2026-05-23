<?php

namespace App\Relay\Maestro;

use Carbon\CarbonImmutable;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;

class RelayQueueTelemetry
{
    private bool $workerStarted = false;

    private ?CarbonImmutable $lastHeartbeatAt = null;

    private ?string $lastQueueName = null;

    public function __construct(
        private RelayMaestroTelemetry $telemetry,
    ) {}

    public function bootWorker(?string $queueName = null): void
    {
        if ($this->workerStarted) {
            return;
        }

        $this->workerStarted = true;
        $this->lastQueueName = $queueName;
        $this->telemetry->workerStarted($queueName);
        $this->lastHeartbeatAt = CarbonImmutable::now();
    }

    public function handleJobProcessing(JobProcessing $event): void
    {
        $queueName = $event->job->getQueue();

        $this->bootWorker($queueName);
        $this->lastQueueName = $queueName;
        $this->telemetry->jobStarted(
            $queueName,
            $event->job->resolveName(),
            $this->resolveJobId($event->job)
        );
        $this->lastHeartbeatAt = CarbonImmutable::now();
    }

    public function handleJobProcessed(JobProcessed $event): void
    {
        $queueName = $event->job->getQueue();

        $this->bootWorker($queueName);
        $this->lastQueueName = $queueName;
        $this->telemetry->jobCompleted(
            $queueName,
            $event->job->resolveName(),
            $this->resolveJobId($event->job)
        );
        $this->lastHeartbeatAt = CarbonImmutable::now();
    }

    public function handleJobFailed(JobFailed $event): void
    {
        $queueName = $event->job->getQueue();

        $this->bootWorker($queueName);
        $this->lastQueueName = $queueName;
        $this->telemetry->jobFailed(
            $queueName,
            $event->job->resolveName(),
            $this->resolveJobId($event->job),
            $event->exception->getMessage()
        );
        $this->lastHeartbeatAt = CarbonImmutable::now();
    }

    public function heartbeatIfDue(): void
    {
        if (! $this->workerStarted) {
            return;
        }

        $intervalSeconds = (int) config('relay.maestro.heartbeat_interval_seconds', 15);

        if ($intervalSeconds <= 0) {
            $intervalSeconds = 15;
        }

        $now = CarbonImmutable::now();

        if ($this->lastHeartbeatAt !== null && $this->lastHeartbeatAt->diffInSeconds($now) < $intervalSeconds) {
            return;
        }

        $this->telemetry->workerHeartbeat($this->lastQueueName);
        $this->lastHeartbeatAt = $now;
    }

    private function resolveJobId(object $job): string|int|null
    {
        if (method_exists($job, 'uuid')) {
            $uuid = $job->uuid();

            if (is_string($uuid) && $uuid !== '') {
                return $uuid;
            }
        }

        if (method_exists($job, 'getJobId')) {
            return $job->getJobId();
        }

        return null;
    }
}
