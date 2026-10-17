<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('iot:storage:tier')->dailyAt('03:15')->withoutOverlapping();
Schedule::command('iot:logs:archive --compress')->dailyAt('00:30')->withoutOverlapping();
// Purge on the 1st and 15th ("every ~2 weeks"); archives younger than LOG_ARCHIVE_DAYS are never touched.
Schedule::command('iot:logs:archive --purge')->twiceMonthly(1, 15, '01:00')->withoutOverlapping();
Schedule::command('iot:capacity:check')->everyFifteenMinutes()->withoutOverlapping();
Schedule::command('iot:cdc:trim')->hourly()->withoutOverlapping();
