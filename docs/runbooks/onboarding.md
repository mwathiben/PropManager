# Phase-31 Onboarding Runbook

Operator-facing reference for the Phase-31 ONBOARDING surface: wizard
resume, activation funnel telemetry, prospect sample data, contextual
help drawer, empty-state checklists.

## Activation funnel (6 milestones)

Per landlord, recorded write-once in `onboarding_milestones`:

| Order | Milestone | Trigger |
|-------|-----------|---------|
| 1 | `signed_up` | UserObserver on landlord row insert/role-set |
| 2 | `first_property` | PropertyObserver on first row |
| 3 | `first_unit` | UnitObserver on first row |
| 4 | `first_tenant` | UserObserver on tenant role-set |
| 5 | `first_invoice` | InvoiceObserver on first row |
| 6 | `first_payment` | PaymentObserver on first row |

The recorder (`OnboardingMilestoneRecorder::record`) is idempotent —
duplicate calls return the existing row without firing a second
`MilestoneRecorded` event.

## Crons

| Command | Cadence (Africa/Nairobi) | What it does |
|---|---|---|
| `onboarding-wizard:audit` | 04:45 daily | Buckets stalled wizard users by days inactive (1-3, 4-7, 8-30, 30+); emits `onboarding_stalled_count{bucket=X}`. |
| `activation:audit` | 04:15 daily | Emits `activation_signups_count{period}`, `activation_milestone_count{milestone, period}`, `activation_time_to_first_invoice_p50_hours`, `..._p90_hours`. |

## Endpoints (web routes)

- `GET  /onboarding` — wizard entry (redirects to current step or dashboard if complete)
- `POST /onboarding/step/{n}/skip` — skip optional step (records in `skipped_steps`)
- `GET  /api/onboarding/status` — JSON for the dashboard ResumeBanner
- `POST /onboarding/sample-data/populate` — load prospect demo dataset
- `POST /onboarding/sample-data/reset` — undo a previous populate
- `GET  /api/help/contextual?key=X` — per-page articles (HelpDrawer)
- `GET  /api/help/search?q=X` — full-text search (debounced from drawer)
- `GET  /api/onboarding/milestones` — flat boolean map for EmptyState checklist
- `POST /api/onboarding/checklist/dismiss` — landlord dismisses the checklist

## Tables

- `onboarding_milestones`     — write-once funnel ledger (unique landlord+milestone)
- `sample_data_runs`          — sample-data populate log (status machine + row_refs)
- `help_articles.help_key`    — per-route key column for contextual lookup
- `users.onboarding_checklist_dismissed_at` — per-user dismiss flag

## How to investigate

1. **7-day activation rate drops**: check `activation_time_to_first_invoice_p50_hours`. If it climbs, walk `onboarding_milestones` for landlords with `signed_up` but no `first_invoice` within 7 days — file content/UX issue.
2. **Wizard stalls spike**: `onboarding_stalled_count{bucket=8-30}` climbing means the resume banner / step layout has a friction point. Look at `onboarding_progress.current_step` distribution for the stalled cohort.
3. **Sample data not loading for a prospect**: `populate()` refuses when there is any `is_active=true` Lease for the landlord. Have them reset first OR remove the test lease — `reset()` undoes a prior populated run cleanly.
4. **Help drawer empty on a page**: that page is missing a `helpKey` constant — wire `window.__helpKey = 'page.routename.action'` in the Inertia page setup AND seed at least one `help_articles` row with `help_key` matching.

## CI gates
- Phase31OnboardingSurfaceTest — every cron is scheduled, every event has a listener, en/sw onboarding.php key sets match
- Phase31WizardTest — skip/last_touched_at semantics + status endpoint
- Phase31MilestoneTest — recorder idempotency, full funnel records, audit runs
- Phase31SampleDataTest — populate/reset + isolation + role gates
- Phase31HelpDrawerTest — contextual + search + role scoping + auth gate

## Deferrals

None for Phase 31. Sub-scope (NOT PRD findings) deferrals: Lighthouse audit for the new HelpDrawer focus-trap, manual NVDA pass on the ResumeBanner — both fold into the next a11y consolidation cycle.

---

# Phase-46 ONBOARDING-CANONICAL extensions (2026-05-17)

Phase 46 deepens the onboarding surface with a Mirror Registry, an OnboardingFlow value object, a signed-URL resume contract, and a stalled-session nudge cron. The Phase 31 funnel + resume banner stays intact — these are net-new infrastructure on top.

## Mirror Registry (CANONICAL-AUDIT)

`config('onboarding.mirrors')` declares every `users.*` column that denormalises a child-table record. Each entry has `column`, `canonical`, `key`, optional `canonical_filter`, optional `role_scope`, and `pinned` (sev3 at drift > 0 if true; sev4 at threshold 5/24h otherwise).

### Current entries

| Mirror | Canonical | Listener | Pinned |
|--------|-----------|----------|--------|
| `users.profile_photo_path` | `landlord_profiles.profile_photo_path` | `LandlordProfile::booted()` (saved) | yes |
| `users.emergency_contact_name` | `emergency_contacts.name` (is_primary) | `EmergencyContact::booted()` (saved) | yes |
| `users.emergency_contact_phone` | `emergency_contacts.phone` (is_primary) | `EmergencyContact::booted()` (saved) | yes |

### `mirror_exempt` (deprecation allow-list)

| Mirror | Deprecated | Remove by | Reason |
|--------|------------|-----------|--------|
| `users.kyc_completed_at` | 2026-05-17 | 2026-08-17 | Write-only ghost column — `User::hasCompletedKyc()` reads dynamically. Use `User::kycVerifiedAt()` (read-through accessor returning `MAX(reviewed_at)` from approved `tenant_kyc_submissions`) for the timestamp display. |

### Adding a new mirror

1. Declare the entry in `config('onboarding.mirrors')`.
2. Implement the saving listener on the canonical model — `Model::booted()` `static::saved` fans the canonical value into the user row.
3. Backfill via a migration block — walk every canonical row + write the mirror.
4. Verify with `php artisan onboarding:dedupe-audit` — the new entry should report `drift_count = 0`.

### Mirror-drift incident response

`onboarding:dedupe-audit` cron runs daily 03:30 Africa/Nairobi. When `canonical_mirror_drift_count{mirror=...}` > 0 fires:

1. `php artisan onboarding:dedupe-audit --verbose-rows` — see affected user_ids.
2. Inspect the listener on the canonical model.
3. Common causes: listener removed in refactor; `Model::query()->update()` bypassing the `saved` event; schema change broke the column reference.
4. Ship the listener fix + a one-off backfill block in the same CL.

## Wizard infrastructure (WIZARD-INFRA)

### onboarding_sessions

Per-user wizard state. **Not** unique on user_id — a user accumulates historical rows; `OnboardingSession::firstFor($user)` returns the live (`completed_at NULL AND abandoned_at NULL`) row or mints a fresh one.

Shape: `current_step`, `step_history` JSON (each entry `{step, action: advance|back|completed|abandoned|auto_abandoned, at}`), `started_at`, `last_touched_at`, `completed_at`, `abandoned_at`, `last_nudge_sent_at`.

The old `onboarding_progress.step_data` JSON blob stays in deprecation but is no longer the source of truth. Phase 47 [WIZARD-MIGRATE] will move step writes from the JSON blob to canonical-model service calls.

### OnboardingFlow value object

`App\Onboarding\OnboardingFlow::forRole($role)` returns a per-role step sequence:

- **landlord** — 8 steps (Welcome, Profile, First property, Units + building, Payment configuration, Invite team, First tenant, Done)
- **caretaker** — 3 steps (Profile, Building assignment, Notification preferences)
- **tenant** — 3 steps (Profile, KYC verification, Payment method)

### OnboardingSessionService

`advance(session, target, writer)` wraps the caller's canonical write closure in `DB::transaction` — a writer that throws does NOT advance the session.

`back(session, target)` NEVER touches canonical models — by explicit design (WIZARD-INFRA-3). The user can re-edit the previous step's form, but canonical rows don't roll back. step_history records `action='back'`.

`complete(session)` sets `completed_at = now()`. `markAbandoned(session)` sets `abandoned_at = now()`.

## Role dispatch (ROLE-PATHS)

`RegisteredUserController::store` accepts `role in ['landlord','caretaker','tenant']` (nullable, defaults to `tenant`). If `invitation_token` is supplied + the invitation is unaccepted, the **invitation's role overrides** the form choice (prevents privilege escalation). On signup, dispatches `OnboardingSession::firstFor($user)` so the wizard is ready on first dashboard hit.

Onboarding routes carry `verified` middleware — landlords must click the email verification link before reaching the wizard.

Invitations: `invitations.role` defaults to `caretaker` (historical semantic — tenant invitations live in `tenant_invitations`).

## Signed-URL resume (PROGRESS-RESUME)

### Generation

`OnboardingResumeService::generate(OnboardingSession): string` issues a Laravel `temporarySignedRoute` (`EXPIRY_DAYS=7`) keyed on the session id. Persists an `onboarding_resume_links` audit row with `SHA-256(signature)` — replay defence doesn't need to deserialise the URL.

### Consume

`consume(session, signature, ip)` throws `ValidationException` for:
- Unknown signature (no audit row)
- Replay (audit row already `consumed_at NOT NULL`)
- Expired (audit row `signed_until` < now())

Route `/onboarding/resume/{session}` middleware `signed` (Laravel's built-in URL-tamper check) + `OnboardingResumeRedirectController` (calls `consume()`, asserts owner, redirects to the session's `current_step`).

### Nudge cron

`onboarding:nudge-stalled` daily 09:00 Africa/Nairobi:

1. **Nudge**: sessions with `last_touched_at < today-3-days` AND `last_nudge_sent_at NULL OR < now-24h` — generate signed URL via `OnboardingResumeService::generate`; write `last_nudge_sent_at = now()`. Mail dispatch deferred to Phase 47 [WIZARD-MIGRATE] — current implementation logs the URL to laravel.log.
2. **Seal**: sessions with `last_touched_at < today-30-days` — flip `abandoned_at = now()` + append `step_history{action: 'auto_abandoned'}`.

Emits `onboarding_session_abandoned_count` gauge (sealed in the last 7 days; growth signal, sev4).

## Phase-46 troubleshooting

| Symptom | Cause | Fix |
|---------|-------|-----|
| Mirror-drift alert fires | Saving listener regressed | Inspect canonical model `booted()`; ship listener fix + one-off backfill |
| Tenant edits profile but landlord sees stale data | New `users.*` mirror without a listener | Add to `config('onboarding.mirrors')` + listener + backfill |
| Landlord signup fails "role is invalid" | Form submitted an unexpected role value | Verify Register.vue role dropdown only offers landlord/caretaker/tenant |
| Resume link returns 403 | Replay — link already consumed | Re-issue a fresh URL via the cron (next 24h tick) |
| Nudge email missing | Phase 47 mail dispatch not yet shipped | `[onboarding:nudge-stalled] resume URL` line is logged with session_id; copy + send manually if needed |

## Cross-references

- `docs/runbooks/alert-thresholds.md` — sev3/sev4 rows for `canonical_mirror_drift_count` + `onboarding_session_abandoned_count`
- `docs/runbooks/tenant.md` — Phase 45 [TENANT-DEPTH] including EMERGENCY-CONTACT-SMS-3 (the specific case Phase 46 generalises)
- `phase-46-audit-prd.json` — full PRD + audit_closeout
