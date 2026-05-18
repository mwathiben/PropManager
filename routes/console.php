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

// Phase-17 TIME-3: explicitly pin every schedule to Africa/Nairobi
// regardless of APP_TIMEZONE. Pre-fix, dailyAt('00:05') ran in
// APP_TIMEZONE — if an operator ever flipped APP_TIMEZONE to UTC for
// portability, every cron would shift by 3 hours and the overdue-
// marker would run 03:05 local instead of 00:05.
$schedule = function (string $command, callable $cadence) use ($failureEmail, $logFailure) {
    $event = Schedule::command($command);
    $cadence($event);
    $event->timezone('Africa/Nairobi')
        ->withoutOverlapping()
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
// Phase-13 DPA-8 (RETAIN-5 follow-up): consent records past 3 years
// after withdrawal. Active consents never prune (withdrawn_at IS NULL).
$schedule('logs:prune --table=consent --confirm', fn ($e) => $e->dailyAt('04:00'));

// Phase-12 RETAIN-9: Laravel's built-in failed-jobs prune. Phase-5
// OBS-13 added an ALERT (failed-jobs-growth-monitor); this is the
// matching PRUNE. 720 hours = 30 days retention.
$schedule('queue:prune-failed --hours=720', fn ($e) => $e->dailyAt('04:10'));

// Phase-17 MONEY-5: invoice.amount_paid drift audit. Sums per-invoice
// payments and compares to invoice.amount_paid; mismatches > 0.01 KES
// are logged + bumped to invoice_amount_paid_drift_count gauge. Phase
// -16 ops alerts can trigger off the gauge.
$schedule('payments:audit-allocations', fn ($e) => $e->dailyAt('05:30'));

// Phase-18 DATA-2: Lease.wallet_balance vs sum(wallet_transactions)
// drift audit. Same shape as MONEY-5: log mismatches, bump the
// lease_wallet_balance_drift_count Prometheus gauge, exit FAILURE.
$schedule('wallets:audit-balances', fn ($e) => $e->dailyAt('05:35'));

// Phase-19 INDEX-1 (DATA-4 closure): invoice.late_fees_total vs
// sum(active late_fees.fee_amount) drift audit. Same shape as
// MONEY-5 / DATA-2 above: log mismatches, bump the
// invoice_late_fees_total_drift_count Prometheus gauge, exit FAILURE
// on drift. Runs 5min after wallets:audit-balances so MetricsService
// gauges don't race for the same Cache key write window.
$schedule('latefees:audit-drift', fn ($e) => $e->dailyAt('05:40'));

// Phase-21 DEFER-DPA-3 (closes Phase-12 RETAIN-5 deferral): unified
// retention orchestrator. Runs logs:prune (6 tables) + soft-deleted:purge
// + queue:prune-batches + queue:prune-failed + gdpr:process-deletions
// in sequence with per-stage health gauges + aggregated failure count.
// Individual schedule entries above still run (idempotent — defensive
// duplication). Operator dashboard watches retention_pipeline_health
// {stage=X} gauges.
$schedule('dpa:enforce-retention', fn ($e) => $e->dailyAt('02:00')->withoutOverlapping(120));

// Phase-21 DEFER-DPA-1: nightly minor-tenant consent drift audit.
// Kenya DPA Article 8 / Section 33 — flags tenants with dob indicating
// minor status BUT no parental_consent_provided_at. Same pattern as
// MONEY-5 / DATA-2 / INDEX-1: log mismatches, emit
// tenant_minor_missing_consent_count Prometheus gauge, FAILURE on
// drift. Runs 5min after latefees:audit-drift to keep the
// MetricsService gauge cadence collision-free.
$schedule('tenants:audit-minor-consent', fn ($e) => $e->dailyAt('05:45'));

// Phase-18 DATA-7: weekly orphan-row audit. Catches lease→trashed-unit,
// invoice→trashed-lease, audit_logs/security_logs with missing
// user_id. Emits data_orphan_row_count{kind=X} Prometheus gauges.
$schedule('data:audit-orphans', fn ($e) => $e->weeklyOn(0, '06:00'));
// Phase-16 QUEUE-9: same prune for the job_batches table. SendBulkNoti-
// ficationsJob (post-Phase-16 QUEUE-2) now creates a job_batches row
// per fan-out — without this prune the table grows unbounded.
$schedule('queue:prune-batches --hours=720', fn ($e) => $e->dailyAt('04:15'));

// Phase-13 BREACH-3: 72-hour SLA enforcement. Hourly check for
// SecurityIncidents whose ODPC notification deadline is closing or
// has passed without odpc_notified_at being set. Pages ops via
// KENYA_DPA_BREACH_EMAIL + writes a HIGH-severity SecurityLog row
// each time. Acknowledgement via dpa:mark-regulator-notified stops
// the loop. Failure here means a Section 43 reporting miss becomes
// invisible — same on-failure email channel as the other gates.
$schedule('breach:escalate-overdue', fn ($e) => $e->hourly());

// Phase-13 BREACH-7: weekly check for SecurityIncidents whose 30-day
// post-incident review report is overdue. Acknowledgement via
// dpa:mark-review-complete stops the surfacing.
$schedule('breach:review-overdue', fn ($e) => $e->weeklyOn(1, '07:00'));

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
})->name('exports:cleanup')->dailyAt('03:15')->timezone('Africa/Nairobi')->onOneServer();

// Process queued offline payment intents every minute
$queuedIntents = Schedule::job(new \App\Jobs\ProcessQueuedPaymentIntents)
    ->everyMinute()
    ->timezone('Africa/Nairobi')
    ->withoutOverlapping()
    ->onOneServer()
    ->onFailure($logFailure('ProcessQueuedPaymentIntents'));
if ($failureEmail) {
    $queuedIntents->emailOutputOnFailure($failureEmail);
}

// Archive payments older than retention period (7 years) on 1st of each month at 03:30
$archive = Schedule::job(new \App\Jobs\ArchiveOldPayments)
    ->monthlyOn(1, '03:30')
    ->timezone('Africa/Nairobi')
    ->withoutOverlapping()
    ->onOneServer()
    ->onFailure($logFailure('ArchiveOldPayments'));
if ($failureEmail) {
    $archive->emailOutputOnFailure($failureEmail);
}

// Phase-16 QUEUE-6: per-queue depth + failed-jobs gauges. Runs every
// minute; the Phase-14 /api/metrics endpoint surfaces them so Grafana
// can plot the time series. Cheap O(1) Queue::size() + 3 cardinality-
// 1 COUNT queries per minute.
$schedule('metrics:capture-queue-depth', fn ($e) => $e->everyMinute());

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
})->name('failed-jobs-growth-monitor')->dailyAt('05:00')->timezone('Africa/Nairobi')->onOneServer();

// Phase-27 BI-DELIVERY-2: dispatch scheduled reports daily at 06:00
// Africa/Nairobi (after the failed-jobs sweep at 05:00, before
// landlords start their day). onOneServer prevents duplicate sends in
// a multi-node setup.
Schedule::command('reports:send-scheduled')
    ->dailyAt('06:00')
    ->timezone('Africa/Nairobi')
    ->onOneServer()
    ->name('phase27-bi-delivery2-scheduled-reports');

// Phase-28 TENANT-MAINT-1: nightly SLA breach detector. Runs at
// 07:00 Africa/Nairobi (after failed-jobs sweep + scheduled-reports
// dispatch). Emits ticket_sla_breach_count{priority=...} gauges +
// fires TicketSlaBreached event for each row (idempotent via cache).
Schedule::command('tickets:audit-sla')
    ->dailyAt('07:00')
    ->timezone('Africa/Nairobi')
    ->onOneServer()
    ->name('phase28-tenant-maint1-sla-audit');

// Phase-49 PARTS-INVENTORY-3: daily parts stock audit. Walks
// parts.qty_available <= reorder_threshold, emits
// parts_below_threshold_count{landlord_id} gauge + fires
// parts_below_threshold sev4 alert. 06:30 sits before
// tickets:audit-sla (07:00).
Schedule::command('parts:audit-stock')
    ->dailyAt('06:30')
    ->timezone('Africa/Nairobi')
    ->onOneServer()
    ->name('phase49-parts-inventory3-stock-audit');

// Phase-49 MAINTENANCE-COSTS-3: weekly per-landlord maintenance
// spend rollup (last 30 days). Visibility-only gauge for ops
// dashboards. Sundays at 05:00 sits in the existing Phase-33
// Sunday cost-cluster (storage:cost-audit at 05:00).
Schedule::command('maintenance:cost-rollup')
    ->weeklyOn(0, '05:00')
    ->timezone('Africa/Nairobi')
    ->onOneServer()
    ->name('phase49-maintenance-costs3-rollup');

// Phase-29 WF-RENT-REMIND-1: tiered rent reminder dispatcher. Runs
// after invoices:automate (06:00) and before tickets:audit-sla (07:00)
// so newly-generated invoices land in the same overnight cycle.
Schedule::command('rent-reminders:dispatch')
    ->dailyAt('08:00')
    ->timezone('Africa/Nairobi')
    ->onOneServer()
    ->name('phase29-wf-rent-remind1-dispatch');

// Phase-29 WF-LEASE-RENEW-1: nightly lease end_date scan emitting at
// T-60/30/7 day buckets. Runs between tickets:audit-sla (07:00) and
// rent-reminders:dispatch (08:00).
Schedule::command('leases:scan-renewals')
    ->dailyAt('07:30')
    ->timezone('Africa/Nairobi')
    ->onOneServer()
    ->name('phase29-wf-lease-renew1-scan');

// Phase-45 LEASE-COUNTER-3: expire counter-offers older than 14 days.
// Runs at 06:00 — before tickets:audit-sla (07:00) so any expiry
// notification email is already in the queue when the day's batch fires.
Schedule::command('lease-renewal:expire-stale-counters')
    ->dailyAt('06:00')
    ->timezone('Africa/Nairobi')
    ->onOneServer()
    ->name('phase45-lease-counter3-expire-stale');

// Phase-45 PAY-PLAN-MOD-3: emit payment_plan_modification_pending_24h
// gauges so oncall sees ghosted modification requests. Sev4 alert opens
// at 7-day threshold via docs/runbooks/alert-thresholds.md.
Schedule::command('payment-plans:audit-stale-modifications')
    ->dailyAt('06:15')
    ->timezone('Africa/Nairobi')
    ->onOneServer()
    ->name('phase45-payplan-mod3-audit-stale');

// Phase-46 CANONICAL-AUDIT-2: audit Mirror Registry for users.* drift.
// Runs at 03:30 — after 03:15 stripe-balance-audit, before the 06:00
// lease-counter expiry sweep. Daily cadence catches a regressed saving
// listener within 24 hours.
Schedule::command('onboarding:dedupe-audit')
    ->dailyAt('03:30')
    ->timezone('Africa/Nairobi')
    ->onOneServer()
    ->name('phase46-canonical-audit-mirror-drift');

// Phase-46 PROGRESS-RESUME-2/3: nudge stalled onboarding sessions +
// seal 30-day-abandoned ones. Runs at 09:00 — during business hours
// so the nudge email lands when the recipient is likely to read it.
Schedule::command('onboarding:nudge-stalled')
    ->dailyAt('09:00')
    ->timezone('Africa/Nairobi')
    ->onOneServer()
    ->name('phase46-progress-resume-nudge');

// Phase-29 WF-LATE-FEE-1: nightly escalation for chronically overdue
// invoices. Runs after invoices:mark-overdue (00:05) and
// invoices:apply-late-fees (00:10) so the freshly-updated overdue
// corpus is what gets escalated.
Schedule::command('invoices:escalate-overdue')
    ->dailyAt('00:30')
    ->timezone('Africa/Nairobi')
    ->onOneServer()
    ->name('phase29-wf-late-fee1-escalate');

// Phase-29 WF-VACANCY-1/3: nightly per-building occupancy aggregation
// + breach detection. Runs between reports:send-scheduled (06:00) and
// tickets:audit-sla (07:00).
Schedule::command('occupancy:audit')
    ->dailyAt('06:30')
    ->timezone('Africa/Nairobi')
    ->onOneServer()
    ->name('phase29-wf-vacancy1-audit');

// Phase-29 WF-CI-2: silent-failure detector for Phase-29 workflows.
// Runs at 04:30 — after all overnight workflow commands have had a
// chance to fire and well before the next 24h window opens at 00:05.
Schedule::command('workflow:health')
    ->dailyAt('04:30')
    ->timezone('Africa/Nairobi')
    ->onOneServer()
    ->name('phase29-wf-ci2-health');

// Phase-30 INT-MPESA-DEEP-2: poll Daraja every 30 minutes for
// in-flight B2C payout status (rows in 'sent' or 'queued' older than
// 5 minutes). Closes the silent-failure gap when the ResultURL
// callback never arrives.
Schedule::command('mpesa:reconcile-status')
    ->everyThirtyMinutes()
    ->timezone('Africa/Nairobi')
    ->onOneServer()
    ->name('phase30-int-mpesa-deep2-reconcile');

// Phase-30 INT-BANK-PARITY-3: nightly per-bank drift audit. Emits
// bank_webhook_unmatched_count, bank_webhook_error_count, and
// bank_webhook_silence_hours gauges per bank for Prometheus.
Schedule::command('bank-reconciliation:audit')
    ->dailyAt('05:50')
    ->timezone('Africa/Nairobi')
    ->onOneServer()
    ->name('phase30-int-bank-parity3-audit');

// Phase-30 INT-PERIOD-LOCK-1: monthly accounting close — closes the
// previous full calendar month for every landlord. Runs on the 1st
// at 02:30 (after dpa:enforce-retention at 02:00, before the rest of
// the nightly maintenance window).
Schedule::command('finance:close-month')
    ->monthlyOn(1, '02:30')
    ->timezone('Africa/Nairobi')
    ->onOneServer()
    ->name('phase30-int-period-lock1-close-month');

// Phase-30 INT-PAY-ALLOC-2: nightly PaymentPlan drift audit. Emits
// payment_plan_allocation_total_drift_count + status_drift_count
// gauges. Runs at 05:45 — between the other 05:30-05:50 audits.
Schedule::command('payment-plan-allocations:audit')
    ->dailyAt('05:45')
    ->timezone('Africa/Nairobi')
    ->onOneServer()
    ->name('phase30-int-pay-alloc2-audit');

// Phase-31 ONB-WIZARD-3: bucket users stalled mid-wizard by days
// inactive. Emits onboarding_stalled_count{bucket=1-3|4-7|8-30|30+}
// gauges. Runs 04:45 between workflow:health (04:30) and the
// activation:audit cron added by ONB-TTFI-2 (04:15).
Schedule::command('onboarding-wizard:audit')
    ->dailyAt('04:45')
    ->timezone('Africa/Nairobi')
    ->onOneServer()
    ->name('phase31-onb-wizard3-audit');

// Phase-31 ONB-TTFI-2: emit activation funnel gauges (signups,
// per-milestone counters, time-to-first-invoice p50/p90).
Schedule::command('activation:audit')
    ->dailyAt('04:15')
    ->timezone('Africa/Nairobi')
    ->onOneServer()
    ->name('phase31-onb-ttfi2-audit');

// Phase-32 SRE-RUNBOOK-2: weekly validation that every alert in
// config/alerts.php points at an existing runbook file + heading.
Schedule::command('runbook:coverage-audit')
    ->weeklyOn(0, '06:00')
    ->timezone('Africa/Nairobi')
    ->onOneServer()
    ->name('phase32-sre-runbook2-coverage');

// Phase-32 SRE-RUNBOOK-3: weekly per-runbook staleness gauge.
Schedule::command('runbook:staleness-audit')
    ->weeklyOn(0, '06:30')
    ->timezone('Africa/Nairobi')
    ->onOneServer()
    ->name('phase32-sre-runbook3-staleness');

// Phase-32 SRE-ALERT-2: signal-to-noise ratio per alert key over the
// last 30 days. Emits alert_signal_to_noise_ratio{alert_key=X} +
// alert_fatigue_count gauges.
Schedule::command('alert:quality')
    ->dailyAt('06:00')
    ->timezone('Africa/Nairobi')
    ->onOneServer()
    ->name('phase32-sre-alert2-quality');

// Phase-32 SRE-BUDGET-2/3: per-service budget remaining + multi-window
// burn-rate emission. Every 15 minutes matches Phase-22 PERF-SLO cadence
// so the burn-rate gauge time series is dense enough for a 1h window.
Schedule::command('slo:budget-audit')
    ->everyFifteenMinutes()
    ->timezone('Africa/Nairobi')
    ->onOneServer()
    ->name('phase32-sre-budget23-audit');

// Phase-32 SRE-INCIDENT-3: weekly MTTR (p50/p90) per-severity emission.
Schedule::command('mttr:audit')
    ->weeklyOn(1, '06:45')
    ->timezone('Africa/Nairobi')
    ->onOneServer()
    ->name('phase32-sre-incident3-mttr');

// Phase-32 SRE-DEPS-2: every 5 minutes probe each upstream dependency.
// Emits dependency_up{dep=X} + dependency_latency_ms{dep=X}, fires
// DegradationDetected on transitions, fires dependency_down alert when
// any dep is down.
Schedule::command('outbound:health-check')
    ->everyFiveMinutes()
    ->timezone('Africa/Nairobi')
    ->onOneServer()
    ->name('phase32-sre-deps2-health-check');

// Phase-33 COST-ATTRIB-2: per-landlord estimated_cost_kes gauge from
// rolling 30-day landlord_usage_metrics. Runs at 03:30 — after
// dpa:enforce-retention 02:00 and finance:close-month 02:30, before
// the cluster of 03:40-04:00 prunes.
Schedule::command('cost:attribute')
    ->dailyAt('03:30')
    ->timezone('Africa/Nairobi')
    ->onOneServer()
    ->name('phase33-cost-attrib2-attribute');

// Phase-33 COST-QUERY-2/3: per-route-class scan-to-return ratio gauges
// + high_query_scan_ratio alert. Runs at 03:45 — after cost:attribute
// 03:30 and before the 04:00-05:00 backup cluster.
Schedule::command('query:cost-audit')
    ->dailyAt('03:45')
    ->timezone('Africa/Nairobi')
    ->onOneServer()
    ->name('phase33-cost-query23-audit');

// Phase-33 COST-CACHE-1/3: per-bucket cache hit-rate gauge + low-hit
// alert. Runs at 03:50 — after query:cost-audit 03:45, before
// bank-reconciliation:audit 05:50.
Schedule::command('cache:hit-rate-audit')
    ->dailyAt('03:50')
    ->timezone('Africa/Nairobi')
    ->onOneServer()
    ->name('phase33-cost-cache13-audit');

// Phase-33 COST-STORAGE-2: weekly filesystem walk per active policy —
// heavy, so weekly not daily. Sunday 04:30 Africa/Nairobi (low-traffic
// window).
Schedule::command('storage:tier-policy')
    ->weeklyOn(0, '04:30')
    ->timezone('Africa/Nairobi')
    ->onOneServer()
    ->name('phase33-cost-storage2-tier-policy');

// Phase-33 COST-STORAGE-3: weekly KES projection per tier — must run
// AFTER storage:tier-policy (reads the gauges it wrote). 30 min later
// to give the filesystem walk room to finish on slow disks.
Schedule::command('storage:cost-audit')
    ->weeklyOn(0, '05:00')
    ->timezone('Africa/Nairobi')
    ->onOneServer()
    ->name('phase33-cost-storage3-cost-audit');

// Phase-33 COST-LOGS-2/3: per-landlord log-volume audit + skew alert.
// Runs at 03:55 — last in the Phase-33 03:30-03:55 cluster, just
// before the 04:00 backup window.
Schedule::command('log:volume-audit')
    ->dailyAt('03:55')
    ->timezone('Africa/Nairobi')
    ->onOneServer()
    ->name('phase33-cost-logs23-audit');

// Phase-34 GROWTH-MRR-2: daily MRR snapshot + per-plan gauges. Runs
// at 04:05 — first in the Phase-34 growth cluster (04:05-04:15),
// after the Phase-33 cost cluster finishes at 03:55.
Schedule::command('mrr:snapshot')
    ->dailyAt('04:05')
    ->timezone('Africa/Nairobi')
    ->onOneServer()
    ->name('phase34-growth-mrr2-snapshot');

// Phase-34 GROWTH-CHURN-3: weekly churn + cohort retention audit +
// high_churn_rate alert. Runs Monday 06:00 — before Phase-32 mttr:
// audit Monday 06:45 so both surface in the same operator review.
Schedule::command('churn:audit')
    ->weeklyOn(1, '06:00')
    ->timezone('Africa/Nairobi')
    ->onOneServer()
    ->name('phase34-growth-churn3-audit');

// Phase-34 GROWTH-REFERRAL-3: per-landlord attribution rollup. Runs
// at 04:10 — second in the Phase-34 growth cluster (after 04:05
// mrr:snapshot, before 04:15 engagement:rollup).
Schedule::command('referrals:rollup')
    ->dailyAt('04:10')
    ->timezone('Africa/Nairobi')
    ->onOneServer()
    ->name('phase34-growth-referral3-rollup');

// Phase-34 GROWTH-ENGAGEMENT-2/3: per-landlord engagement score
// rollup + low-engagement alert. Runs at 04:15 — third in the
// Phase-34 growth cluster (after referrals:rollup 04:10).
Schedule::command('engagement:rollup')
    ->dailyAt('04:15')
    ->timezone('Africa/Nairobi')
    ->onOneServer()
    ->name('phase34-growth-engagement23-rollup');

// Phase-34 GROWTH-LIFECYCLE-1/2/3: landlord lifecycle email cluster
// runs in business-hour adjacent window (09:00-09:45 Africa/Nairobi)
// — slightly after Phase-29 rent-reminders:dispatch 08:00 so the
// inboxes aren't all hit simultaneously.
Schedule::command('subscriptions:trial-ending-reminder')
    ->dailyAt('09:00')
    ->timezone('Africa/Nairobi')
    ->onOneServer()
    ->name('phase34-growth-lifecycle1-trial-ending');

Schedule::command('subscriptions:dunning-emails')
    ->dailyAt('09:15')
    ->timezone('Africa/Nairobi')
    ->onOneServer()
    ->name('phase34-growth-lifecycle2-dunning');

Schedule::command('subscriptions:churn-winback')
    ->dailyAt('09:30')
    ->timezone('Africa/Nairobi')
    ->onOneServer()
    ->name('phase34-growth-lifecycle3-winback');

Schedule::command('landlords:activation-nudge')
    ->dailyAt('09:45')
    ->timezone('Africa/Nairobi')
    ->onOneServer()
    ->name('phase34-growth-lifecycle3-activation-nudge');

// Phase-35 PLATFORM-METER-2/3: per-landlord metered usage ratio +
// overage alert. Runs at 04:20 — fourth in the 04:00 growth cluster,
// after engagement:rollup 04:15.
Schedule::command('metered:soft-cap-audit')
    ->dailyAt('04:20')
    ->timezone('Africa/Nairobi')
    ->onOneServer()
    ->name('phase35-platform-meter23-soft-cap-audit');

// Phase-35 PLATFORM-ANALYTICS-3: nightly product-event rollup. Runs
// at 04:25 — fifth in the 04:00 growth cluster, after metered:
// soft-cap-audit 04:20.
Schedule::command('product:rollup')
    ->dailyAt('04:25')
    ->timezone('Africa/Nairobi')
    ->onOneServer()
    ->name('phase35-platform-analytics3-rollup');

// Phase-35 PLATFORM-BILLING-2: apply scheduled downgrades at period
// boundary. Runs at 02:00 — earliest in the day so Phase-30
// finance:close-month 02:30 sees the new plan_id.
Schedule::command('subscriptions:apply-downgrades')
    ->dailyAt('02:00')
    ->timezone('Africa/Nairobi')
    ->onOneServer()
    ->name('phase35-platform-billing2-apply-downgrades');

// Phase-35 PLATFORM-NOTIF-3: weekly preference-drift audit. Runs
// Sunday 07:00 Africa/Nairobi onOneServer — after the Phase-32
// 06:00/06:30 Sunday cluster.
Schedule::command('notifications:preference-drift-audit')
    ->weeklyOn(0, '07:00')
    ->timezone('Africa/Nairobi')
    ->onOneServer()
    ->name('phase35-platform-notif3-drift-audit');

// Phase-36 INSIGHT-CRON-2/3: per-command + total cron runtime
// gauge + high_cron_runtime alert. Runs at 04:30 — sixth in the
// Phase 34-35 growth cluster, after Phase-35 product:rollup 04:25.
Schedule::command('cron:budget-audit')
    ->dailyAt('04:30')
    ->timezone('Africa/Nairobi')
    ->onOneServer()
    ->name('phase36-insight-cron23-budget-audit');

// Phase-37 PWA-DIGEST-1: weekly insight digest emailed to paying,
// lifecycle-opted-in landlords. Monday 07:00 — slots after Phase-32
// mttr:audit Mon 06:45 and Phase-34 churn:audit Mon 06:00.
Schedule::command('insight:weekly-digest')
    ->weeklyOn(1, '07:00')
    ->timezone('Africa/Nairobi')
    ->onOneServer()
    ->name('phase37-pwa-digest1-weekly-insight-digest');

// Phase-37 PWA-GATEWAY-3: reconcile last-24h UPGRADE
// SubscriptionChange rows against Paystack. Fires
// high_gateway_proration_drift sev3 when unreconciled count
// exceeds threshold. Daily 05:30 — slots in the growth cluster
// after Phase-30 bank-reconciliation:audit 05:50.
Schedule::command('gateway:proration-audit')
    ->dailyAt('05:30')
    ->timezone('Africa/Nairobi')
    ->onOneServer()
    ->name('phase37-pwa-gateway3-proration-audit');

// Phase-37 PWA-RETENTION-STATS-1: weekly product_events prune.
// Sunday 03:00 — runs before Phase-33 storage:tier-policy Sun 04:30
// so the freshly-pruned table doesn't get scanned by the tier audit.
Schedule::command('product:prune')
    ->weeklyOn(0, '03:00')
    ->timezone('Africa/Nairobi')
    ->onOneServer()
    ->name('phase37-pwa-retention1-product-prune');

// Phase-37 PWA-RETENTION-STATS-2: monthly cold-storage rollover.
// 1st of month 03:30 — runs BEFORE product:prune on the next
// Sunday so historical data is archived before retention deletion.
Schedule::command('product:cold-storage-rollover')
    ->monthlyOn(1, '03:30')
    ->timezone('Africa/Nairobi')
    ->onOneServer()
    ->name('phase37-pwa-retention2-product-cold-storage-rollover');

// Phase-38 DEFER-BUILD-CI-3: alert when public/build/manifest.json
// is older than the newest commit touching resources/js/. Daily
// 04:55 — slots between Phase-36 cron:budget-audit 04:30 and
// Phase-30 bank-reconciliation:audit 05:50.
Schedule::command('bundle:freshness-audit')
    ->dailyAt('04:55')
    ->timezone('Africa/Nairobi')
    ->onOneServer()
    ->name('phase38-defer-build-ci3-bundle-freshness-audit');

// Phase-39 VENDOR-ANALYTICS-2: forward yesterday's product_events
// to the configured analytics vendor (PostHog default; noop when
// disabled). Daily 04:45 — between Phase-36 cron:budget-audit
// 04:30 and Phase-38 bundle:freshness-audit 04:55.
Schedule::command('analytics:replay-batch')
    ->dailyAt('04:45')
    ->timezone('Africa/Nairobi')
    ->onOneServer()
    ->name('phase39-vendor-analytics2-replay-batch');

// Phase-39 VENDOR-OBSERV-1: emit push_click_through_rate_24h gauge.
// Daily 05:10 — after replay-batch + bundle:freshness-audit so the
// metrics emit slot doesn't compete with vendor work.
Schedule::command('push:click-through-audit')
    ->dailyAt('05:10')
    ->timezone('Africa/Nairobi')
    ->onOneServer()
    ->name('phase39-vendor-observ1-push-click-through-audit');

// Phase-40 GATEWAY-RECONCILE-2: iterate landlords + each enabled
// gateway (Paystack + Stripe), emit drift gauges + fire
// gateway_drift sev3 alert if drift exceeds threshold. 05:45 slot
// is the legacy reconciliation:run-daily slot 04:00 + 105m so the
// Paystack-only daily reconciler can run first if both stay enabled.
Schedule::command('payments:gateway-reconcile')
    ->dailyAt('05:45')
    ->timezone('Africa/Nairobi')
    ->onOneServer()
    ->name('phase40-payments-gateway-reconcile');

// Phase-41 GATEWAY-PLAN-SYNC-1: weekly push of SubscriptionPlan
// price changes to Stripe Prices. Mon 04:35 — slots between
// Phase-36 cron:budget-audit 04:30 and Phase-39 analytics:replay-batch
// 04:45.
Schedule::command('stripe:plan-sync')
    ->weeklyOn(1, '04:35')
    ->timezone('Africa/Nairobi')
    ->onOneServer()
    ->name('phase41-gateway-plan-sync');

// Phase-42 PAYOUT-AUDIT-1: twice-daily poll for Stripe Connect
// payout failures. 03:15 + 15:15 Africa/Nairobi — covers a Stripe
// payout lifecycle in under 12h with completeness in case the
// payout.failed webhook is dropped.
Schedule::command('payouts:stripe-balance-audit')
    ->twiceDailyAt(3, 15, 15)
    ->timezone('Africa/Nairobi')
    ->onOneServer()
    ->name('phase42-payout-audit');

// Phase-43 LANG-AUDIT-1: daily diff of every locale bundle against
// the English baseline. Emits i18n_missing_keys_count{namespace,locale}
// gauge for the ops dashboard + sev3 alert on pinned-namespace drift.
Schedule::command('lang:audit')
    ->dailyAt('04:15')
    ->timezone('Africa/Nairobi')
    ->onOneServer()
    ->name('phase43-i18n-audit');

// Phase-53 GAUGE-WIRING-1: tenant_kyc_blocked_count gauge emitter.
// Counts tenants stuck at Phase-48 wizard step-2 advance gate. Hourly
// at :15 so the gauge timeline is granular enough for the sev4 alert
// window (alert-thresholds.md line 33, threshold 20/24h).
Schedule::command('tenant-kyc:blocked-audit')
    ->hourlyAt(15)
    ->timezone('Africa/Nairobi')
    ->onOneServer()
    ->name('phase53-gauge-wiring1-tenant-kyc-blocked');

// Phase-54 PARTS-REORDER-2: convert parts_below_threshold gauge signal
// into draft purchase orders per (landlord, suggested_vendor). Runs at
// 06:45 — after parts:audit-stock 06:30 (so the gauge for the day is
// current) and before tickets:audit-sla 07:00.
Schedule::command('parts:reorder-suggest')
    ->dailyAt('06:45')
    ->timezone('Africa/Nairobi')
    ->onOneServer()
    ->name('phase54-parts-reorder-suggest');

// Phase-53 GAUGE-WIRING-3: i18n_translation_spend_usd_24h gauge emitter.
// Scrapes TranslationCostTracker Cache keys + emits total + per-locale
// gauges. Every 15m so the sev3 $20/day budget alert (alert-thresholds.md
// line 39) has a fresh window to fire against.
Schedule::command('i18n:spend-audit')
    ->everyFifteenMinutes()
    ->timezone('Africa/Nairobi')
    ->onOneServer()
    ->name('phase53-gauge-wiring3-i18n-spend');
