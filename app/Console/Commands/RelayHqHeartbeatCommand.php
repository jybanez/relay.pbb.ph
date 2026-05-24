<?php

namespace App\Console\Commands;

use App\Relay\HqHeartbeat\RelayHqHeartbeatService;
use Illuminate\Console\Command;

class RelayHqHeartbeatCommand extends Command
{
    protected $signature = 'relay:hq-heartbeat {--once : Send one heartbeat and exit}';

    protected $description = 'Send Relay install-level heartbeat to Hub HQ and hydrate public hub snapshot';

    public function handle(RelayHqHeartbeatService $heartbeat): int
    {
        do {
            $result = $heartbeat->heartbeat();

            if ($result['sent']) {
                $this->info('Relay HQ heartbeat accepted.');
            } else {
                $this->warn('Relay HQ heartbeat not sent: '.($result['error'] ?? 'unknown error'));
            }

            if ($this->option('once')) {
                return $result['sent'] ? self::SUCCESS : self::FAILURE;
            }

            sleep((int) config('relay.hq_heartbeat.interval_seconds', 60));
        } while (true);
    }
}
