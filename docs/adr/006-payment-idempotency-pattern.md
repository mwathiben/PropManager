# ADR-006: Payment Idempotency Pattern

## Status

Accepted (PAY-V2-001, PAY-V2-002, PAY-V2-003)

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

1. **UNIQUE constraint** on transaction identifier columns:
   - `mpesa_transaction_id` for M-Pesa payments (PAY-V2-001)
   - `intasend_reference` for IntaSend payments (PAY-V2-002)
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
4. **Pessimistic lock is optional**: Only required when performing read-modify-write operations on related records (e.g., updating invoice balance or wallet credit); for simple insert-only flows, the UNIQUE constraint alone is sufficient

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

#### PAY-V2-001: M-Pesa Idempotency

| File | Change |
|------|--------|
| `database/migrations/2026_02_02_000001_add_unique_constraint_mpesa_transaction_id.php` | Add UNIQUE constraint on `mpesa_transaction_id` |
| `app/Http/Controllers/Api/MpesaWebhookController.php` | Add QueryException(1062) handling |

#### PAY-V2-002: IntaSend Idempotency

| File | Change |
|------|--------|
| `database/migrations/2026_02_04_000001_add_unique_constraint_intasend_reference.php` | Add UNIQUE constraint on `intasend_reference` |
| `app/Http/Controllers/Api/IntaSendWebhookController.php` | Add QueryException(1062) handling |
| `app/Models/PaymentConfiguration.php` | Encrypt `intasend_webhook_challenge` |

### Test Coverage

#### M-Pesa Tests (`tests/Feature/MpesaIdempotencyTest.php`)

| Test | Purpose |
|------|---------|
| `test_duplicate_mpesa_transaction_id_throws_query_exception` | Verify database constraint |
| `test_c2b_duplicate_webhook_returns_200_without_creating_duplicate_payment` | Verify idempotent response |
| `test_50_concurrent_webhooks_create_exactly_one_payment` | Verify race condition handling |
| `test_duplicate_webhook_does_not_modify_original_payment` | Verify data integrity |
| `test_multiple_payments_with_null_mpesa_transaction_id_allowed` | Verify NULL handling |

#### IntaSend Tests (`tests/Feature/IntaSendIdempotencyTest.php`)

| Test | Purpose |
|------|---------|
| `test_duplicate_intasend_reference_throws_query_exception` | Verify database constraint |
| `test_duplicate_intasend_webhook_returns_200_without_creating_duplicate_payment` | Verify idempotent response |
| `test_50_concurrent_intasend_webhooks_create_exactly_one_payment` | Verify race condition handling |
| `test_process_complete_payment_handles_duplicate_reference` | Verify controller handles QueryException |
| `test_multiple_payments_with_null_intasend_reference_allowed` | Verify NULL handling |
| `test_duplicate_intasend_webhook_does_not_modify_original_payment` | Verify data integrity |

---

## PAY-V2-003: Application-Level Idempotency Service

### Context

While the UNIQUE constraints (PAY-V2-001, PAY-V2-002) prevent duplicate payments at the database level, they only catch duplicates at the INSERT moment. This means:

1. Duplicate requests may execute expensive processing before hitting the constraint
2. No response caching - each duplicate incurs full processing overhead
3. Race condition handling relies on exception catching

### Decision

Add an application-level `IdempotencyService` that provides:

1. **Early detection** - Check BEFORE processing begins
2. **Response caching** - Return cached response for duplicates
3. **TTL-based cleanup** - Auto-expire keys after 24 hours

### Two-Layer Idempotency Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                    Application Layer                        │
│  ┌─────────────────────────────────────────────────────┐   │
│  │           IdempotencyService.acquire()               │   │
│  │  - Early detection (BEFORE processing starts)       │   │
│  │  - Response caching (return cached result)          │   │
│  │  - TTL expiration (24 hours)                        │   │
│  └─────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────┘
                           │
                           ▼
┌─────────────────────────────────────────────────────────────┐
│                    Database Layer                           │
│  ┌─────────────────────────────────────────────────────┐   │
│  │         UNIQUE Constraints (Safety Net)              │   │
│  │  - mpesa_transaction_id                              │   │
│  │  - intasend_reference                                │   │
│  │  - paystack_reference                                │   │
│  │  - Catches race conditions that slip through         │   │
│  └─────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────┘
```

### IdempotencyService API

```php
class IdempotencyService
{
    // Attempt to acquire lock for processing
    public function acquire(string $key, ?string $requestHash = null): array
    {
        // Returns: ['acquired' => true] or ['acquired' => false, 'response' => cached_data]
    }

    // Release lock and store response
    public function release(string $key, array $response): void;

    // Mark as failed with reason
    public function fail(string $key, ?string $reason = null): void;

    // Check if key is being processed
    public function isProcessing(string $key): bool;

    // Remove expired keys
    public function cleanupExpired(): int;
}
```

### Usage Pattern (Webhook Controllers)

```php
public function processWebhook(Request $request): Response
{
    $receiptNumber = $request->input('mpesa_receipt');
    $idempotencyKey = IdempotencyService::generateKey('mpesa', $receiptNumber);

    $result = $this->idempotencyService->acquire($idempotencyKey);

    if (!$result['acquired']) {
        if ($result['response']) {
            // Return cached response
            return response()->json($result['response']);
        }
        // Another request is processing - return 202 Accepted
        return response('Processing', 202);
    }

    try {
        // Process payment...
        $response = $this->processPayment($request);

        $this->idempotencyService->release($idempotencyKey, [
            'status' => 'success',
            'payment_id' => $response->payment_id,
        ]);

        return response()->json($response);

    } catch (\Exception $e) {
        $this->idempotencyService->fail($idempotencyKey, $e->getMessage());
        throw $e;
    }
}
```

### Files Created (PAY-V2-003)

| File | Purpose |
|------|---------|
| `database/migrations/2026_02_04_100000_create_idempotency_keys_table.php` | Create idempotency_keys table |
| `app/Models/IdempotencyKey.php` | Model with scopes (active, expired, pending, completed) |
| `app/Services/IdempotencyService.php` | Acquire/release/fail/cleanup service |
| `app/Console/Commands/CleanupExpiredIdempotencyKeys.php` | Scheduled cleanup command |
| `database/factories/IdempotencyKeyFactory.php` | Test factory |

### Test Coverage (PAY-V2-003)

| Test File | Tests |
|-----------|-------|
| `tests/Unit/Services/IdempotencyServiceTest.php` | 17 unit tests for service methods |
| `tests/Feature/IdempotencyIntegrationTest.php` | 10 integration tests for workflow |

### Schedule

Cleanup command runs daily at 03:00 via `routes/console.php`:

```php
Schedule::command('idempotency:cleanup')
    ->dailyAt('03:00')
    ->withoutOverlapping()
    ->runInBackground();
```

## References

- PAY-V2-001: Add Unique Constraint on mpesa_transaction_id
- PAY-V2-002: Add Unique Constraint on intasend_reference
- PAY-V2-003: Create Idempotency Key Table for Cross-Request Synchronization
- Laravel Transactions and Consistency (database best practices)
- ADR-003: Wrap Multi-Write Operations in Transactions
- ADR-004: Payment Gateway Interface (for Paystack idempotency pattern)
