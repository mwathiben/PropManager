# Growth runbook — Phase 34

Stub runbook so config/alerts.php references resolve. Full content
ships with Phase 2 GROWTH-CI-3.

## Alerts handled

- `high_churn_rate` (sev2, monthly churn > 5%) — see [investigation
  playbook](#high-churn-rate-playbook).
- `low_engagement_landlord` (sev4) — added in Phase 1d.

## high_churn_rate playbook

1. Pull last 30 days of cancelled subscriptions grouped by
   `cancel_reason`.
2. Split voluntary (too_expensive / missing_features / switching /
   business_closing) from involuntary (technical_issues, usually
   failed payment).
3. Voluntary churn spike: bring to product review for prioritisation.
4. Involuntary churn spike: check Paystack webhook health and
   dunning-email delivery.
