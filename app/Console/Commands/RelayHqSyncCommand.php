<?php

namespace App\Console\Commands;

use App\Relay\Registry\HqHubRegistrySyncService;
use Illuminate\Console\Command;
use Throwable;

class RelayHqSyncCommand extends Command
{
    protected $signature = 'relay:hq-sync';

    protected $description = 'Sync canonical hub identity and topology from the HQ hub registry';

    public function handle(HqHubRegistrySyncService $syncService): int
    {
        try {
            $result = $syncService->sync();

            $this->info('HQ hub registry sync completed.');
            $this->line('Synced hubs: ' . $result['synced_hubs']);
            $this->line('Synced links: ' . $result['synced_links']);
            $this->line('Local Relay hub ID: ' . ($result['local_relay_hub_id'] ?? 'not configured'));
            $this->line('Local HQ hub ID: ' . ($result['local_hq_id'] ?? 'not configured'));

            return self::SUCCESS;
        } catch (Throwable $e) {
            $syncService->markFailed($e);
            $this->error('HQ hub registry sync failed: ' . $e->getMessage());

            return self::FAILURE;
        }
    }
}
