<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Runs from the worker container (scripts/railway-worker.sh).
Schedule::command('generation:reap-stuck')->everyFiveMinutes()->withoutOverlapping();
