<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Scheduled maintenances whose date has arrived become On Progress. The
// same transition also runs on-read in MaintenanceController::index(), so
// environments without a scheduler (e.g. Windows dev) stay accurate too.
// Server cron (Linux) only needs: * * * * * php artisan schedule:run
Schedule::command('maintenance:advance-status')->hourly();
