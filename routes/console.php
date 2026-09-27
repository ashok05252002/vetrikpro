<?php

use App\Support\Settings;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 * The daily overdue email, at the time and in the timezone set in
 * Configuration hub. Needs the scheduler running on the server:
 * `* * * * * php artisan schedule:run` in cron.
 */
Schedule::command('tasks:overdue-digest')
    ->dailyAt(rescue(fn () => (string) app(Settings::class)->get('notify.overdue.time', '17:00'), '17:00', false) ?: '17:00')
    ->timezone(rescue(fn () => (string) app(Settings::class)->get('display.timezone', 'UTC'), 'UTC', false) ?: 'UTC')
    ->withoutOverlapping();
