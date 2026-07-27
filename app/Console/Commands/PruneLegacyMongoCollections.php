<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PruneLegacyMongoCollections extends Command
{
    protected $signature = 'capstone:prune-legacy-mongo
                            {--dry-run : List collections that would be dropped without deleting}';

    protected $description = 'Drop unused legacy MongoDB collections left over from schema cleanup';

    /**
     * @var list<string>
     */
    private array $legacyCollections = [
        'offense_sanctions',
    ];

    public function handle(): int
    {
        $database = DB::connection('mongodb')->getMongoDB();
        $dryRun = (bool) $this->option('dry-run');

        $this->info('Legacy MongoDB collection cleanup');
        $this->newLine();

        $dropped = 0;

        foreach ($this->legacyCollections as $name) {
            $exists = false;

            foreach ($database->listCollections(['filter' => ['name' => $name]]) as $collection) {
                $exists = true;
                break;
            }

            if (! $exists) {
                $this->line("  <fg=gray>{$name}</> — not present, skipped");

                continue;
            }

            if ($dryRun) {
                $this->line("  <fg=yellow>{$name}</> — would drop");
                $dropped++;

                continue;
            }

            $database->selectCollection($name)->drop();
            $this->line("  <fg=green>{$name}</> — dropped");
            $dropped++;
        }

        $this->newLine();

        if ($dryRun) {
            $this->comment("Dry run complete. {$dropped} collection(s) would be removed.");
        } else {
            $this->info("Done. {$dropped} legacy collection(s) removed.");
        }

        return self::SUCCESS;
    }
}
