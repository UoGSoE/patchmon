<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('patchmon:sync-netbox')->dailyAt('08:10')->withoutOverlapping();
Schedule::command('patchmon:evaluate')->dailyAt('08:40')->withoutOverlapping();
Schedule::command('patchmon:alert-unassigned')->weeklyOn(1, '08:00')->withoutOverlapping();
Schedule::command('patchmon:weekly-overview')->weeklyOn(1, '08:00')->withoutOverlapping();
