<?php

namespace App\Console\Commands;

use App\Services\GateHardwareService;
use Illuminate\Console\Command;

class GateStatusCommand extends Command
{
    protected $signature = 'capstone:gate-status
                            {--watch : Refresh every 2 seconds until Ctrl+C}';

    protected $description = 'Show whether each ESP32 RFID gate is online (Laravel heartbeat)';

    public function handle(GateHardwareService $hardware): int
    {
        if ($this->option('watch')) {
            $this->info('Watching ESP32 gates — Ctrl+C to stop.');
            $this->newLine();

            while (true) {
                $this->render($hardware->statuses());
                sleep(2);
            }
        }

        $this->render($hardware->statuses());

        return self::SUCCESS;
    }

    /**
     * @param  list<array{gate_id: string, direction: string, label: string, online: bool, pending_open: bool, last_seen_at: int|null}>  $gates
     */
    private function render(array $gates): void
    {
        $rows = [];
        foreach ($gates as $gate) {
            $rows[] = [
                $gate['gate_id'],
                $gate['label'],
                $gate['online'] ? 'ONLINE' : 'OFFLINE',
                $this->lastSeenLabel($gate['last_seen_at'] ?? null),
                ($gate['pending_open'] ?? false) ? 'queued' : '-',
            ];
        }

        $this->table(['Gate', 'Name', 'Status', 'Last heartbeat', 'Open'], $rows);
    }

    private function lastSeenLabel(?int $timestamp): string
    {
        if ($timestamp === null || $timestamp <= 0) {
            return 'never';
        }

        $age = max(0, now()->timestamp - $timestamp);

        return $age === 0 ? 'just now' : "{$age}s ago";
    }
}
