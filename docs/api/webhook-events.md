# Webhook event catalog

Phase-25 API-WEBHOOK-3: consumer-facing catalog of every outbound
webhook event a landlord can subscribe to from the
`/settings/webhooks` UI.

For the operator process behind webhook subscriptions, see
[`docs/runbooks/api-deprecation.md`](../runbooks/api-deprecation.md)
(versioning + deprecation contract applies to event-payload shapes
too).

The single source of truth for the event-type list is
`config/webhooks.php` — the subscription UI's event-picker reads
from it directly, so any event listed here is also selectable.

## Delivery format

Every dispatch is an `application/json` POST with the following
headers:

| Header | Description |
|--------|-------------|
| `Content-Type` | Always `application/json` |
| `User-Agent` | `PropManager-Webhook/1.0` |
| `X-PropManager-Event` | The event type (e.g. `payment.received`) |
| `X-PropManager-Signature` | `sha256=<hex>` HMAC-SHA256 of the raw body, keyed by your subscription's secret |
| `X-PropManager-Delivery-Id` | A unique identifier for this delivery attempt — use it to dedupe replays |
| `X-PropManager-Attempt` | The attempt number, 1-5. Attempt 5 + non-2xx response means we will not retry; check the delivery log for the dead-lettered flag |

### Body shape

```json
{
  "event": "payment.received",
  "data": { ... event-specific payload ... },
  "delivery_id": 12345,
  "dispatched_at": "2026-05-15T13:42:00+00:00"
}
```

### Verifying the signature

```python
import hmac, hashlib
expected = hmac.new(secret.encode(), raw_body, hashlib.sha256).hexdigest()
header = request.headers["X-PropManager-Signature"]  # "sha256=..."
assert hmac.compare_digest(f"sha256={expected}", header)
```

```php
$expected = hash_hmac('sha256', $rawBody, $secret);
$header = $request->header('X-PropManager-Signature'); // "sha256=..."
if (! hash_equals('sha256='.$expected, $header)) {
    abort(403);
}
```

### Retry policy

Non-2xx responses (including timeouts after 10s) trigger exponential
backoff retries: **15s, 60s, 300s, 1800s**. After the 5th attempt the
delivery is dead-lettered — your landlord can manually retry it from
the subscription's delivery-log page, but PropManager will not retry
automatically.

Your endpoint MUST respond 2xx within **10 seconds** to count as
successful.

## Event types

Every event listed below appears in `config/webhooks.php`. The
description matches the UI checkbox copy.

### `payment.received`

A tenant payment has been recorded and applied to an invoice.

```json
{
  "event": "payment.received",
  "data": {
    "payment_id": 12345,
    "invoice_id": 6789,
    "amount": "5000.00",
    "currency": "KES",
    "method": "mpesa",
    "tenant_id": 42,
    "received_at": "2026-05-15T10:00:00+03:00"
  }
}
```

### `payment.refunded`

A payment has been (partially or fully) refunded to the tenant.

### `invoice.created`

A new invoice has been generated for a tenant.

### `invoice.paid`

An invoice has been fully paid off — `amount_paid >= total_due`.

### `invoice.overdue`

An invoice has passed its due date without being fully paid.

### `lease.signed`

A tenant has accepted and signed a lease invitation.

### `lease.expired`

A lease has reached its `end_date`.

### `tenant.invited`

A landlord has issued a tenant invitation.

## Internal: adding a new event type

1. Add the entry to `config/webhooks.php` `events` array.
2. Document the payload shape in the **Event types** section above.
3. Wire the dispatch site (the model event / listener that fires the
   webhook) to call `DeliverWebhookJob::dispatch($subscriptionId, $eventType, $payload)`
   — fan out across every active subscription that has opted into
   the new event type (filter via `WebhookSubscription::subscribesTo`).
4. The Phase-25 watchdog auto-picks the new entry; the test asserts
   every event in `config/webhooks.php` is documented here.
