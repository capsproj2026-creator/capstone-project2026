<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class EnsureMongoIndexes extends Command
{
    protected $signature = 'mongo:ensure-indexes';

    protected $description = 'Create MongoDB indexes for RFID / gate / violation query performance';

    public function handle(): int
    {
        try {
            $db = DB::connection('mongodb')->getMongoDB();
        } catch (Throwable $e) {
            $this->error('MongoDB unavailable: '.$e->getMessage());

            return self::FAILURE;
        }

        $specs = [
            'users' => [
                ['key' => ['rfid_uid' => 1], 'name' => 'users_rfid_uid', 'sparse' => true],
                ['key' => ['plate_number' => 1], 'name' => 'users_plate_number', 'sparse' => true],
                ['key' => ['temp_rfid_uid' => 1], 'name' => 'users_temp_rfid_uid', 'sparse' => true],
                ['key' => ['status' => 1, 'created_at' => -1], 'name' => 'users_status_created'],
            ],
            'gate_logs' => [
                ['key' => ['timestamp' => -1], 'name' => 'gate_logs_timestamp'],
                ['key' => ['rfid_uid' => 1, 'timestamp' => -1], 'name' => 'gate_logs_rfid_timestamp'],
                ['key' => ['user_id' => 1, 'timestamp' => -1], 'name' => 'gate_logs_user_timestamp', 'sparse' => true],
                ['key' => ['action' => 1, 'timestamp' => -1], 'name' => 'gate_logs_action_timestamp'],
            ],
            'violations_log' => [
                ['key' => ['created_at' => -1], 'name' => 'violations_created'],
                ['key' => ['user_id' => 1, 'created_at' => -1], 'name' => 'violations_user_created'],
                ['key' => ['plate_number' => 1, 'created_at' => -1], 'name' => 'violations_plate_created'],
                ['key' => ['plate_key' => 1, 'created_at' => -1], 'name' => 'violations_plate_key_created', 'sparse' => true],
            ],
            'visitor_rfid_cards' => [
                ['key' => ['rfid_uid' => 1], 'name' => 'visitor_cards_rfid', 'sparse' => true],
            ],
        ];

        foreach ($specs as $collection => $indexes) {
            try {
                $col = $db->selectCollection($collection);
                foreach ($indexes as $index) {
                    $options = ['name' => $index['name']];
                    if (! empty($index['sparse'])) {
                        $options['sparse'] = true;
                    }
                    $col->createIndex($index['key'], $options);
                    $this->line("  {$collection}.{$index['name']}");
                }
            } catch (Throwable $e) {
                $this->warn("Skipped {$collection}: ".$e->getMessage());
            }
        }

        $this->info('MongoDB indexes ensured.');

        return self::SUCCESS;
    }
}
