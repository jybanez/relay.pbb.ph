<?php

namespace App\Relay\Maestro;

use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

class RelayWorkerIdentity
{
    private readonly CarbonImmutable $startedAt;

    private readonly string $workerId;

    public function __construct()
    {
        $this->startedAt = CarbonImmutable::now();

        $host = $this->hostName();
        $pid = $this->processId();
        $suffix = Str::lower(Str::random(6));

        $this->workerId = sprintf(
            '%s:%s:%s:%s',
            $host,
            $pid,
            $this->startedAt->toIso8601String(),
            $suffix
        );
    }

    public function workerId(): string
    {
        return $this->workerId;
    }

    public function hostName(): string
    {
        $host = gethostname();

        return is_string($host) && $host !== '' ? $host : 'unknown-host';
    }

    public function processId(): int
    {
        return (int) getmypid();
    }

    public function startedAt(): CarbonImmutable
    {
        return $this->startedAt;
    }
}
