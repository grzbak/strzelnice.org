<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('shooting-ranges:expire-invitations')->hourly();
Schedule::command('shooting-ranges:send-invitation-expiry-reminders --days=2')->dailyAt('00:24');