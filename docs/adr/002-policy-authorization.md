# ADR-002: Use Policy Classes for Authorization

## Status

Accepted (DBP-010)

## Context

Authorization was handled inconsistently across the codebase:

1. **Manual checks in controllers**: `if ($user->id !== $resource->landlord_id)`
2. **Duplicated logic**: Same ownership checks repeated in multiple places
3. **No clear authorization layer**: Hard to audit who can do what
4. **Mixed concerns**: Controllers handled both authz and business logic

## Decision

All authorization MUST use Laravel Policy classes:

```php
// Before (anti-pattern)
public function update(Request $request, Invoice $invoice)
{
    if ($invoice->landlord_id !== auth()->user()->landlord_id) {
        abort(403);
    }
}

// After (required pattern)
public function update(UpdateInvoiceRequest $request, Invoice $invoice)
{
    $this->authorize('update', $invoice);
    // or automatically via FormRequest authorize() method
}
```

Policies are organized in `app/Policies/`:
- One Policy per Model that needs authorization
- Methods map to controller actions: `viewAny`, `view`, `create`, `update`, `delete`
- Complex authorization uses `before()` method for super admin bypass

## Consequences

### Positive

- **Auditable**: All authorization rules in one place per model
- **Testable**: Policy methods can be unit tested
- **Consistent**: Same authorization logic across web, API, and CLI
- **Blade integration**: `@can('update', $invoice)` in views
- **Automatic 403**: Proper HTTP responses without manual abort()

### Negative

- More boilerplate for simple ownership checks
- Need to register policies in AuthServiceProvider

### Neutral

- TenantScope trait handles multi-tenancy at query level
- Policies handle action-level authorization
- Both work together for complete access control

## References

- DBP-010: Create Missing Authorization Policies
- `laravelpolicies-and-authorization` skill
