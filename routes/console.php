<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('orders:auto-cancel')->everyMinute()->withoutOverlapping();
Schedule::command('orders:payment-reminders')->everyMinute()->withoutOverlapping();
Schedule::command('campaigns:check-sold-out')->everyMinute()->withoutOverlapping();
Schedule::command('midtrans:sync-payments')->everyFifteenMinutes()->withoutOverlapping();
Schedule::command('biteship:sync-status')->everyFifteenMinutes()->withoutOverlapping();
Schedule::command('app:db-backup')->dailyAt('02:00')->withoutOverlapping();
Schedule::command('app:heartbeat')->everyMinute()->withoutOverlapping();
