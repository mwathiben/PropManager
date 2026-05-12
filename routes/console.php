<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// CONC-14: every scheduled task that mutates financial state or sends
// notifications must use onOneServer() on top of withoutOverlapping().
// withoutOverlapping prevents the same server from running two copies of
// the task at the same time; onOneServer prevents two SERVERS from each
// running their own copy under a multi-host deployment.

// OBS-3: every scheduled command must log on failure and (when configured)
// page ops via email. Pre-fix, a wedged scheduler ran silently — the
// invoice automation could fail every night without anyone noticing. The
// failure_email recipient is read from config so dev / CI doesn't blast
// real inboxes.
$failureEmail = config('schedule.failure_email');
$logFailure = function (string $command): \Closure {
    return function () use ($command) {
        Log::channel(config('logging.schedule_channel', 'stack'))
            ->error('Scheduled task failed', ['command' => $command]);
    };
};

$schedule = function (string $command, callable $cadence) use ($failureEmail, $logFailure) {
    $event = Schedule::command($command);
    $cadence($event);
    $event->withoutOverlapping()
        ->onOneServer()
        ->runInBackground()
        ->onFailure($logFailure($command));
    if ($failureEmail) {
        $event->emailOutputOnFailure($failureEmail);
    }

    return $event;
};

$schedule('notifications:process-schedules', fn ($e) => $e->everyFiveMinutes());
$schedule('invoices:mark-overdue', fn ($e) => $e->dailyAt('00:05'));
$schedule('invoices:apply-late-fees', fn ($e) => $e->dailyAt('00:10'));
$schedule('invoices:automate', fn ($e) => $e->dailyAt('06:00'));
$schedule('notifications:process-failed', fn ($e) => $e->everyFifteenMinutes());
$schedule('notifications:process-scheduled', fn ($e) => $e->everyMinute());
$schedule('payment-links:cleanup', fn ($e) => $e->dailyAt('02:00'));
$schedule('tenant-invitations:cleanup', fn ($e) => $e->dailyAt('02:30'));
$schedule('idempotency:cleanup', fn ($e) => $e->dailyAt('03:00'));
$schedule('reconciliation:run-daily', fn ($e) => $e->dailyAt('04:00'));

// Phase-12 RETAIN-1/2: prune append-only log tables past their
// configured retention window. config/security.php defines the
// windows; this is the consumer. Pruning is chunked (1000 rows per
// batch) so a multi-million-row prune doesn't lock either table.
$schedule('logs:prune --table=audit --confirm', fn ($e) => $e->dailyAt('03:30'));
$schedule('logs:prune --table=security --confirm', fn ($e) => $e->dailyAt('03:35'));
// Phase-12 RETAIN-7/8: prune RESOLVED webhook_dead_letters past 28
// days (config('payments.dead_letter.retention_days'), previously a
// dead config). Unresolved rows are operational debt and never
// pruned here.
$schedule('logs:prune --table=dead-letter --confirm', fn ($e) => $e->dailyAt('03:40'));
// Phase-12 RETAIN-10: high-volume webhook log tables. 180-day
// retention keeps payment-reconciliation history but contains
// index bloat. Stagger off the dead-letter prune so they don't
// contend for the table cache.
$schedule('logs:prune --table=webhook --confirm', fn ($e) => $e->dailyAt('03:50'));
$schedule('logs:prune --table=bank-webhook --confirm', fn ($e) => $e->dailyAt('03:55'));

// Phase-12 RETAIN-9: Laravel's built-in failed-jobs prune. Phase-5
// OBS-13 added an ALERT (failed-jobs-growth-monitor); this is the
// matching PRUNE. 720 hours = 30 days retention.
$schedule('queue:prune-failed --hours=720', fn ($e) => $e->dailyAt('04:10'));

// Phase-12 RETAIN-3: GDPR Article 17 deletion request processor.
// Command existed pre-Phase-12 (ProcessScheduledDeletions) but was
// never scheduled — deletion requests were marked then never
// actioned. Daily at 02:45 sits inside the existing 02:00-04:00
// nightly maintenance window.
$schedule('gdpr:process-deletions', fn ($e) => $e->dailyAt('02:45'));

// Phase-12 BACKUP-1: spatie/laravel-backup scheduling. backup:clean
// applies the GFS retention from config/backup.php. backup:run
// produces the daily backup. backup:monitor checks health (age +
// size thresholds) and notifies on failure. Phase 2 refines the
// retention policy further.
$schedule('backup:clean', fn ($e) => $e->dailyAt('01:00'));
$schedule('backup:run', fn ($e) => $e->dailyAt('01:30'));
$schedule('backup:monitor', fn ($e) => $e->dailyAt('06:30'));
// Phase-12 BACKUP-2: weekly archive-integrity check beyond
// backup:monitor's age/size assertions. Catches the case where a
// backup is the right size and recent but the .zip itself is
// corrupted or the inner dump is empty.
$schedule('backup:verify', fn ($e) => $e->weeklyOn(0, '06:35'));

// Phase-12 RETAIN-4: force-delete soft-deleted rows past the grace
// window. DELETION_GRACE_DAYS lives in .env; defaults to 30.
$schedule('soft-deleted:purge --confirm', fn ($e) => $e->dailyAt('03:45'));

// Phase-12 RETAIN-6: DataExportService::cleanupOldExports already
// existed but was never scheduled — GDPR export files accumulated
// on disk after the user downloaded them. Default retention 7 days
// post-creation.
Schedule::call(function () {
    app(\App\Services\DataExportService::class)->cleanupOldExports(7);
})->name('exports:cleanup')->dailyAt('03:15')->onOneServer();

// Process queued offline payment intents every minute
$queuedIntents = Schedule::job(new \App\Jobs\ProcessQueuedPaymentIntents)
    ->everyMinute()
    ->withoutOverlapping()
    ->onOneServer()
    ->onFailure($logFailure('ProcessQueuedPaymentIntents'));
if ($failureEmail) {
    $queuedIntents->emailOutputOnFailure($failureEmail);
}

// Archive payments older than retention period (7 years) on 1st of each month at 03:30
$archive = Schedule::job(new \App\Jobs\ArchiveOldPayments)
    ->monthlyOn(1, '03:30')
    ->withoutOverlapping()
    ->onOneServer()
    ->onFailure($logFailure('ArchiveOldPayments'));
if ($failureEmail) {
    $archive->emailOutputOnFailure($failureEmail);
}

// OBS-13: failed_jobs growth monitor. Without this, a wedged worker /
// poisoned job lets failed_jobs grow unbounded and we don't notice
// until queue throughput collapses. Threshold + recipient are
// configurable so dev / CI doesn't blast real inboxes.
Schedule::call(function () use ($failureEmail) {
    $threshold = (int) config('queue.failed_jobs_alert_threshold', 25);
    $count = \Illuminate\Support\Facades\DB::table('failed_jobs')
        ->where('failed_at', '>=', now()->subDay())
        ->count();
    Log::channel(config('logging.schedule_channel', 'stack'))->info(
        'failed_jobs growth monitor',
        ['count_24h' => $count, 'threshold' => $threshold]
    );
    if ($count > $threshold && $failureEmail) {
        \Illuminate\Support\Facades\Mail::raw(
            "failed_jobs grew by {$count} rows in the last 24h (threshold {$threshold}). Investigate worker logs.",
            fn ($m) => $m->to($failureEmail)->subject('[ALERT] failed_jobs growth threshold crossed')
        );
    }
})->name('failed-jobs-growth-monitor')->dailyAt('05:00')->onOneServer();
