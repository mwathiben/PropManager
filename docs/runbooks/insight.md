# Insight runbook — Phase 36

Stub. Full content ships with INSIGHT-CI-3 in Phase 2.

## Alerts handled

- `high_cron_runtime` (sev3) — total cron runtime > 60 minutes in 24h.

## high_cron_runtime playbook

1. Pull `cron:budget-audit` output for the per-command breakdown.
2. Identify the runaway command (typically heaviest gap from baseline).
3. Profile the specific command + add an index / cap the chunk / split into smaller jobs as needed.
4. Re-run audit to verify total < 60 minutes.
