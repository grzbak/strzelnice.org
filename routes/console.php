<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('shooting-ranges:expire-invitations')->hourly();
Schedule::command('shooting-ranges:send-invitation-expiry-reminders --days=2')->dailyAt('00:24');
Schedule::command('shooting-range:cleanup-expired')
    ->dailyAt('00:00')
    ->withoutOverlapping();
Schedule::command('reservations:archive-and-purge')
    ->dailyAt('00:15')
    ->withoutOverlapping();
Schedule::command('reservations:send-today-reminders')
    ->dailyAt('06:00')
    ->withoutOverlapping();
Schedule::command('packages:expire-unpaid-orders')
    ->hourly()
    ->withoutOverlapping();
Schedule::command('reservation-payments:expire-pending')
    ->everyFiveMinutes()
    ->withoutOverlapping();
Schedule::command('security:prune-audit-history')
    ->dailyAt('03:15')
    ->withoutOverlapping();
