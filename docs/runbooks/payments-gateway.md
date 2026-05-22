# Payments Gateway Runbook (Phase 85 depth)

Operator reference for the Phase-85 [PAYMENTS-GATEWAY-DEPTH] additions on top of
the mature Phase 40-42 gateway layer (Paystack / M-Pesa / IntaSend / Stripe,
signature verification, dual-layer idempotency, refunds, reconciliation).

## Gateway reconciliation view (RECON-VIEW)

The daily reconciler persists a `ReconciliationReport` per (landlord, gateway)
and emails a `ReconciliationAlert` on discrepancies. Phase 85 adds the in-app
view so a landlord can see + act on them (the older `/finances/reconciliation`
UI is BANK-statement recon, a different thing):

- `GET /gateway-reconciliation` (`gateway-reconciliation.index`) — recent reports
  + open disputes + failed refunds needing attention. Linked from the Finances
  bank-reconciliation tab.
- `GET /gateway-reconciliation/{report}` (`.show`) — the stored discrepancies
  (missing_locally | missing_remotely | amount_mismatch, local vs remote amount).
- Owner-scoped (`landlord_id`); read-only.

## Stripe in the daily run (RECON-STRIPE)

`reconciliation:run-daily` (`DailyPaymentReconciliation`) previously reconciled
only Paystack. It now reconciles EVERY gateway a landlord has configured —
`processLandlord` runs `reconcilePaystack` when paystack is enabled AND
`reconcileStripe` when stripe is enabled, storing a report per gateway.
`payments:reconciliation-rollup` (weekly Sun 05:25) emits
`landlord_gateway_discrepancies{gateway}` (visibility-only).

## Refund retry (REFUND-RETRY)

`refunds:retry-failed` (daily 05:30) re-attempts FAILED refunds via
`RefundService::retry`, which is **idempotent by design**:
- A failed refund WITHOUT a gateway reference never reached the gateway → safe to
  re-process (`processRefund`); `retry_count` increments (capped at 3).
- A failed refund WITH a reference (`paystack_refund_reference` /
  `mpesa_conversation_id`) already created a gateway refund — re-calling would
  **double-refund**, so it is flagged `needs_review` and NEVER auto-re-called; a
  human resolves it. Surfaced on the reconciliation view.
- `refunds_failed_count` gauge per landlord.

## Disputes / chargebacks (DISPUTE)

Stripe `charge.dispute.created` now records a first-class `PaymentDispute`
(linked to the Payment behind the disputed `payment_intent`) + notifies the
landlord (`payment_dispute` notification type, IMPORTANT so it reaches email +
in-app). `charge.dispute.closed` updates the record to won/lost. The ops-facing
`OperationalIncident` log is unchanged.

**No auto-reversal**: a dispute (even "lost") does NOT auto-void the Payment or
reverse the Invoice — disputes can be won, and reversal is an operator decision.
Adding the `payment_dispute` notification type required three layers (const +
TYPE_URGENCY_MAP, `notification_preferences.payment_dispute_enabled`,
`notifications.type` ENUM value).

| Symptom | Where to look |
| --- | --- |
| Discrepancies not visible | gateway-reconciliation.index — reports persist daily. |
| Stripe never reconciled | Landlord needs stripe_enabled + stripe_secret_key; check the daily run. |
| Failed refund stuck | If it has a gateway reference it's needs_review (manual); else the cron retries (cap 3). |
| Dispute not recorded | Only attributed when the disputed intent matches a Payment (paystack_reference); else logged to incidents only. |
