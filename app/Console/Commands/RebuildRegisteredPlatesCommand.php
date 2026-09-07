<?php

namespace App\Console\Commands;

use App\Services\RegisteredPlateService;
use Illuminate\Console\Command;

class RebuildRegisteredPlatesCommand extends Command
{
    protected $signature = 'plates:rebuild';

    protected $description = 'Rebuild registered_plates scan table from user_vehicles, users, and visitors';

    public function handle(RegisteredPlateService $plates): int
    {
        $this->info('Rebuilding registered_plates…');
        $count = $plates->rebuild();
        $this->info("Synced {$count} plate(s) for AI / gate scanning.");

        return self::SUCCESS;
    }
}
