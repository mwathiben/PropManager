<?php

declare(strict_types=1);

/**
 * Phase-32 SRE-RUNBOOK-1: single source of truth for every alert that
 * pages or emails the on-call rotation. Every entry must have:
 *
 *   - key         : stable machine identifier (snake_case)
 *   - severity    : sev1|sev2|sev3|sev4 — drives paging policy
 *   - threshold   : numeric value the gauge must cross to fire
 *   - window      : human-readable window (matches the gauge's window)
 *   - gauge       : the Prometheus gauge name this alert reads
 *   - runbook     : path-and-anchor pointing at docs/runbooks/X.md#anchor
 *   - paging      : email|page|both (page = real pager / phone)
 *   - description : one-line operator-facing summary
 *
 * SRE-RUNBOOK-2 (runbook:coverage-audit) walks this list and verifies
 * every runbook reference resolves to an existing markdown file and
 * heading. SRE-ALERT-1 (AlertFiringRecorder) records firings keyed on
 * `key`. SRE-CI-1 (Phase32SreSurfaceTest) asserts the registry is
 * loadable + has at least one entry per category.
 *
 * Phase-69 GAUGE-NAMING: `gauge` names follow the convention in
 * docs/runbooks/metrics-naming.md, enforced by Phase69GaugeNamingTest.
 */

return [
    'alerts' => [
        [
            'key' => 'failed_jobs_growth',
            'severity' => 'sev3',
            'threshold' => 25,
            'window' => '24h',
            'gauge' => 'failed_jobs_count_24h',
            'runbook' => 'docs/runbooks/queue-triage.md',
            'paging' => 'email',
            'description' => 'failed_jobs table grew by more than threshold rows in last 24h.',
        ],
        [
            'key' => 'queue_depth_high',
            'severity' => 'sev2',
            'threshold' => 1000,
            'window' => 'instantaneous',
            'gauge' => 'queue_depth',
            'runbook' => 'docs/runbooks/queue-triage.md',
            'paging' => 'page',
            'description' => 'Workers not keeping up with queue depth.',
        ],
        [
            'key' => 'webhook_dead_letter_unresolved',
            'severity' => 'sev2',
            'threshold' => 50,
            'window' => 'instantaneous',
            'gauge' => 'webhook_dead_letter_unresolved_count',
            'runbook' => 'docs/runbooks/integrations.md#how-to-investigate',
            'paging' => 'page',
            'description' => 'Webhook dead-letter backlog exceeded threshold.',
        ],
        [
            'key' => 'backup_age_overdue',
            'severity' => 'sev2',
            'threshold' => 24,
            'window' => 'instantaneous',
            'gauge' => 'backup_age_hours',
            'runbook' => 'docs/runbooks/disaster-recovery.md',
            'paging' => 'page',
            'description' => 'Most recent successful backup is older than threshold hours.',
        ],
        [
            'key' => 'slo_budget_fast_burn',
            'severity' => 'sev1',
            'threshold' => 14.4,
            'window' => '1h',
            'gauge' => 'service_slo_burn_rate_1h',
            'runbook' => 'docs/runbooks/slo.md',
            'paging' => 'page',
            'description' => 'Multi-window burn-rate alert: 1h burn > 14.4x AND 6h burn > 6x.',
        ],
        [
            'key' => 'dependency_down',
            'severity' => 'sev2',
            'threshold' => 0,
            'window' => 'instantaneous',
            'gauge' => 'dependency_up',
            'runbook' => 'docs/runbooks/circuit-breaker.md',
            'paging' => 'page',
            'description' => 'An upstream dependency (Daraja, Paystack, Redis, SMTP, SMS) is down.',
        ],
        [
            'key' => 'workflow_silent_failure',
            'severity' => 'sev3',
            'threshold' => 0,
            'window' => '24h',
            'gauge' => 'workflow_runs_total',
            'runbook' => 'docs/runbooks/workflow-automation.md',
            'paging' => 'email',
            'description' => 'A Phase-29 workflow command produced zero workflow_runs_log rows in the last 24h.',
        ],
        [
            'key' => 'onboarding_stalled_users_spike',
            'severity' => 'sev4',
            'threshold' => 25,
            'window' => '24h',
            'gauge' => 'onboarding_stalled_count',
            'runbook' => 'docs/runbooks/onboarding.md#how-to-investigate',
            'paging' => 'email',
            'description' => '8-30 day stall bucket exceeded threshold — onboarding UX friction signal.',
        ],

        // Phase-33 COST-QUERY-3
        [
            'key' => 'high_query_scan_ratio',
            'severity' => 'sev3',
            'threshold' => 1000,
            'window' => '24h',
            'gauge' => 'query_scan_to_return_ratio_p90',
            'runbook' => 'docs/runbooks/n-plus-one.md',
            'paging' => 'email',
            'description' => 'Per-route-class scan-to-return ratio p90 exceeded threshold — likely N+1 or missing index.',
        ],

        // Phase-33 COST-CACHE-3
        [
            'key' => 'low_cache_hit_rate',
            'severity' => 'sev3',
            'threshold' => 0.5,
            'window' => 'instantaneous',
            'gauge' => 'cache_hit_rate_ratio',
            'runbook' => 'docs/runbooks/policy-and-index.md',
            'paging' => 'email',
            'description' => 'A cache bucket (cache,type) dropped below the configured hit-rate floor — wasted compute.',
        ],

        // Phase-33 COST-LOGS-3
        [
            'key' => 'high_landlord_log_volume',
            'severity' => 'sev4',
            'threshold' => 5,
            'window' => '24h',
            'gauge' => 'landlord_log_bytes_24h',
            'runbook' => 'docs/runbooks/cost.md',
            'paging' => 'email',
            'description' => 'One landlord exceeds 5x median log volume — investigate noisy webhook or error loop.',
        ],

        // Phase-34 GROWTH-CHURN-3
        [
            'key' => 'high_churn_rate',
            'severity' => 'sev2',
            'threshold' => 0.05,
            'window' => '30d',
            'gauge' => 'subscription_monthly_churn_rate',
            'runbook' => 'docs/runbooks/growth.md',
            'paging' => 'email',
            'description' => 'Monthly subscription churn rate exceeded 5% — investigate cancel_reason distribution.',
        ],

        // Phase-34 GROWTH-ENGAGEMENT-3
        [
            'key' => 'low_engagement_landlord',
            'severity' => 'sev4',
            'threshold' => 30,
            'window' => '24h',
            'gauge' => 'landlord_engagement_score',
            'runbook' => 'docs/runbooks/growth.md',
            'paging' => 'email',
            'description' => 'A paying landlord dropped below engagement score 30 — customer-success outreach recommended.',
        ],

        // Phase-35 PLATFORM-METER-3
        [
            'key' => 'high_metered_overage',
            'severity' => 'sev4',
            'threshold' => 1.5,
            'window' => '24h',
            'gauge' => 'metered_usage_ratio',
            'runbook' => 'docs/runbooks/platform.md',
            'paging' => 'email',
            'description' => 'A paying landlord exceeded 1.5x plan limit on a metered feature — overage outreach recommended.',
        ],

        // Phase-36 INSIGHT-CRON-3
        [
            'key' => 'high_cron_runtime',
            'severity' => 'sev3',
            'threshold' => 60,
            'window' => '24h',
            'gauge' => 'cron_runtime_total_minutes_24h',
            'runbook' => 'docs/runbooks/insight.md',
            'paging' => 'email',
            'description' => 'Total daily cron runtime exceeded 60 minutes — investigate per-command profiling.',
        ],

        // Phase-39 VENDOR-OBSERV-2
        [
            'key' => 'vendor_flap',
            'severity' => 'sev4',
            'threshold' => 0.10,
            'window' => '5m',
            'gauge' => 'analytics_forwarder_error_rate',
            'runbook' => 'docs/runbooks/vendors.md',
            'paging' => 'email',
            'description' => 'Analytics vendor (PostHog or future) returning >10% error rate over the last batch — data loss accumulating.',
        ],

        // Phase-38 DEFER-BUILD-CI-3
        [
            'key' => 'stale_bundle_warning',
            'severity' => 'sev4',
            'threshold' => 24,
            'window' => 'instantaneous',
            'gauge' => 'bundle_age_hours_since_last_fe_commit',
            'runbook' => 'docs/runbooks/cleanup.md',
            'paging' => 'email',
            'description' => 'public/build/manifest.json is older than the newest FE commit — the dev/prod server is serving a stale bundle. Rebuild via `npm run build`.',
        ],

        // Phase-37 PWA-GATEWAY-3
        [
            'key' => 'high_gateway_proration_drift',
            'severity' => 'sev3',
            'threshold' => 5,
            'window' => '24h',
            'gauge' => 'subscription_proration_drift_count_24h',
            'runbook' => 'docs/runbooks/pwa-depth.md',
            'paging' => 'email',
            'description' => 'More than threshold UPGRADE subscription_changes rows could not be reconciled with Paystack in the last 24h.',
        ],

        // Phase-40 GATEWAY-RECONCILE-3
        [
            'key' => 'gateway_drift',
            'severity' => 'sev3',
            'threshold' => 5,
            'window' => '24h',
            'gauge' => 'gateway_reconcile_drift_total',
            'runbook' => 'docs/runbooks/payments.md',
            'paging' => 'email',
            'description' => 'Payment gateway ledger (Paystack or Stripe) drifted from local payments table by more than the threshold count in the last 24h. Investigate via payments:gateway-reconcile.',
        ],

        // Phase-49 PARTS-INVENTORY-3
        [
            'key' => 'parts_below_threshold',
            'severity' => 'sev4',
            'threshold' => 1,
            'window' => '24h',
            'gauge' => 'parts_below_threshold_count',
            'runbook' => 'docs/runbooks/maintenance.md',
            'paging' => 'email',
            'description' => 'One or more parts have qty_available <= reorder_threshold. Operational signal — landlord should reorder before the next ticket needs the part.',
        ],

        // Phase-66 GROWTH-OBSERVABILITY-1
        [
            'key' => 'nps_negative',
            'severity' => 'sev4',
            'threshold' => 0,
            'window' => '90d',
            'gauge' => 'nps_score',
            'runbook' => 'docs/runbooks/growth.md',
            'paging' => 'email',
            'description' => 'Platform NPS turned negative (more detractors than promoters) over the rolling 90d window with a meaningful sample (>=10 responses). Review recent detractor comments and the last few releases.',
        ],

        // Phase-67 INBOX-OBSERVABILITY-1
        [
            'key' => 'inbox_attachment_infected',
            'severity' => 'sev2',
            'threshold' => 0,
            'window' => '24h',
            'gauge' => 'inbox_attachment_infected_24h',
            'runbook' => 'docs/runbooks/inbox.md#attachment-malware-detected',
            'paging' => 'page',
            'description' => 'The attachment scanner blocked one or more infected uploads in the last 24h. Malware reached the upload boundary — confirm it was contained and identify the sender.',
        ],

        // Phase-68 STALE-SWEEP-3
        [
            'key' => 'legal_hold_stale',
            'severity' => 'sev3',
            'threshold' => 0,
            'window' => 'instantaneous',
            'gauge' => 'legal_hold_stale_count',
            'runbook' => 'docs/runbooks/legal-hold.md#stale-holds',
            'paging' => 'email',
            'description' => 'One or more legal holds have been active past the stale threshold (litigation likely resolved). The owning landlord is reminded by email; review and release holds no longer required so retention can resume.',
        ],
    ],
];
