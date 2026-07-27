<?php

namespace App\Console\Commands;

use App\Models\GateLog;
use App\Models\Notification;
use App\Models\ParkingSlot;
use App\Models\User;
use App\Models\UserSuspension;
use App\Models\ViolationLog;
use App\Services\SequenceService;
use Database\Seeders\CapstoneSeeder;
use Illuminate\Console\Command;

class PurgeUserDataCommand extends Command
{
    protected $signature = 'capstone:purge-users
                            {--force : Run without confirmation}
                            {--keep-admin : Keep the seeded admin account}
                            {--no-reseed : Do not reseed admin/test users after purge}';

    protected $description = 'Remove all user data (users, notifications, logs) while keeping schema and reference data';

    public function handle(): int
    {
        if (! $this->option('force') && ! $this->confirm('This will permanently delete all user accounts and related activity. Continue?')) {
            $this->warn('Aborted.');

            return self::SUCCESS;
        }

        $keepAdmin = $this->option('keep-admin');
        $admin = $keepAdmin ? User::query()->where('email', 'admin@my.cspc.edu.ph')->first() : null;

        $this->info('Purging user-related collections...');

        $deleted = [
            'notifications' => Notification::query()->delete(),
            'gate_logs' => GateLog::query()->delete(),
            'violations_log' => ViolationLog::query()->delete(),
            'user_suspensions' => UserSuspension::query()->delete(),
        ];

        if ($keepAdmin && $admin) {
            $deleted['users'] = User::query()->where('id', '!=', $admin->id)->delete();
        } else {
            $deleted['users'] = User::query()->delete();
        }

        $slotsReset = ParkingSlot::query()
            ->where(function ($q) {
                $q->whereNotNull('parked_user_id')
                    ->orWhereIn('status', ['Occupied', 'Reserved']);
            })
            ->update([
                'parked_user_id' => null,
                'status' => 'Available',
            ]);

        $deleted['parking_slots_reset'] = $slotsReset;

        foreach ($deleted as $label => $count) {
            $this->line("  · {$label}: {$count}");
        }

        SequenceService::syncCountersForModels([
            User::class,
            Notification::class,
            GateLog::class,
            ViolationLog::class,
            UserSuspension::class,
        ]);

        if (! $this->option('no-reseed')) {
            $this->info('Reseeding admin and test users...');
            $this->callSilent('db:seed', ['--class' => CapstoneSeeder::class, '--force' => true]);
            $this->line('  · Admin: admin@my.cspc.edu.ph / admin123');
            $this->line('  · Guard: guard@my.cspc.edu.ph / password123');
        }

        $this->info('User data purge complete. Schema and reference data preserved.');

        return self::SUCCESS;
    }
}
