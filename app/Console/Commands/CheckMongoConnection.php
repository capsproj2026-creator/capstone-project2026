<?php

namespace App\Console\Commands;

use App\Support\MongoConnection;
use Illuminate\Console\Command;

class CheckMongoConnection extends Command
{
    protected $signature = 'capstone:db-status';

    protected $description = 'Check MongoDB connectivity and show whether the app uses cloud (Atlas) or local database';

    public function handle(): int
    {
        $status = MongoConnection::status();

        $this->info('MongoDB connection status');
        $this->newLine();
        $this->line('Database: '.$status['database']);
        $this->line('Mode: '.($status['cloud'] ? 'MongoDB Atlas (cloud, multi-device)' : 'Local MongoDB (this machine/network only)'));

        if ($status['connected']) {
            $this->components->info('Connected');
            $this->line($status['message']);

            return self::SUCCESS;
        }

        $this->components->error('Not connected');
        $this->line($status['message']);
        $this->error($status['error'] ?? 'Unknown error');
        $this->newLine();

        if ($status['cloud']) {
            $this->warn('Atlas checklist:');
            $this->line('  1. Confirm MONGODB_URI username/password in .env');
            $this->line('  2. In Atlas → Network Access, allow your current IP or 0.0.0.0/0 for development');
            $this->line('  3. Copy the same .env MongoDB settings to every device running this app');
        } else {
            $this->warn('Start local MongoDB, or switch MONGODB_URI to your Atlas connection string for multi-device access.');
        }

        return self::FAILURE;
    }
}
