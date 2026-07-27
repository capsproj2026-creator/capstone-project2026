<?php

namespace App\Console\Commands;

use App\Models\Department;
use App\Models\GateLog;
use App\Models\GeneralInformation;
use App\Models\Notification;
use App\Models\ParkingArea;
use App\Models\ParkingRule;
use App\Models\ParkingSlot;
use App\Models\StalledVehicle;
use App\Models\User;
use App\Models\UserRole;
use App\Models\UserSuspension;
use App\Models\Vehicle;
use App\Models\ViolationLog;
use App\Models\ViolationSanction;
use App\Models\ViolationType;
use App\Services\SequenceService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ImportMysqlToMongo extends Command
{
    protected $signature = 'capstone:import-mysql
                            {--fresh : Drop MongoDB collections before importing}
                            {--chunk=500 : Rows per batch for large tables}';

    protected $description = 'Import data from MySQL (capstone.sql) into MongoDB collections';

  /**
     * @var array<string, class-string>
     */
    private array $tables = [
        'user_roles' => UserRole::class,
        'departments' => Department::class,
        'vehicles' => Vehicle::class,
        'parking_areas' => ParkingArea::class,
        'users' => User::class,
        'general_informations' => GeneralInformation::class,
        'parking_rules' => ParkingRule::class,
        'violation_types' => ViolationType::class,
        'violation_sanctions' => ViolationSanction::class,
        'stalled_vehicles' => StalledVehicle::class,
        'parking_slots' => ParkingSlot::class,
        'notifications' => Notification::class,
        'gate_logs' => GateLog::class,
        'violations_log' => ViolationLog::class,
        'user_suspensions' => UserSuspension::class,
    ];

    public function handle(): int
    {
        $this->info('Capstone MySQL → MongoDB import');
        $this->newLine();

        try {
            DB::connection('mysql_import')->getPdo();
        } catch (\Throwable $e) {
            $this->error('Cannot connect to MySQL. Import capstone.sql into MySQL first, then set MYSQL_IMPORT_* in .env');
            $this->line('  '.$e->getMessage());

            return self::FAILURE;
        }

        if ($this->option('fresh')) {
            $this->warn('Dropping existing MongoDB collections...');
            $this->dropMongoCollections();
        }

        $chunk = (int) $this->option('chunk');

        foreach ($this->tables as $table => $modelClass) {
            $this->importTable($table, $modelClass, $chunk);
        }

        $this->line('  Syncing ID counters...');
        SequenceService::syncCountersForModels(array_values($this->tables));
        $this->newLine();
        $this->info('Import complete. MongoDB database: '.config('database.connections.mongodb.database'));

        return self::SUCCESS;
    }

    private function importTable(string $table, string $modelClass, int $chunk): void
    {
        $total = DB::connection('mysql_import')->table($table)->count();

        if ($total === 0) {
            $this->line("  <fg=gray>{$table}</> — skipped (empty)");

            return;
        }

        $bar = $this->output->createProgressBar($total);
        $bar->setFormat("  %message% [%bar%] %percent:3s%%");
        $bar->setMessage($table);
        $bar->start();

        DB::connection('mysql_import')
            ->table($table)
            ->orderBy('id')
            ->chunk($chunk, function ($rows) use ($modelClass, $bar) {
                foreach ($rows as $row) {
                    $data = $this->normalizeRow((array) $row);
                    $modelClass::query()->updateOrCreate(
                        ['id' => $data['id']],
                        $data
                    );
                    $bar->advance();
                }
            });

        $bar->finish();
        $this->newLine();
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function normalizeRow(array $row): array
    {
        foreach (['created_at', 'timestamp', 'suspended_until', 'log_date'] as $dateField) {
            if (isset($row[$dateField]) && $row[$dateField] !== null && ! $row[$dateField] instanceof Carbon) {
                $row[$dateField] = Carbon::parse($row[$dateField]);
            }
        }

        if (array_key_exists('is_read', $row)) {
            $row['is_read'] = (bool) $row['is_read'];
        }

        if (array_key_exists('is_suspended', $row)) {
            $row['is_suspended'] = (bool) $row['is_suspended'];
        }

        if (array_key_exists('strike_count', $row) && $row['strike_count'] !== null) {
            $row['strike_count'] = (int) $row['strike_count'];
        }

        if (array_key_exists('vehicle_id', $row) && $row['vehicle_id'] === '') {
            $row['vehicle_id'] = null;
        }

        if (array_key_exists('parked_user_id', $row) && $row['parked_user_id'] === '') {
            $row['parked_user_id'] = null;
        }

        return $row;
    }

    private function dropMongoCollections(): void
    {
        $database = DB::connection('mongodb')->getMongoDB();

        foreach (array_keys($this->tables) as $collection) {
            $database->selectCollection($collection)->drop();
        }

        $database->selectCollection('counters')->drop();
    }

}
