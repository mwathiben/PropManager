---
name: propmanager-tdd-phpunit
description: PropManager-specific TDD guidance. Use instead of laraveltdd-with-pest. This project is on class-based PHPUnit (not Pest). Apply when writing any new feature, fixing any bug, or before marking a task complete.
---

# TDD on PropManager (PHPUnit)

This project uses **class-based PHPUnit**, not Pest. The `pestphp/pest` package is intentionally NOT installed; tests under `tests/Feature/` and `tests/Unit/` extend `Tests\TestCase` and use snake_case `test_*` methods.

If you're following `laraveltdd-with-pest` from the user-global skill set, **stop and use this skill instead**.

## The loop (non-negotiable)

```
RED    -> write a failing test that captures the intended behaviour
GREEN  -> implement the minimum code to pass
REFACTOR -> tidy while tests stay green
```

You must SEE the test fail in the RED phase before writing implementation.

## File location

| Test type | Where |
|---|---|
| Pure logic, value objects, transformers, calculations | `tests/Unit/...` |
| HTTP endpoints, DB transactions, multi-step workflows | `tests/Feature/...` |
| Browser/JS-heavy flows | `tests/Browser/...` (Dusk) |
| Email flow E2E with Mailpit | `tests/Browser/EmailFlows/` |

Match the surrounding directory layout when adding a test for an existing area.

## Required class shape

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers;

use App\Models\Lease;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;
use Tests\Traits\MocksExternalServices;

class LeaseRenewalControllerTest extends TestCase
{
    use RefreshDatabase;
    use CreatesTestData;
    use MocksExternalServices;

    public function test_landlord_can_renew_an_active_lease(): void
    {
        $this->createTestData();
        $this->mockPaystack();

        $response = $this->actingAs($this->landlord)
            ->post(route('leases.renew', $this->lease), ['months' => 12]);

        $response->assertRedirect();
        $this->assertDatabaseHas('leases', [
            'id' => $this->lease->id,
            'months_remaining' => 12,
        ]);
    }
}
```

## Required project setup

- `declare(strict_types=1);` on every test file
- Use the two project test traits (these solve recurring fixture pain):
  - `CreatesTestData` — sets up `$this->landlord`, `$this->property`, `$this->building`, `$this->unit`, `$this->lease`, `$this->invoice`, `$this->payment` etc. via factories
  - `MocksExternalServices` — `mockPaystack()`, `mockMpesa()` etc. (prevents real API calls)
- `RefreshDatabase` for DB tests (resets between tests)
- `Mail::fake()` for mailables; for HTML body checks call `$mailable->render()` separately (see `memory/MEMORY.md` — `Mail::fake()` doesn't render)

## Parameterised tests use attributes, not doc-comments

```php
use PHPUnit\Framework\Attributes\DataProvider;

#[DataProvider('invalidPhoneNumbers')]
public function test_mpesa_phone_validation_rejects_invalid(string $phone): void
{
    $this->expectException(InvalidPhoneException::class);
    MpesaPhone::from($phone);
}

public static function invalidPhoneNumbers(): array
{
    return [
        'missing country code' => ['0712345678'],
        'wrong country'        => ['+1234567890'],
        'too short'            => ['254712'],
    ];
}
```

`@dataProvider` doc-comment syntax is deprecated and is guarded against by `tests/Feature/TestHygiene/Phase69MetadataHygieneTest.php`.

## Mandatory triple for every endpoint

For every controller method, write all three of:

```php
public function test_authorized_landlord_can_perform_action(): void { ... }
public function test_unauthenticated_user_gets_403(): void { ... }
public function test_cross_tenant_user_gets_403_or_404(): void { ... }
```

The third (cross-tenant) catches `TenantScope` leaks — the highest-risk failure mode in this codebase.

## Assertions cheatsheet

| Concern | Use |
|---|---|
| HTTP response | `$response->assertOk()`, `assertStatus(302)`, `assertRedirect(route(...))` |
| Inertia page | `$response->assertInertia(fn ($page) => $page->component('Portfolio/Home')->has('foo')->where('bar', 'baz')->missing('secret'))` |
| Database state | `$this->assertDatabaseHas('table', [...])`, `assertDatabaseMissing`, `assertDatabaseCount` |
| Events | `Event::fake(); ...; Event::assertDispatched(EventName::class)` |
| Jobs | `Bus::fake(); ...; Bus::assertDispatched(JobName::class)` |
| Mail (no HTML) | `Mail::fake(); ...; Mail::assertQueued(MailableName::class)` |
| Mail (HTML body) | `$mailable->render()` then `$this->assertStringContainsString('text', $html)` |
| Enum status (CRITICAL) | `$this->assertSame(InvoiceStatus::Paid, $invoice->fresh()->status)` — NEVER compare enum to string literal |

## Enum-vs-string footgun

`Invoice::$casts['status'] = InvoiceStatus::class`. `$invoice->status` returns an enum **instance**, not a string. So:

```php
// WRONG — always false (enum instance !== string)
$invoice->status === 'paid'

// RIGHT
$invoice->status === InvoiceStatus::Paid
$this->assertSame(InvoiceStatus::Paid, $invoice->status)
```

See `memory/MEMORY.md` for the full list of locations that were fixed.

## Running tests

```bash
php artisan test                                # full suite (MySQL backed by .env.testing)
php artisan test --parallel                     # 8 workers, ~5x faster
php artisan test --filter=LeaseRenewalController # one class
php artisan test --filter=test_specific_method  # one method
php artisan test tests/Feature/Plans/           # one subdirectory
php artisan test --coverage --min=70            # with coverage (CI enforces 70%)
```

`./vendor/bin/pest` does not exist on this project. Do not invoke it.

## Common pitfalls (logged in MEMORY.md, surfaced again here)

- `Mail::fake()` captures but doesn't render. For HTML assertions use `$mailable->render()` separately.
- Dusk `Browser::visit()` mangles non-http URLs; for `data:` or `file:` use `$browser->driver->navigate()->to($url)` directly.
- agent-browser sessions: always pass `--session e2e` flag, never bare `agent-browser ...`.
- Factory slug collisions: `NotificationTemplate::factory()->create(['landlord_id' => $x])` may conflict on slug — specify explicitly.
- `DatabaseMigrations` runs `migrate:rollback` in teardown and can hit FK errors; use `RefreshDatabase` for most tests.
- Pre-existing migration bug: `2026_01_15_000001_add_finance_hub_indexes.php::down()` fails on FK constraint — don't try to roll back through it.

## Before marking the test task complete

- [ ] RED was visible (`php artisan test --filter=YourTest` failed first)
- [ ] GREEN is now in (same command passes)
- [ ] No new PHPMD violations on touched files (`./vendor/bin/phpmd app text phpmd.xml`)
- [ ] `php vendor/bin/pint` clean
- [ ] Authorization triple (authorized / unauthenticated / cross-tenant) exists for every new endpoint
- [ ] Enum comparisons use the enum class, never a string literal
