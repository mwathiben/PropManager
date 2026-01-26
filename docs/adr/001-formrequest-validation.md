# ADR-001: Use FormRequest Classes for Validation

## Status

Accepted (DBP-012)

## Context

The PropManager codebase had 50+ inline `$request->validate()` calls scattered across controllers. This led to:

1. **Duplicated validation rules** - Same rules repeated in multiple controllers
2. **Untestable validation** - Inline validation can't be unit tested
3. **Bloated controllers** - Controllers mixed HTTP handling with validation logic
4. **Inconsistent error messages** - Custom messages defined differently in each location

## Decision

All validation MUST use Laravel FormRequest classes:

```php
// Before (anti-pattern)
public function store(Request $request)
{
    $request->validate([
        'email' => 'required|email',
        'amount' => 'required|numeric|min:0',
    ]);
}

// After (required pattern)
public function store(StorePaymentRequest $request)
{
    // Validation already done by FormRequest
}
```

FormRequest classes are organized by domain in `app/Http/Requests/`:
- `Finance/` - Financial operations
- `Payment/` - Payment processing
- `Tenant/` - Tenant management
- `Building/` - Property operations
- etc.

## Consequences

### Positive

- **Single source of truth** for validation rules
- **Testable** - FormRequest classes can be unit tested
- **Reusable** - Same request class used across multiple endpoints
- **Cleaner controllers** - Controllers focus on orchestration, not validation
- **Consistent error messages** - Defined once in FormRequest

### Negative

- More files to maintain (74 FormRequest classes created)
- Need to remember to create FormRequest for new endpoints

### Neutral

- Authorization logic can also be centralized in `authorize()` method
- `prepareForValidation()` hook useful for data normalization

## References

- DBP-012: Extract Validation Rules to FormRequest Classes
- `laravelform-requests` skill
