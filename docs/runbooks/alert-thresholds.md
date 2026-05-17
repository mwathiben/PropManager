# Alert Threshold Catalog

Phase-14 OBSERV-8: alert thresholds were scattered across env vars and config files; tuning one without considering the others created inconsistent paging budgets. This document is the central catalog.

When tuning, edit here first + record the rationale; then change the env var. The values listed are the current defaults.

## Authentication / authorization

| Signal | Threshold | Source | Window | Rationale | On-call action |
|--------|-----------|--------|--------|-----------|----------------|
| Failed-login burst (one account) | 50 | `DETECTION_FAILED_LOGIN_BURST_THRESHOLD` | 1h | Credential-stuffing pattern across many IPs targeting one account. Phase-13 BREACH-2. | Verify the targeted account; force password reset; review IPs |
| Failed-login per IP | 5 | `FAILED_LOGIN_THRESHOLD` | 15m | Per-IP brute force. Phase-5 RATE. | Auto-blocks IP; investigate if recurring |
| Account lockouts | 10 | `LOCKOUT_DURATION` (15m default) | 15m | Phase-5 RATE-8. | Cross-check with failed-login burst |
| Impersonation frequency | 5 | `DETECTION_IMPERSONATION_THRESHOLD` | 1h | Admin session compromise or abuse. Phase-13 BREACH-5. | Verify admin's session not stolen; review impersonation targets |
| Role escalation to landlord without invitation | 1 | (no threshold — always fires) | per-event | Should never happen via the normal flow. Phase-13 BREACH-2. | Investigate ASAP |

## Payment / webhook

| Signal | Threshold | Source | Window | Rationale | On-call action |
|--------|-----------|--------|--------|-----------|----------------|
| Webhook signature failures from one IP | 10 | `DETECTION_WEBHOOK_SIGNATURE_THRESHOLD` | 1m | Replay attack or misconfigured upstream integration. Phase-13 BREACH-2/5. | Add IP to deny-list; investigate intent |
| Unresolved webhook dead-letter rows | 50 | `DEAD_LETTER_ALERT_THRESHOLD` | (count) | Payment reconciliation backlog. Phase-12 RETAIN-7/8. | Process DLQ rows; investigate root cause |
| Payment-config change | 1 | (no threshold — always logs) | per-event | OBS-6. | Verify actor is authorised |
| Stripe Connect payout failures | 5 | `stripe_payout_failure_count` gauge from `payouts:stripe-balance-audit` + `payout.failed` webhook | 24h | Landlord payout delivery has stalled (closed bank account, identity-verification expired, Connect status drift). Phase-42 PAYOUT-AUDIT-1/2. | Open Stripe Dashboard → Payouts; verify landlord's bank account; run `StripeConnectService::syncAccountStatus`; check stripe_connect_status column |
| i18n missing keys — pinned namespace | 0 | `i18n_missing_keys_count{namespace,locale}` gauge from `lang:audit` (config('i18n.pinned_namespaces')) | per-cron | Pinned-namespace drift (`auth`/`common`/`validation`/`payments`) means a fall-through to English for a critical user flow. Phase-43 LANG-AUDIT-1 / sev3. | Run `php artisan lang:check --pinned-only` locally; raise PR with the missing-key fills; CI gates the merge |
| i18n missing keys — loose namespace | 10 | same gauge for non-pinned namespaces | 24h | Translator backlog. Less urgent; soft signal. Phase-43 LANG-AUDIT-1 / sev4. | Queue translator work; use `php artisan lang:suggest <namespace>` for stub fills |

## Compliance / breach

| Signal | Threshold | Source | Window | Rationale | On-call action |
|--------|-----------|--------|--------|-----------|----------------|
| Breach notification 72h SLA imminent | 12h before deadline | `--imminent-hours=12` arg to `breach:escalate-overdue` | per-hour | Kenya DPA Section 43 / GDPR Article 33. Phase-13 BREACH-3. | Notify ODPC NOW |
| Breach notification 72h SLA overdue | 0 | (any past-deadline incident) | per-hour | Section 43 reporting failure. Phase-13 BREACH-3. | Notify ODPC + write incident retrospective |
| Post-incident review 30d overdue | 0 | (any past-30d incident with review_completed_at null) | weekly | Phase-13 BREACH-7. | File the review; run `dpa:mark-review-complete` |
| Affected-subject notification overdue | (operator judgement) | manual | per-incident | Article 34. Phase-13 BREACH-4. | Operator queues via `dpa:notify-affected-subjects` |

## Operational

| Signal | Threshold | Source | Window | Rationale | On-call action |
|--------|-----------|--------|--------|-----------|----------------|
| failed_jobs growth | 25 new in 24h | `failed_jobs_alert_threshold` | 24h | Worker wedged or poison-pill job. Phase-5 OBS-13. | Investigate failing job; clear poison; restart workers |
| Queue depth | 1000 | `queue.health.depth_threshold` | (instantaneous) | Workers not keeping up. Phase-14 OBSERV-3. | Scale workers; investigate slow handler |
| Backup age | 24h since last successful run | `backup.monitor_backups.health_checks.MaximumAgeInDays` | (per-run check) | Phase-12 BACKUP-1. | Investigate backup:run failure; check disk space |
| Backup size | 0 / drastic shrink | (per-run check) | (per-run check) | Phase-12 BACKUP-2. | Restore-test the last good backup |
| Schedule task missed | 24h since last run | implicit (schedule channel logs) | per-day | Phase-13 BREACH-3 / RETAIN-1 etc. | Investigate scheduler / cron health |

## Cross-border (Section 48)

| Signal | Threshold | Source | Window | Rationale | On-call action |
|--------|-----------|--------|--------|-----------|----------------|
| Backup disk in non-adequate region | 1 | Phase-13 DPA-2 boot warning | boot | Section 48 transfer without safeguards. | Add SCCs / BCRs OR move bucket region |
| Sentry DSN in non-adequate region | 1 | Phase-13 DPA-2 boot warning | boot | Same. | Use Sentry EU project or self-hosted DSN |

## Where to edit

1. **First**: this document. Record the new threshold + rationale.
2. **Then**: the env var (per the Source column).
3. **Verify**: redeploy + watch logs/metrics for one cycle to confirm the alert fires (or doesn't) as expected.

## Cross-references

- Phase-13 BREACH-6 drill runbook: `docs/runbooks/breach-drill.md`
- Phase-14 OBSERV-5 SLOs: `docs/runbooks/slo.md`
- Phase-12 disaster recovery: `docs/runbooks/disaster-recovery.md`
