---
name: propmanager-queue-database
description: PropManager-specific queue guidance. Use instead of laravelqueues-and-horizon / laravelhorizonmetrics-and-dashboards. This project uses Laravel's database queue driver (NO Horizon, NO Redis). Apply when dispatching any job, writing a job class, configuring a Mailable, or debugging async work.
---

# Queue on PropManager (database driver, no Horizon)

This project runs queues on Laravel's `database` driver. There is no Redis, no Horizon, no Horizon dashboard. The user-global `laravelqueues-and-horizon` and `laravelhorizonmetrics-and-dashboards` skills assume Horizon as the default — they are misleading here.

If you're following either of those user-global skills, **stop and use this skill instead**.

## What's actually configured

- `QUEUE_CONNECTION=database` in `.env` (see `config/queue.php`)
- `SESSION_DRIVER=database` (queue and session both ride MySQL)
- Jobs table created by `php artisan queue:table` + migrate (already in place)
- Failed jobs table created by `php artisan queue:failed-table` + migrate (already in place)
- Worker is `php artisan queue:listen --tries=1` in dev (see `composer.json` `dev` script)
- In production, the deploy hosts a `php artisan queue:work --tries=3 --max-time=3600 --memory=512` supervisor entry (NOT documented here yet — defer to ops)

## When to dispatch a job vs run inline

Dispatch a job when the work is:
- I/O bound (email send, SMS, external API call to Paystack / IntaSend / M-Pesa)
- Slow (PDF generation, ZIP creation, OCR)
- Best-effort (analytics events, audit fan-out)
- Time-shifted (delayed reminders, scheduled rent invoices)

Do NOT dispatch a job for work the user is waiting on synchronously. The dev queue listener is single-process and can lag.

## Dispatch patterns (project conventions)

### Standard dispatch

```php
SendInvoiceEmail::dispatch($invoice);
```

### Dispatch after the current DB transaction commits (CRITICAL)

```php
DB::transaction(function () use ($invoice) {
    $invoice->update(['status' => InvoiceStatus::Sent]);
    SendInvoiceEmail::dispatch($invoice)->afterCommit();
});
```

OR — equivalent project pattern, used for Mailables — set the property on the Mailable class itself:

```php
class InvoiceSent extends Mailable implements ShouldQueue
{
    public bool $afterCommit = true;

    // ...
}
```

Every project Mailable that `implements ShouldQueue` has `public bool $afterCommit = true;` per project convention (search the codebase to confirm; if missing, ADD it).

**Why it matters:** without `afterCommit`, if the transaction rolls back, the queued job still fires and the user gets an email about an invoice that no longer exists. See `tests/Feature/TransactionRollbackTest.php` for the regression coverage that enforces this.

### Bulk dispatch

```php
$tenants->chunk(100)->each(function ($chunk) {
    Bus::batch(
        $chunk->map(fn ($tenant) => new SendRentReminder($tenant))->all()
    )->dispatch();
});
```

For 1000+ records use `Bus::batch` (database-driven; no Horizon dependency).

### Delayed dispatch

```php
SendRentReminder::dispatch($lease)->delay(now()->addDays(7));
```

The database driver respects `available_at` correctly.

## Job class shape

```php
<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;  // optional, see below
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class SendInvoiceEmail implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;
    public int $backoff = 30;   // seconds; or array for tiered backoff: [30, 60, 120]
    public int $timeout = 60;

    public function __construct(public readonly Invoice $invoice) {}

    public function handle(): void
    {
        // do the work — keep it idempotent
    }

    public function failed(\Throwable $e): void
    {
        Log::error('SendInvoiceEmail failed', [
            'invoice_id' => $this->invoice->id,
            'error' => $e->getMessage(),
            // NEVER log $e->getTrace() with raw context — PII / secret risk
        ]);
    }
}
```

## Idempotency is non-negotiable

The database driver retries on failure. Your `handle()` must produce the same final state if called twice:

```php
public function handle(): void
{
    if ($this->invoice->fresh()->status === InvoiceStatus::Sent) {
        return;  // already done in a prior attempt
    }

    // ... do the work ...
}
```

For payments specifically (M-Pesa / Paystack / IntaSend webhook handlers), use pessimistic locking + idempotency key check. See `memory/MEMORY.md` "Idempotency with pessimistic locking" section.

## Failure handling

- The `failed_jobs` table catches anything that exhausts `$tries`. Inspect with `php artisan queue:failed`.
- Retry one with `php artisan queue:retry {uuid}`.
- Retry all with `php artisan queue:retry all`.
- Forget with `php artisan queue:forget {uuid}` or `queue:flush` for all.
- The `failed()` method on the job runs once per terminal failure — use it for alerting (Sentry, structured log).

## What you DON'T do (because no Horizon)

- ❌ `php artisan horizon` — package not installed
- ❌ `php artisan horizon:status` — same
- ❌ `Horizon::auth(...)` in a service provider — same
- ❌ Tag jobs for Horizon dashboard grouping (`->tags(...)`) — has no consumer here
- ❌ Configure supervisor in `config/horizon.php` — file doesn't exist

If a task genuinely needs Horizon (real-time queue dashboard, configured concurrency per queue, metrics export to Prometheus), surface that as a question — installing Horizon means installing Redis too, which is a real infrastructure change.

## Testing jobs

```php
public function test_invoice_sent_queues_the_email(): void
{
    Mail::fake();
    Queue::fake();

    $this->createTestData();

    $this->actingAs($this->landlord)
        ->post(route('invoices.send', $this->invoice));

    Mail::assertQueued(InvoiceSent::class);
    // OR for direct dispatch:
    // Queue::assertPushed(SendInvoiceEmail::class);
}
```

For testing `afterCommit`:

```php
public function test_invoice_email_is_NOT_queued_if_transaction_rolls_back(): void
{
    Mail::fake();
    Queue::fake();

    $this->createTestData();

    try {
        DB::transaction(function () {
            $this->invoice->update(['status' => InvoiceStatus::Sent]);
            InvoiceSent::dispatch($this->invoice)->afterCommit();
            throw new \RuntimeException('simulated rollback');
        });
    } catch (\RuntimeException) {
        // expected
    }

    Mail::assertNothingQueued();
}
```

`tests/Feature/TransactionRollbackTest.php` already covers this for the core Mailables.

## Local dev: see what's pending

```bash
# Count of pending jobs
php artisan tinker --execute="echo DB::table('jobs')->count();"

# Count of failed jobs
php artisan queue:failed | wc -l

# Tail the queue listener output
composer dev   # or:
php artisan queue:listen --tries=1 -vvv
```

If a job appears to "hang," check the dev queue listener is actually running. The database driver does NOT auto-process — a worker must be listening.

## Production-readiness checklist (when this codebase deploys)

- [ ] `queue:work` supervisor entry running with restart-on-fail
- [ ] `failed_jobs` table monitored — non-zero count = alert
- [ ] Per-Mailable `$afterCommit = true` audited (search: `class.*extends Mailable implements ShouldQueue`)
- [ ] `Log::error()` in every `failed()` method routes to Sentry
- [ ] No job carries unencrypted PII in `serialize()` payload (check `__sleep()` or use route-model-binding to id only)
- [ ] Dead letter triage runbook exists (currently informal — flag for ops)
