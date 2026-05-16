# Platform runbook — Phase 35

Stub. Full content ships with PLATFORM-CI-3 in Phase 2.

## Alerts handled

- `high_metered_overage` (sev4) — see [overage playbook](#high-metered-overage-playbook).

## high_metered_overage playbook

1. Pull `metered:soft-cap-audit` output for the offending landlord+feature pair.
2. If ratio > 2.0 → trigger CS outreach for upgrade conversation.
3. If ratio 1.5-2.0 → automated email nudge offering next-tier upgrade.
