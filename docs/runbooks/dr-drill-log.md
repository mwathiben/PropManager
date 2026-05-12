# DR Drill Log

Quarterly restore drills exercise the disaster-recovery posture
documented in `disaster-recovery.md`. An empty drill log means there
is no DR posture, regardless of how thoroughly the automation is
configured.

## Cadence

First Monday of every quarter (Q1: January, Q2: April, Q3: July,
Q4: October). Owner: rotates through the on-call list.

## Procedure (reference)

See `docs/runbooks/disaster-recovery.md`, section
"Quarterly restore drill". Each entry below records the outcome of
one execution of that procedure.

## Drill entries

> Format: copy the template below, fill in the values, append to
> the table. Most recent at the top.

| Quarter | Date | Owner | Backup SHA | RPO observed | RTO observed | Smoke-test results | Outcome | Notes |
|---------|------|-------|------------|---------------|---------------|---------------------|---------|-------|
| Q2 2026 | _pending_ | _pending_ | _pending_ | — | — | — | — | First drill required by end of June 2026. |

### Template (copy to add an entry)

```
| Q? YYYY | YYYY-MM-DD | owner@example.com | backup-YYYY-MM-DD-HH-MM-SS.zip | 8h | 1h22m | users=2401, leases=873, invoices=15203, latest_payment=2026-04-15 14:33 | PASS | Notes... |
```

## Failed-drill protocol

A failed drill (any smoke-test row count outside ±5% of production
same-day, or restore time over RTO target) is a **P0 incident**.
File an incident in the standard tracker, pull the on-call rotation,
and DO NOT mark the drill as PASS until the underlying issue is
resolved.

## Compliance reporting

Kenya DPA Section 41 (security and integrity) requires demonstrable
DR posture. This log is the artefact regulators inspect. Two
consecutive missed quarterly drills triggers the same incident-
response severity as a failed drill.
