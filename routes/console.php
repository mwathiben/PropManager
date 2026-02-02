<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Process notification schedules every 5 minutes
Schedule::command('notifications:process-schedules')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->runInBackground();

// Mark overdue invoices daily at 00:05
Schedule::command('invoices:mark-overdue')
    ->dailyAt('00:05')
    ->withoutOverlapping()
    ->runInBackground();

// Apply late fees to overdue invoices daily at 00:10 (after mark-overdue)
Schedule::command('invoices:apply-late-fees')
    ->dailyAt('00:10')
    ->withoutOverlapping()
    ->runInBackground();

// Auto-generate invoices for buildings configured for today's date
Schedule::command('invoices:automate')
    ->dailyAt('06:00')
    ->withoutOverlapping()
    ->runInBackground();

// Process failed/stuck notifications and trigger fallback channels every 15 minutes
Schedule::command('notifications:process-failed')
    ->everyFifteenMinutes()
    ->withoutOverlapping()
    ->runInBackground();

// Process scheduled notifications (quiet hours safety net) every minute
Schedule::command('notifications:process-scheduled')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground();

// Clean up expired payment links daily at 02:00
Schedule::command('payment-links:cleanup')
    ->dailyAt('02:00')
    ->withoutOverlapping()
    ->runInBackground();

// Clean up expired tenant invitations daily at 02:30
Schedule::command('tenant-invitations:cleanup')
    ->dailyAt('02:30')
    ->withoutOverlapping()
    ->runInBackground();
