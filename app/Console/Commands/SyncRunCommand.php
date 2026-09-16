<?php

namespace App\Console\Commands;

use App\Services\Sync\AtlasSyncService;
use Illuminate\Console\Command;

class SyncRunCommand extends Command
{
    protected $signature = 'sync:run';

    protected $description = 'Push pending local records to MongoDB Atlas and pull records from other devices (no-op unless SYNC_ENABLED=true)';

    public function handle(AtlasSyncService $sync): int
    {
        $result = $sync->syncAll();

        if (isset($result['skipped'])) {
            $this->line("[sync] skipped: {$result['skipped']}");

            return self::SUCCESS;
        }

        foreach ($result as $modelClass => $stats) {
            if (isset($stats['error'])) {
                $this->error("[sync] {$modelClass}: FAILED — {$stats['error']}");

                continue;
            }

            $this->info(sprintf(
                '[sync] %s: pushed=%d pulled=%d conflicts=%d',
                $modelClass,
                $stats['pushed'] ?? 0,
                $stats['pulled'] ?? 0,
                $stats['conflicts'] ?? 0,
            ));
        }

        return self::SUCCESS;
    }
}
