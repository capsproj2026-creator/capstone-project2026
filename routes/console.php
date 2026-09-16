<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('visitors:expire')->everyMinute();

// Local-first <-> Atlas sync. No-op unless SYNC_ENABLED=true and Atlas is
// reachable (see App\Services\Sync\AtlasSyncService), so this is harmless
// on the cloud/Atlas-only deployment and on a laptop with no internet.
Schedule::command('sync:run')->everyTwoMinutes()->withoutOverlapping();

// Retries verification emails that failed at registration time (e.g. the
// account registered while offline/SMTP was unreachable). Cheap no-op when
// there is nothing pending.
Schedule::command('email:retry-verification')->everyFiveMinutes()->withoutOverlapping();
