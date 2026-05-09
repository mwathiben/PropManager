<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// CONC-14: every scheduled task that mutates financial state or sends
// notifications must use onOneServer() on top of withoutOverlapping().
// withoutOverlapping prevents the same server from running two copies of
// the task at the same time; onOneServer prevents two SERVERS from each
// running their own copy under a multi-host deployment.

// Process notification schedules every 5 minutes
Schedule::command('notifications:process-schedules')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->onOneServer()
    ->runInBackground();

// Mark overdue invoices daily at 00:05
Schedule::command('invoices:mark-overdue')
    ->dailyAt('00:05')
    ->withoutOverlapping()
    ->onOneServer()
    ->runInBackground();

// Apply late fees to overdue invoices daily at 00:10 (after mark-overdue)
Schedule::command('invoices:apply-late-fees')
    ->dailyAt('00:10')
    ->withoutOverlapping()
    ->onOneServer()
    ->runInBackground();

// Auto-generate invoices for buildings configured for today's date
Schedule::command('invoices:automate')
    ->dailyAt('06:00')
    ->withoutOverlapping()
    ->onOneServer()
    ->runInBackground();

// Process failed/stuck notifications and trigger fallback channels every 15 minutes
Schedule::command('notifications:process-failed')
    ->everyFifteenMinutes()
    ->withoutOverlapping()
    ->onOneServer()
    ->runInBackground();

// Process scheduled notifications (quiet hours safety net) every minute
Schedule::command('notifications:process-scheduled')
    ->everyMinute()
    ->withoutOverlapping()
    ->onOneServer()
    ->runInBackground();

// Clean up expired payment links daily at 02:00
Schedule::command('payment-links:cleanup')
    ->dailyAt('02:00')
    ->withoutOverlapping()
    ->onOneServer()
    ->runInBackground();

// Clean up expired tenant invitations daily at 02:30
Schedule::command('tenant-invitations:cleanup')
    ->dailyAt('02:30')
    ->withoutOverlapping()
    ->onOneServer()
    ->runInBackground();

// Clean up expired idempotency keys daily at 03:00
Schedule::command('idempotency:cleanup')
    ->dailyAt('03:00')
    ->withoutOverlapping()
    ->onOneServer()
    ->runInBackground();

// Process queued offline payment intents every minute
Schedule::job(new \App\Jobs\ProcessQueuedPaymentIntents)
    ->everyMinute()
    ->withoutOverlapping()
    ->onOneServer();

// Run daily payment reconciliation for all Paystack-configured landlords at 04:00
Schedule::command('reconciliation:run-daily')
    ->dailyAt('04:00')
    ->withoutOverlapping()
    ->onOneServer()
    ->runInBackground();

// Archive payments older than retention period (7 years) on 1st of each month at 03:30
Schedule::job(new \App\Jobs\ArchiveOldPayments)
    ->monthlyOn(1, '03:30')
    ->withoutOverlapping()
    ->onOneServer();
