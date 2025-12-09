<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule automatic cleanup of abandoned payments
Schedule::command('payments:cleanup-abandoned')->hourly();

// Schedule Pulse data cleanup (removes data older than PULSE_STORAGE_KEEP)
Schedule::command('pulse:clear')->dailyAt('03:00');

