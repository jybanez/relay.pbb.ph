<?php

namespace App\Relay\Maestro;

interface RelayMaestroTelemetry
{
    public function workerStarted(?string $queueName = null): void;

    public function workerHeartbeat(?string $queueName = null): void;

    public function jobStarted(string $queueName, ?string $jobType, string|int|null $jobId): void;

    public function jobCompleted(string $queueName, ?string $jobType, string|int|null $jobId): void;

    public function jobFailed(string $queueName, ?string $jobType, string|int|null $jobId, ?string $error = null): void;
}
