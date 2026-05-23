<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class RelayMaestroProbeJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $probeId,
        public readonly bool $shouldFail = false,
    ) {}

    public function handle(): void
    {
        Log::info('Relay Maestro probe job executed.', [
            'probe_id' => $this->probeId,
            'should_fail' => $this->shouldFail,
        ]);

        if ($this->shouldFail) {
            throw new \RuntimeException(sprintf(
                'Relay Maestro probe job forced failure for %s.',
                $this->probeId
            ));
        }
    }
}
