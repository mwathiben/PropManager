# Blameless Post-Mortem Template

*Phase-32 SRE-INCIDENT-2: standard structure for every OperationalIncident retrospective.*

## 1. Summary

One paragraph: what happened, who/what was affected, how long, and how it was resolved. Read this first; everything else is supporting detail.

## 2. Timeline

UTC timestamps, one event per line.

| Time (UTC) | Event |
|---|---|
| 12:00 | Daraja STK Push success rate drops below 50%. |
| 12:03 | dependency_down alert fires; on-call paged. |
| 12:05 | OperationalIncident sev2 opened. |
| 12:08 | Mitigation: failover to IntaSend as STK backup. |
| 12:18 | Customer-impact ends; incident marked mitigated. |
| 12:45 | Daraja confirms outage resolved; status flipped to resolved. |

## 3. Root cause

What was the *underlying* cause — not "X failed" but *why* X failed. If the root cause is "human error", the post-mortem is incomplete; press on for the systemic gap.

## 4. Contributing factors

Conditions that made the incident more likely, harder to detect, or harder to resolve. Example: "the dependency_down alert only fires on consecutive failures, masking a 30-second flap that would have been an earlier signal."

## 5. Customer impact

Specific user-facing consequences with magnitude (rows, dollars, users affected, downtime minutes). One number per row.

## 6. What went well

Acknowledge the parts of the response that worked. Mute the temptation to be purely negative — reinforcement matters for morale + repeatability.

## 7. Action items

Concrete + owned + dated. One line per action, with the owner and the due date.

| # | Action | Owner | Due |
|---|---|---|---|
| 1 | Add Daraja STK 30-sec rolling success-rate gauge. | @ops | 2026-05-30 |
| 2 | Document the IntaSend STK failover procedure. | @ops | 2026-06-06 |
| 3 | Auto-failover when Daraja drops below 80% over 1 min. | @eng | 2026-06-30 |

## 8. Lessons learned

What this incident taught us about the system, our assumptions, or our processes. Pure narrative — not blame.

---

**Blameless rule**: replace every "X person didn't" with "the system didn't make Y obvious". The system is what's being fixed — people are how we fix it.
