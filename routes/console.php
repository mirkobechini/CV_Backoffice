<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');


Schedule::command('app:send-summary-report')
    ->when(fn() => notification_setting('report_frequency', 'daily') === 'daily')
    ->dailyAt('8:00');

Schedule::command('app:send-summary-report')
    ->when(fn() => notification_setting('report_frequency', 'daily') === 'weekly')
    ->weeklyOn(1, '8:00');

Schedule::command('app:send-summary-report')
    ->when(fn() => notification_setting('report_frequency', 'daily') === 'monthly')
    ->monthlyOn(1, '8:00');