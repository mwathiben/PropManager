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
| RTL visual regression count | 5 | `rtl_regression_count` gauge from `npm run test:rtl` Playwright snapshot diff in CI | 24h | 5+ snapshots over `maxDiffPixelRatio: 0.01` in a 24h window means a visible RTL layout broke (absolute-positioned overlay still using `left:`, flex row that didn't get `flex-row-reverse`, Tailwind LTR residue past the codemod). Phase-44 VISUAL-REGRESSION-1/2/3 / sev3. | Open the diff images; locate the offending commit via `npm run test:rtl -- --reporter=html`; either fix the layout OR `npm run test:rtl:update` if the change is intentional and commit the new baselines |
| Lease renewal counter offers expired | 5/day | `lease_renewal_counter_expired_count{landlord_id}` gauge from `lease-renewal:expire-stale-counters` cron | 24h | 5+ counter-offers expiring in a day means tenants are countering but landlords aren't responding — outreach trigger. Phase-45 LEASE-COUNTER-3 / sev4. | Pull the landlord_id list from the gauge; send an in-app + email nudge ("Your tenant submitted a counter-offer N days ago"); escalate to support if >14 days |
| Payment plan modification pending | 7 days | `payment_plan_modification_pending_24h{plan_id}` gauge from `payment-plans:audit-stale-modifications` cron (value=days-since-creation) | per-cron | A landlord has ghosted a tenant modification request for >7 days. Financial-distress UX risk — the tenant cannot reschedule installments while the modification is pending. Phase-45 PAY-PLAN-MOD-3 / sev3. | Open `/finance/payment-plans?status=modified_pending` for the affected plan_id; nudge the landlord by email; if no response in 14 days, support manually approves or rejects on landlord's behalf |
| Canonical mirror drift (pinned) | 0 | `canonical_mirror_drift_count{mirror}` gauge from `onboarding:dedupe-audit` cron (one entry per `config('onboarding.mirrors')` row) | per-cron | Any drift on a pinned mirror means a saving listener regressed silently — a future code change broke the canonical→users.* fan-out. Phase-46 CANONICAL-AUDIT-1/2 / sev3. | Run `php artisan onboarding:dedupe-audit --verbose-rows`; identify which mirror drifted (`users.profile_photo_path` etc.); locate the regressed listener (typically a Model::booted() block) and ship the backfill + listener fix in one CL |
| Canonical mirror drift (loose) | 5/24h | same gauge for non-pinned entries | 24h | Lower-stakes denormalisation drift — not a paging emergency, but signals data quality decay. Phase-46 CANONICAL-AUDIT-2 / sev4. | Queue a fix for the next sprint; if drift accumulates >50, promote the mirror to pinned |
| Onboarding session abandoned | 20/week | `onboarding_session_abandoned_count` gauge from `onboarding:nudge-stalled` cron (sealed in last 7 days) | per-cron | Growth-team signal, not a fire — landlords intending to sign up are dropping mid-wizard. Phase-46 PROGRESS-RESUME-3 / sev4. | Review the abandoned sessions for current_step distribution; high abandonment on a specific step signals a UX problem worth investigating in the next polish cycle |
| Tenant KYC blocked | 20/24h | `tenant_kyc_blocked_count` gauge (visibility — emitted by future audit cron; threshold is the contract for now). Tenants who hit the Phase-48 wizard step 2 gate without submitting required docs. | 24h | Growth-funnel signal — if the count spikes, the landlord's KYC requirement set is too strict or the tenant flow is confusing. Phase-48 TENANT-KYC-BRIDGE / sev4. | Review the landlord's `kyc_requirements` set via `/settings/kyc-requirements`; review the tenant cohort's `User::kycProgress()` shapes to find the specific blocker requirement |
| Ticket resolution SLA breach | 1/24h | `ticket_resolution_breach_count{priority}` gauge from `tickets:audit-sla` cron — tickets past `resolution_due_at` with `resolved_at NULL` and status NOT IN (resolved, closed, cancelled). | per-cron | Contractual landlord SLA miss — different from first-response breach (caretaker responded but ticket sat for days). Phase-49 TICKETS-SLA-DEEP / sev3. | Inspect the ticket via the priority breakdown; reassign caretaker / call vendor / verify parts are in stock; if pattern is per-landlord, review their `sla_definitions` overrides for over-aggressive resolution windows |
| Parts below threshold | 1/landlord/24h | `parts_below_threshold_count{landlord_id}` gauge from `parts:audit-stock` cron — `qty_available <= reorder_threshold AND is_active`. | 24h | Operational reorder signal — not a fire. Phase-49 PARTS-INVENTORY-3 / sev4. | `Part::belowThreshold()->get(['landlord_id', 'name', 'qty_available'])` — share with landlord for reorder; if same SKU repeatedly fires, raise `reorder_threshold` |
| Landlord maintenance cost 30d | (visibility only — no threshold) | `landlord_maintenance_cost_kes_30d{landlord_id}` gauge from `maintenance:cost-rollup` weekly cron. | rolling-30d | Trend gauge for ops dashboards — landlord spend on tickets over last 30 days. Phase-49 MAINTENANCE-COSTS-3 / no paging. | No on-call action; consumed by landlord cost-attribution panels |
| Report render failure count | 3 | `report_render_failure_count` derived from log scraping on ReportBuilderService::run / DashboardService::buildPayload / ScheduledController::preview 5xx responses. | 15m | 3+ 5xx within 15 minutes is either a malformed saved-report config breaking many landlords (allowlist regression) or a DB outage. Phase-50 REPORTS-DEPTH / sev3. | Pull `storage/logs/laravel.log`; look for traces beginning with `ReportBuilderService::run` or `DashboardService::buildPayload`. Single-landlord ValidationException = working as designed; broad 5xx = page SRE |
| Vue preview poll pause count | (visibility only — no threshold) | `vue_preview_poll_pause_count` — client-side counter incremented by Scheduled.vue every time the `document.visibilitychange` listener pauses the preview poll. Telemetry wiring deferred until the frontend telemetry pipeline exists. | rolling-session | Visibility gauge that proves the Phase-51 bandwidth optimisation is actually firing — N pauses/session means the visibility-aware pause is paying off. Phase-51 SCHEDULED-PREVIEW-UX-1 / no paging. | No on-call action; consumed by frontend cost-of-polling reporting once wired |
| i18n translation spend 24h | $20/day (default; configurable via `I18N_DAILY_BUDGET_USD`) | `i18n_translation_spend_usd_24h` — Laravel Cache gauge incremented by `TranslationCostTracker::record` after each successful Google/DeepL translate call (zero for stub). | rolling-24h | A runaway `lang:suggest --apply` loop or buggy retry could rack up $100s in a day. CostAwareDriver wraps every non-stub driver and refuses to call past budget — but the alert fires before that hard cap so ops can decide (raise budget vs kill the process). Phase-52 COST-GUARD-1/3 / sev3. | Review last 24h `lang:suggest --apply` invocations via shell history + `storage/logs/laravel.log` (CostAwareDriver logs each refusal); if legitimate, raise `I18N_DAILY_BUDGET_USD`; if loop bug, kill the process + revert affected lang files via `.bak.<timestamp>` snapshots LangFileWriter created |
| i18n translation spend per-locale 24h | (visibility only — no threshold) | `i18n_translation_spend_usd_24h{locale}` — same source as the total, partitioned by target locale. | rolling-24h | Per-locale attribution — which locale is eating the budget. Phase-52 COST-GUARD-2 / no paging. | No on-call action; consumed by translation cost dashboards |

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

## Lease lifecycle (Phase-61)

| Signal | Threshold | Source | Window | Rationale | On-call action |
|--------|-----------|--------|--------|-----------|----------------|
| `lease_pause_resumed_count` | visibility-only | Phase-61 PAUSE-2 daily 06:00 cron | per-day | Volume signal — sustained high values surface a pattern of granted pauses, useful for hardship-program analysis. | Review pause reasons distribution; cross-reference with churn dashboard. |
| `lease_auto_renewed_count` | visibility-only | Phase-61 RENEWAL-AUTO-2 daily 07:00 cron | per-day | Revenue-continuity signal — a sudden drop means many landlords flipped auto_renew=false (renegotiation pressure) or counter-proposals are blocking. | Drill down by landlord_id; flag those with disproportionate opt-outs. |
| `lease_termination_pending_count` | visibility-only | Phase-61 TERMINATION-2 service-emitted | rolling 7d | Stuck approval queue — high values mean either landlords aren't responding to tenant-initiated terminations within a week, or vice versa. | Page support to nudge stuck parties; review termination_reason distribution. |

## Plan management (Phase-60)

| Signal | Threshold | Source | Window | Rationale | On-call action |
|--------|-----------|--------|--------|-----------|----------------|
| `plan_feature_denied_count{feature}` | visibility-only | Phase-60 FEATURE-GATES-2 inline on denial | rolling 1h | High values indicate landlords hit plan walls frequently — could be confusing UX or pricing-tier misfit. | Review by feature; consider lifting the gate on lowest-tier or tweaking copy. |
| `trial_expired_count` | visibility-only | Phase-60 TRIAL-DEPTH-3 daily 09:30 cron | per-day | Expected behaviour — high values surface trial-abuse signals (same email reusing free trial via burner accounts). | Cross-reference with signup_count to compute trial-to-paid conversion rate. |
| `coupon_redeemed_count{code}` | visibility-only | Phase-60 COUPONS-2 inline on redeem | rolling 24h | Marketing campaign signal; sudden spike on a single code could indicate code-sharing abuse outside intended channel. | Review max_redemptions on the affected coupon; consider rotating the code. |

## Storage hardening (Phase-59)

| Signal | Threshold | Source | Window | Rationale | On-call action |
|--------|-----------|--------|--------|-----------|----------------|
| `files_retention_purged_count{subject}` | visibility-only | Phase-59 FILE-RETENTION-2 daily 02:30 cron | per-day | Tracks purge volume per subject so a sudden zero (cron skipped) or huge spike (policy misconfig) surfaces. | Investigate retention cron health |
| `file_access_anomaly_count{action}` | > 0 for 10min | Phase-59 ACCESS-AUDIT-3 every-5min cron | rolling 5min | A single user exceeding 50 downloads in 5 minutes is bot-like; investigate token theft / scraping. | Page on-call; lock affected user account pending review |

## PWA offline depth (Phase-62)

| Signal | Threshold | Source | Window | Rationale | On-call action |
|--------|-----------|--------|--------|-----------|----------------|
| `offline_writes_dead_letter_count` | 50 / 24h cluster-wide | Phase-62 OFFLINE-WRITES-3 client-side IDB telemetry, posted via `/api/v1/telemetry/pwa` from `sendBeacon` on `visibilitychange` (Phase-64 TELEMETRY-WIRE-1/2) | rolling 24h | Sustained dead-letter accumulation indicates a broken replay payload (server-side validation that can't be satisfied by the queued write). Phase-62 OFFLINE-WRITES-3 + Phase-64 TELEMETRY-WIRE / sev4. | Inspect the dead-letter store via DevTools (Application → IndexedDB → pm-offline-writes → dead-letter); read the `lastError` field to identify the failing route family + 4xx body; if pattern is broad, check for a recent validation tightening that breaks queued payloads. |
| `offline_photo_quota_evictions_count` | visibility-only | Phase-62 OFFLINE-PHOTOS-3 enforceBudget logger, posted via `/api/v1/telemetry/pwa` from `sendBeacon` on `visibilitychange` (Phase-64 TELEMETRY-WIRE-1/2) | rolling 24h | Users hitting the 50MB photo budget repeatedly — surfaces either a hostile-environment Kenya use case (caretakers backlog 100s of photos before reconnect) or a bug where discardPhoto isn't firing on successful upload. Phase-62 OFFLINE-PHOTOS-3 + Phase-64 TELEMETRY-WIRE / sev4. | Confirm via UX research whether 50MB is too small; consider raising PHOTO_BUDGET_BYTES or surfacing storage usage in the in-app UI; verify discardPhoto runs on Inertia onSuccess for normal cases. |
| `offline_shell_boot_count` | visibility-only | Phase-62 CACHE-STRATEGY-2 SW navigation handler, posted via `/api/v1/telemetry/pwa` from `sendBeacon` on `visibilitychange` (Phase-64 TELEMETRY-WIRE-1/2) | rolling 24h | Volume signal — how often the offline shell satisfies a navigation. High counts mean offline boot is paying off; sustained zero means the precache isn't landing (auth redirect, build-time asset miss). Phase-62 CACHE-STRATEGY-2 + Phase-64 TELEMETRY-WIRE / no paging. | Confirm /dashboard precache hit at install via DevTools (Application → Cache Storage → pm-shell-v1); if missing, the build may not be including the dashboard route in the precache manifest. |

## Communication inbox (Phase-63)

| Signal | Threshold | Source | Window | Rationale | On-call action |
|--------|-----------|--------|--------|-----------|----------------|
| `inbox_unread_fallback_count` | 1000 / 15m cluster-wide | `messages:notify-unread-fallback` cron (Phase-63 INBOX-NOTIFY-3) | per-cron tick | A sudden spike beyond ~1k fallbacks per 15-minute tick almost always indicates a Reverb outage: the in-app push never reached recipients, so the digest is catching every unread message. Phase-63 INBOX-NOTIFY-3 / sev3. | Check Reverb health (`/reverb/health` or container logs); restart broker if unresponsive; once recovered, the next cron tick should drop back to baseline (single digits per tick during normal operation). |
| `inbox_rate_limit_hits_count` | visibility-only | `RateLimiter::for('messages')` 429 response path (Phase-63 INBOX-MOD-3) | rolling 24h | A spike points to either a UX bug (form-state retry loop) or a compromised account doing burst-send. Phase-63 INBOX-MOD-3 / sev4. | Check `web` log channel for the spiking `user_id`; if a single user dominates, inspect their session activity + force password reset if compromise suspected. |

## Where to edit

1. **First**: this document. Record the new threshold + rationale.
2. **Then**: the env var (per the Source column).
3. **Verify**: redeploy + watch logs/metrics for one cycle to confirm the alert fires (or doesn't) as expected.

## Cross-references

- Phase-13 BREACH-6 drill runbook: `docs/runbooks/breach-drill.md`
- Phase-14 OBSERV-5 SLOs: `docs/runbooks/slo.md`
- Phase-12 disaster recovery: `docs/runbooks/disaster-recovery.md`
