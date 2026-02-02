# ADR-006: Payment Idempotency Pattern

## Status

Accepted (PAY-V2-001)

## Context

PropManager receives payment webhooks from multiple providers (M-Pesa STK Push, M-Pesa C2B, M-Pesa Till, Paystack, IntaSend). These webhooks may be:

1. **Delivered multiple times** due to network retries
2. **Received concurrently** due to provider retry mechanisms
3. **Replayed** during provider debugging or reconciliation

Without proper idempotency, duplicate webhooks can:
- Create duplicate payment records
- Over-credit tenant wallets
- Send duplicate receipt emails
- Cause financial reconciliation issues

### Previous Approach

The original implementation used pessimistic locking:

```php
$existingPayment = Payment::where('mpesa_transaction_id', $receiptNumber)
    ->lockForUpdate()
    ->first();

if ($existingPayment) {
    return; // Already processed
}
```

**Problem**: This approach has a race condition window. Two concurrent requests can both pass the check if they read before either inserts.

## Decision

Implement database-level idempotency using:

1. **UNIQUE constraint** on `mpesa_transaction_id` column
2. **Exception handling** for MySQL error 1062 (duplicate entry)

### Migration

```php
// Add unique constraint (prevents duplicates at database level)
Schema::table('payments', function (Blueprint $table) {
    $table->unique('mpesa_transaction_id', 'payments_mpesa_transaction_id_unique');
});
```

### Controller Pattern

```php
protected function processPayment(array $data): void
{
    $receiptNumber = $data['mpesa_receipt_number'];

    try {
        DB::beginTransaction();

        // Pessimistic lock still useful for read-modify-write operations
        $existingPayment = Payment::where('mpesa_transaction_id', $receiptNumber)
            ->lockForUpdate()
            ->first();

        if ($existingPayment) {
            DB::rollBack();
            return;
        }

        // Insert payment - UNIQUE constraint is the final safety net
        $payment = $invoice->payments()->create([
            'mpesa_transaction_id' => $receiptNumber,
            // ... other fields
        ]);

        // ... process payment (fees, receipts, notifications)

        DB::commit();

    } catch (\Illuminate\Database\QueryException $e) {
        DB::rollBack();

        // MySQL error 1062 = duplicate entry (unique constraint violation)
        if ($e->errorInfo[1] === 1062) {
            Log::info('Duplicate webhook ignored (idempotent)', [
                'mpesa_transaction_id' => $receiptNumber,
            ]);
            return; // Idempotent - not an error
        }

        throw $e; // Re-throw other database errors
    }
}
```

### Key Points

1. **Database constraint is authoritative**: Even if application logic fails, the database prevents duplicates
2. **Return success for duplicates**: Webhooks should return 200 OK to prevent provider retries
3. **Log duplicates as INFO**: Expected behavior, not an error
4. **Keep pessimistic lock**: Useful for read-modify-write operations on related records

## Consequences

### Positive

- **Race condition eliminated**: Database enforces uniqueness atomically
- **Simpler reasoning**: Don't need to think about timing windows
- **Provider-agnostic**: Same pattern works for M-Pesa, Paystack, IntaSend
- **Audit trail**: Duplicate attempts are logged for debugging
- **No data corruption**: Impossible to create duplicate payments

### Negative

- **Migration requires clean data**: Must resolve existing duplicates before adding constraint
- **Exception handling overhead**: Slight performance cost for exception path
- **NULL handling**: MySQL UNIQUE allows multiple NULLs (by design for non-M-Pesa payments)

### Neutral

- Existing pessimistic locks remain (defense in depth)
- No changes to API response format
- No changes to frontend behavior

## Implementation

### Files Modified

| File | Change |
|------|--------|
| `database/migrations/2026_02_02_000001_add_unique_constraint_mpesa_transaction_id.php` | Add UNIQUE constraint |
| `app/Http/Controllers/Api/MpesaWebhookController.php` | Add QueryException handling |

### Test Coverage

| Test | Purpose |
|------|---------|
| `test_duplicate_mpesa_transaction_id_throws_query_exception` | Verify database constraint |
| `test_c2b_duplicate_webhook_returns_200_without_creating_duplicate_payment` | Verify idempotent response |
| `test_50_concurrent_webhooks_create_exactly_one_payment` | Verify race condition handling |
| `test_duplicate_webhook_does_not_modify_original_payment` | Verify data integrity |
| `test_multiple_payments_with_null_mpesa_transaction_id_allowed` | Verify NULL handling |

## References

- PAY-V2-001: Add Unique Constraint on mpesa_transaction_id
- `laraveltransactions-and-consistency` skill
- ADR-003: Wrap Multi-Write Operations in Transactions
- ADR-004: Payment Gateway Interface (for Paystack idempotency pattern)
