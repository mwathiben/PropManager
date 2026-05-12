# Money + Time Conventions (Phase-17)

This runbook captures the conventions for handling money + time in the PropManager codebase after the Phase-17 audit. Read it before introducing new financial arithmetic or date handling code.

## Money

### Use `App\ValueObjects\Money` for arithmetic — never `(float)`

```php
// ❌ Don't do this — float arithmetic drifts under cumulative operations
$total = (float) $invoice->rent_due + (float) $invoice->water_due;

// ✅ Do this — bcmath-backed exact decimal arithmetic
use App\ValueObjects\Money;
$total = Money::fromString((string) $invoice->rent_due)
    ->add(Money::fromString((string) $invoice->water_due));
$invoice->update(['total_due' => $total->toDecimalString()]);
```

The DB uses `DECIMAL(10,2)` everywhere. The Eloquent `'decimal:2'` cast already returns a string from attribute access. The bug surface is anywhere the service layer casts that string to float for arithmetic — that's where drift accumulates over compounded late fees / multi-step allocations.

### Rounding mode is banker's (half-even)

`Money::multiply` and `Money::divide` use banker's rounding internally — `.005` rounds to the nearest **even** cent, not up. This is the financial standard for cumulative compounding (avoids the half-up bias of `round($x, 2)`).

### Paystack / M-Pesa minor-unit conversion

```php
// ❌ Lossy float boundary at the API edge
$cents = Currency::KES->toMinorUnits((float) $payment->amount);

// ✅ Money round-trips precisely
$cents = Currency::KES->toMinorUnitsFromMoney(Money::fromString((string) $payment->amount));
```

`toMinorUnits(float)` remains for backwards compat but should not appear in new code.

### Bank webhook amounts are validated at ingress

`App\Services\Banking\WebhookAmountParser::parse($raw)` rejects:
- non-numeric strings ('twelve thousand')
- scientific notation ('1e3')
- empty/null

All three KCB / Equity / Co-op `parsePaymentNotification` methods route through it. A malformed payload lands in the WebhookDeadLetter (Phase-16 RESIL-8 exponential retry) for operator review rather than being silently zeroed or truncated.

### `Currency` is KES-only via validator

The `Currency` enum declares USD / EUR / GBP cases for future cross-border support but the building / settings form-request validators are pinned to `Rule::in(['KES'])`. Without FX/exchange-rate handling (Phase-18+ candidate) the dashboard would mis-sum across currencies.

### Drift audit

`php artisan payments:audit-allocations` (scheduled nightly at 05:30 Africa/Nairobi) compares `invoice.amount_paid` against `SUM(payments.amount WHERE invoice_id=X)` and logs any > 0.01 KES drift to the schedule channel. The `invoice_amount_paid_drift_count` Prometheus gauge surfaces the count for Grafana alerting.

## Time

### Per-user timezone is honoured for inbound filters

`App\Support\DateFilter::parseUserDay($input, $user, $boundary)` is the canonical way to parse a user-supplied date filter. The user's `timezone` column (default `Africa/Nairobi`) anchors the day boundary so a user in `America/New_York` filtering `'2026-01-15'` gets NY-midnight, not Nairobi-midnight (off-by-day pre-Phase-17).

```php
use App\Support\DateFilter;

$start = DateFilter::parseUserDay($request->date_from, $request->user(), 'startOfDay');
$end = DateFilter::parseUserDay($request->date_to, $request->user(), 'endOfDay');
```

`App\Support\TenantClock::nowFor($user)` returns a CarbonImmutable in the user's timezone. Use this any time you compute "today" or "this week" relative to the user's day boundary.

### Scheduled tasks are explicitly pinned to Africa/Nairobi

`routes/console.php` schedules every command + closure with `->timezone('Africa/Nairobi')` regardless of `APP_TIMEZONE`. Don't add new schedule entries without it — a future operator flipping `APP_TIMEZONE` to UTC would otherwise shift every cron by 3 hours.

### Webhook timestamps carry an explicit TZ

M-Pesa daraja emits `TransactionDate` in `YmdHis` format as Africa/Nairobi wall-clock per Safaricom docs. The webhook validator uses `Carbon::createFromFormat('YmdHis', $timestamp, 'Africa/Nairobi')` — never let `createFromFormat` fall back to `APP_TIMEZONE`.

### Month-overflow for late-fee compounding

`Carbon::addMonth()` overflows: Jan 31 + 1 month = Mar 3, not Feb 28. For LateFeeService monthly compounding we use `addMonthNoOverflow()` — Jan 31 → Feb 28/29 — to keep the cadence anchored to day-of-month.

### Emitted dates carry explicit offset

Laravel-12 Carbon JSON serialization emits ISO 8601 with an explicit timezone marker (`'2026-05-12T00:05:00.000000Z'` for UTC). When emitting your own date strings in API responses, prefer `->toIso8601String()` or `->format(DateTimeInterface::ATOM)` — never `->toDateTimeString()` (no TZ marker — old Safari treats it as UTC, Chrome as local).

### MySQL DATETIME columns are TZ-naive — operator constraint

PropManager uses `DATETIME` columns everywhere. MySQL DATETIME stores wall-clock with no offset; the application has to control the surrounding timezone. **Production-deploy invariant**: the MySQL session timezone must be `+03:00` (or `Africa/Nairobi`). The Phase-11 `scripts/deploy.sh` stamps this on connection. If a deploy ever lands in a non-Kenya region without this setting, every datetime write would silently shift.

Phase-18 candidate: migrate to `TIMESTAMP` columns (TZ-aware, stored in UTC, converted on read). Out of scope for Phase 17.

### House style: `now()`, not `Carbon::now()`

Both return the same value; the codebase uses both inconsistently. New code should use `now()` (the Laravel helper) for grep-ability. Not enforced via Pint — documented preference.

## See also

- `docs/runbooks/circuit-breaker.md` — Phase-16 RESIL-1
- `docs/runbooks/queue-triage.md` — Phase-16 QUEUE-5
- `docs/runbooks/queue-worker-config.md` — Phase-16 QUEUE-8/10
