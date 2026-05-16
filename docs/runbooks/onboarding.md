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
