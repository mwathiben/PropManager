# Breach Response Drill — Annual Tabletop Exercise

**Owner**: CISO / On-call rotation (rotates annually)
**Cadence**: Annual — run on or before April 30th each year
**Prior runs**: Track in `docs/runbooks/breach-drill-log.md` (template at bottom)

Phase-13 BREACH-6: Without an annual tabletop exercise the team does not know whether the Kenya DPA Section 43 / GDPR Article 33 72-hour notification clock can actually be met under realistic constraints (legal review, ODPC form submission, affected-subject email drafting, ops sign-off).

## Scenario library

Pick one per drill — rotate so the team sees a different failure surface each year.

### Scenario A — stolen laptop with .env on it
A backend engineer's laptop is stolen. `.env` contains `APP_KEY`, M-Pesa credentials, AWS access keys, and a copy of the prod DB credentials. Drill objective: validate the credential-rotation runbook + the breach-notification path.

### Scenario B — S3 bucket misconfiguration
The backups bucket is left readable for 6 hours. The bucket contains the last 24h of backups (~1 GB) and includes the audit log. Drill objective: validate the affected-subject identification process (who is in those rows?) + the regulator notification.

### Scenario C — credential-stuffing burst
50+ tenants report being locked out within the same hour. `failed_login` SecurityLog rows match a credential-stuffing pattern. Drill objective: validate IncidentDetector escalation + Phase-1b detector ladder.

## Drill timeline (72-hour SLA budget)

| Hour | Phase | Owner | Action |
|------|-------|-------|--------|
| T+0 | Detect | On-call | Verify SecurityIncident exists (auto from IncidentDetector or manual via `dpa:initiate-breach`) |
| T+0:30 | Triage | On-call + Eng lead | Assess scope, affected dataset, blast radius |
| T+2 | Contain | Eng | Rotate credentials, revoke sessions, take adversary off the system |
| T+6 | Legal review | Legal + CISO | Confirm regulator-notification requirement under Section 43 |
| T+12 | Subject scope | Eng | SQL extract of affected user_ids; queue with `dpa:notify-affected-subjects` |
| T+24 | Mitigation comms | Eng + Comms | Customer-facing status; internal heads-up |
| T+48 | Regulator draft | Legal | ODPC notification draft, including BREACH-1 incident.json export |
| T+60 | IMMINENT alert | Auto (`breach:escalate-overdue`) | If `odpc_notified_at` still NULL, escalation kicks in |
| T+72 | Hard deadline | Legal | ODPC notification sent; record via `dpa:mark-regulator-notified --reference=ODPC/...` |
| T+72+ | OVERDUE alert | Auto (`breach:escalate-overdue`) | Continues hourly until acknowledged — drill must not let this fire |

## During-drill checklist

- [ ] Incident created and visible in `security_incidents` table
- [ ] `notification_deadline = reported_at + 72h` (verify)
- [ ] `review_due_at = reported_at + 30d` (BREACH-7)
- [ ] `BreachReportedAlert` email reached `KENYA_DPA_BREACH_EMAIL`
- [ ] Detector escalation paths exercised — at least one of failed-login burst, webhook flood, impersonation frequency, large export
- [ ] `notifyAffectedSubjects` queued (drill scope: queue without dispatching — or use a `--queue=test` flag if added later)
- [ ] `dpa:mark-regulator-notified` ran inside the 72h window — escalation must NOT fire
- [ ] All actions captured in `audit_logs` and `security_logs`

## Post-drill review (mandatory)

Within 14 days of the drill:

1. Fill in the run log at `docs/runbooks/breach-drill-log.md`.
2. List gaps surfaced: did any phase exceed budget? Was a control missing?
3. File a PR with the gap fixes — title: `fix(breach-drill-YYYY): <one-line summary>`.
4. Update this runbook with new scenarios or revised timelines.

## Post-incident review template

Used for both drills and real incidents. Linked from `breach:review-overdue` paging at 30 days post-report.

```markdown
# Post-Incident Review — Incident #N (YYYY-MM-DD)

## Summary
(One paragraph.)

## Timeline
- T+0 (detection): ...
- T+X (containment): ...
- T+Y (regulator notification): ...
- T+Z (affected subjects notification): ...

## Root cause
(What allowed this to happen?)

## Mitigation
(What stopped it? What remains?)

## Recurrence prevention
- [ ] Action 1 (owner, due date)
- [ ] Action 2 (owner, due date)
- [ ] Action 3 (owner, due date)

## Compliance posture
- Section 43 72h budget met: ___ (yes/no, hours used: ___)
- Article 34 subject notification dispatched: ___ (yes/no)
- ODPC reference recorded: ___

## Sign-off
- Eng lead: ___
- CISO: ___
- Legal: ___
- Date: ___
```

Once filed, run `php artisan dpa:mark-review-complete --incident=N --notes='<wiki/url ref>' --confirm`.
