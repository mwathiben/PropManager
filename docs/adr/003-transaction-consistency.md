# ADR-003: Wrap Multi-Write Operations in Transactions

## Status

Accepted (DBP-025, DBP-026)

## Context

PropManager has many operations that modify multiple database records:

1. **Invoice generation**: Creates invoice + items + marks readings as invoiced
2. **Payment processing**: Updates invoice status + creates payment + updates wallet
3. **Move-out settlement**: Creates refund + updates deposit + closes lease

Without transactions, partial failures leave the database in inconsistent states.

## Decision

All multi-write operations MUST be wrapped in `DB::transaction()`:

```php
// Required pattern
DB::transaction(function () use ($lease, $invoiceData) {
    $invoice = Invoice::create($invoiceData);

    foreach ($items as $item) {
        InvoiceItem::create([...]);
    }

    WaterReading::where('unit_id', $lease->unit_id)
        ->where('is_invoiced', false)
        ->update(['is_invoiced' => true]);

    // Email queued AFTER transaction commits (see afterCommit)
    Mail::to($tenant)->queue(new InvoiceSent($invoice));
});
```

### Queued Jobs Inside Transactions

Jobs dispatched inside transactions MUST use `$afterCommit`:

```php
// In Mailable class
class PaymentReceived extends Mailable implements ShouldQueue
{
    public $afterCommit = true;  // REQUIRED
}

// Or use dispatchAfterCommit()
DB::transaction(function () {
    // ... database operations ...
    SendNotificationJob::dispatchAfterCommit($notification);
});
```

## Consequences

### Positive

- **Atomicity**: All-or-nothing writes prevent partial failures
- **Consistency**: Database always in valid state
- **No orphaned records**: Failed invoice generation doesn't leave partial items
- **Safe rollback**: Exceptions automatically rollback all changes

### Negative

- Longer lock times for complex operations
- Need to handle deadlocks in high-concurrency scenarios
- Slightly more complex code structure

### Neutral

- Use `lockForUpdate()` for pessimistic locking when needed
- Queue jobs use database driver (inherits transaction visibility)
- Laravel handles nested transactions via savepoints

## References

- DBP-025: Audit and Wrap Multi-Write Operations in Transactions
- DBP-026: Add $afterCommit to Queued Jobs and Events
- `laraveltransactions-and-consistency` skill
