<?php

namespace App\Observers;

use App\Models\GateLog;

class GateLogObserver
{
    public function creating(GateLog $gateLog): void
    {
        $today = now()->startOfDay();

        $lastDailyId = GateLog::query()
            ->where('log_date', '>=', $today)
            ->where('log_date', '<', $today->copy()->addDay())
            ->max('daily_log_id');

        $gateLog->log_date = $today->toDateString();
        $gateLog->daily_log_id = ($lastDailyId ?? 0) + 1;
    }
}
