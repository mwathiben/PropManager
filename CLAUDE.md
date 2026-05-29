# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

PropManager is a multi-tenant property management system for landlords to manage properties, buildings, units, tenants, leases, water readings, and invoices. Built with Laravel 12, Inertia.js, and Vue 3.

## ⛔ SKILL GATE (MANDATORY - BLOCKING)

**This gate MUST be completed before ANY implementation (edits, writes, code changes).**

### Step 1: Parse Task
Identify from the user's request:
- **Domain**: frontend | backend | database | auth | API | deployment | testing
- **Type**: new feature | refactor | bug fix | API endpoint | optimization
- **Tech**: Laravel | Vue | Eloquent | queues | migrations | etc.

### Step 2: Match Skills
Using the task context, find ALL matching skills from the table below.

| Task Domain/Type | MUST Check These Skills |
|------------------|-------------------------|
| Laravel controller work | `laravelcontroller-cleanup` |
| Laravel any | `laravelquality-checks` |
| Database/migrations | `laravelmigrations-and-factories` |
| Eloquent queries | `laraveleloquent-relationships`, `laravelperformance-eager-loading` |
| API endpoints | `laravelapi-resources-and-pagination` |
| Form validation | `laravelform-requests` |
| Testing | `propmanager-tdd-phpunit`, `verification-first` (**NOT** `laraveltdd-with-pest` — this project is PHPUnit, not Pest) |
| Feature implementation | `feature-development`, `verification-first` |
| PRD/autonomous | `ralph-wiggum` |
| Frontend Vue | `web-design-guidelines` |
| Refactoring | `laravelcontroller-cleanup`, `laravelinterfaces-and-di` |
| Performance | `laravelperformance-caching`, `laravelperformance-select-columns` |
| Auth/policies | `laravelpolicies-and-authorization` |
| Multi-write operations | `laraveltransactions-and-consistency` |
| External HTTP calls | `laravelhttp-client-resilience` |
| Rate limiting | `laravelrate-limiting` |
| File uploads | `laravelfilesystem-uploads` |
| Bulk data processing | `laraveldata-chunking-large-datasets` |
| Exception handling | `laravelexception-handling-and-logging` |
| Queue jobs | `propmanager-queue-database` (**NOT** `laravelqueues-and-horizon` — this project uses the `database` queue driver, no Horizon, no Redis) |
| Running any shell command | `propmanager-runner-laragon` (**NOT** `laravelbootstrap-check` / `laravelrunner-selection` / `laraveldaily-workflow` — this project is on Laragon/Windows, no Sail) |
| Complexity refactoring | `laravelcomplexity-guardrails` |

### Step 3: Read & Cite (REQUIRED OUTPUT)

For each matched custom skill, read the file: `~/.claude/skills/[name]/SKILL.md`

**You MUST output this block before implementation:**

```
## Skills Applied
- **[skill-name]**: [1-2 sentence summary of guidance being applied]
- **[skill-name]**: [1-2 sentence summary of guidance being applied]
```

If no custom skills match (rare), output:
```
## Skills Applied
- None matched (task domain: [domain], type: [type])
```

### Step 4: Proceed
Only after completing Steps 1-3 and outputting "Skills Applied" may you begin implementation.

---

### Mid-Task Skill Check

When a blocking issue is discovered during implementation:

1. **STOP** - Do not immediately start fixing
2. **Re-run Steps 1-3** for the blocking issue specifically
3. **Output a new "Skills Applied" block** for the issue
4. **Then fix** using the skill guidance

---

### Violations (BLOCKING)

| Violation | Consequence |
|-----------|-------------|
| Starting implementation without "Skills Applied" output | Invalid work - redo |
| Skipping skill read for matched domain | Invalid work - redo |
| Not re-running gate for mid-task blocking issues | Invalid fix - redo |

---

### Full Skill Reference

<details>
<summary><b>Custom Skills (~/.claude/skills/)</b></summary>

**Laravel (52 skills)** - All prefixed `laravel*`
- Controllers: `laravelcontroller-cleanup`, `laravelform-requests`, `laravelcontroller-tests`
- Eloquent: `laraveleloquent-relationships`, `laravelperformance-eager-loading`, `laraveldata-chunking-large-datasets`
- Testing: `laraveltdd-with-pest`, `laravele2e-playwright`, `laravelquality-checks`
- API: `laravelapi-resources-and-pagination`, `laravelapi-surface-evolution`, `laravelrate-limiting`
- Architecture: `laravelports-and-adapters`, `laravelstrategy-pattern`, `laravelinterfaces-and-di`
- Database: `laravelmigrations-and-factories`, `laraveltransactions-and-consistency`
- Performance: `laravelperformance-caching`, `laravelperformance-select-columns`
- Auth: `laravelpolicies-and-authorization`
- Queues: `laravelqueues-and-horizon`, `laraveltask-scheduling`
- Files: `laravelfilesystem-uploads`, `laravelconfig-env-storage`

**Workflow**
- `verification-first` - Verify changes before claiming success
- `feature-development` - End-to-end feature implementation
- `ralph-wiggum` - Autonomous PRD task execution
- `planning-with-files` - Persistent markdown planning

**Frontend**
- `web-design-guidelines` - UI/UX/accessibility (100+ rules)
- `vercel-react-best-practices` - React/Next.js patterns

</details>

<details>
<summary><b>Built-in Claude Code Skills</b></summary>

- `code-review` / `code-reviewer` - Code review
- `api-design-principles` - REST/GraphQL design
- `e2e-testing-patterns` - Playwright/Cypress
- `frontend-design` - Production frontend
- `tech-docs-writer` - Documentation
- `auth-implementation-patterns` - JWT, OAuth2, sessions

</details>

---

## Pre-Implementation Checklist

Before writing ANY code, verify these patterns are followed. These are the standards established in `design-best-practices-prd.json` (DBP series):

| Pattern | Required Approach | Anti-Pattern to Avoid |
|---------|-------------------|----------------------|
| **Validation** | FormRequest class in `app/Http/Requests/` | Inline `$request->validate()` in controller |
| **Authorization** | Policy class in `app/Policies/` | Manual `if ($user->id !== ...)` checks |
| **Models** | Factory in `database/factories/` | Creating without factory for tests |
| **Controllers** | ≤300 lines, single responsibility | God controllers with mixed concerns |
| **External APIs** | `Http::timeout(30)->retry(3, 100)` | No timeout, no retry logic |
| **Multi-write ops** | `DB::transaction()` wrapper | Multiple saves without transaction |
| **Queued jobs** | `$afterCommit = true` if inside transaction | Queue dispatch without afterCommit |
| **File operations** | `Storage` facade | `file_get_contents`, `file_put_contents` |
| **Vue formatting** | `useFormatters` composable | Inline `toLocaleString`, `toLocaleDateString` |
| **Logging** | Structured context arrays, secrets redacted | Concatenated strings, raw API responses |
| **Tests** | Write failing test FIRST (RED-GREEN-REFACTOR) | Code first, tests after |

**Violation of these patterns requires refactoring before the work is considered complete.**

## Tech Stack

- **Backend**: Laravel 12 (PHP 8.4+)
- **Frontend**: Inertia.js with Vue 3, Tailwind CSS v4
- **Build Tool**: Vite 7
- **Database**: MySQL 9.4 (primary), SQLite supported for testing
- **Testing**: PHPUnit
- **Code Style**: Laravel Pint

## Development Commands

### Initial Setup
```bash
composer setup  # Installs dependencies, generates key, runs migrations, builds assets
```

### Development Server
```bash
composer dev  # Runs concurrent: Laravel server, queue worker, pail logs, and Vite
# Or manually:
php artisan serve
php artisan queue:listen --tries=1
php artisan pail --timeout=0
npm run dev
```

### Testing
```bash
composer test           # Run full test suite
php artisan test        # Same as above
php artisan test --filter=TestName  # Run specific test
```

### Code Quality
```bash
./vendor/bin/pint       # Format code (Laravel Pint)
./vendor/bin/phpmd app text phpmd.xml  # Check code complexity
```

### Code Complexity Guidelines

Per `laravelcomplexity-guardrails` skill, maintain these thresholds:

| Metric | Warning | Hard Limit |
|--------|---------|------------|
| Cyclomatic Complexity | > 7 | > 10 |
| NPath Complexity | > 200 | > 500 |
| Method Length | > 80 lines | > 100 lines |
| Class Length | > 400 lines | > 600 lines |
| Parameter Count | > 5 | > 8 |

**Refactoring Patterns** (when limits exceeded):
1. **Extract Method** - Break long methods into focused helpers
2. **Extract Class** - Move related methods to dedicated service/transformer
3. **Replace Conditional with Polymorphism** - Use Strategy pattern for complex switch/if chains
4. **Introduce Parameter Object** - Group related parameters into value objects

**Prior Refactoring** (DBP-033 series):
- `DepositTransformer` - Extracted from FinanceFilterService
- `ProviderStatusCollector` - Extracted from NotificationsController
- `FirstInvoiceItemBuilder` - Extracted from InvoiceService
- `TenantIndexService` - Extracted from TenantController
- `LedgerTransactionBuilder` - Extracted from TenantController
- `BulkRentAdjuster` - Extracted from BulkOperationsController
- `BulkImportValidator` - Extracted from PaymentController
- `PaymentCallbackProcessor` - Extracted from PaymentController

### Database
```bash
php artisan migrate              # Run migrations
php artisan migrate:fresh --seed # Fresh database with seeders
php artisan db:seed              # Run seeders only
```

### Building for Production
```bash
npm run build
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## Architecture

### Multi-Tenancy System

The application uses the `TenantScope` trait (app/Traits/TenantScope.php) for automatic data isolation:

- **Landlords** see only their own data (scoped by `landlord_id`)
- **Caretakers** see data belonging to their assigned landlord (via `user.landlord_id`)
- **Tenants** see data scoped to their landlord
- **Super Admins** bypass all scopes

All models with tenant isolation must use the `TenantScope` trait. The trait automatically:
1. Applies global scopes on queries based on authenticated user's role
2. Auto-fills `landlord_id` when creating records

### Data Hierarchy

```
User (Landlord/Caretaker/Tenant)
  └─ Property
      └─ Building (Wing/Block)
          └─ Unit
              └─ Lease
                  ├─ Tenant (User)
                  ├─ RentHistory
                  └─ Invoice
              └─ WaterReading
```

### Key Models & Relationships

- **Property**: `hasMany(Building)`, `belongsTo(User, 'landlord_id')`
- **Building**: `belongsTo(Property)`, `hasMany(Unit)`
- **Unit**: `belongsTo(Building)`, `hasMany(Lease)`, `hasOne(activeLease)`
  - Status: `vacant`, `occupied`, `maintenance`, `arrears`
  - Has `target_rent` (market price) and meter tracking
- **Lease**: `belongsTo(Unit)`, `belongsTo(User, 'tenant_id')`, `hasMany(RentHistory)`
  - Tracks rent amount, deposit, wallet balance (prepayments)
  - Has `is_active` flag for current leases
- **WaterReading**: `belongsTo(Unit)`
  - Tracks consumption and cost per unit
  - Has `is_invoiced` lock to prevent duplicate billing
- **Invoice**: `belongsTo(Lease)`
  - Consolidates rent + water + arrears
  - Status: `draft`, `sent`, `partial`, `paid`, `overdue`

### Routing Structure

Routes are in `routes/web.php`:

- **Dashboard**: `/dashboard` - Main visualizer showing unit grid with color-coded statuses
  - Landlords see their property's buildings and units
  - Caretakers redirected to Caretaker/Dashboard view
  - Uses query param `?building_id=X` to switch between buildings
- **Onboarding**: `/onboarding` - First-time property/building setup
- **Leases**:
  - `/units/{unit}/lease/create` - Add new tenant
  - `/leases/{lease}/adjust-rent` - Single rent adjustment
  - `/leases/batch-adjust` - Bulk rent increase
- **Buildings**:
  - `/buildings/{building}/configure` - The "Architect" - edit floors/units
  - `/buildings/{building}/update-units` - Update unit configuration
  - `/buildings/{building}/add-unit` - Add individual unit
  - `/properties/{property}/buildings` - Add new wing
- **Tenants**: `/tenants/{tenant}` (PUT) - Update tenant profile
- **Water Readings**: `/readings` - "The Water Guy" - log meter readings
- **Profile**: Standard Breeze profile routes

### Frontend Structure (resources/js)

- **Pages/**: Inertia page components
  - `Dashboard.vue` - Main unit visualizer (landlord view)
  - `Caretaker/Dashboard.vue` - Caretaker-specific view
  - `Onboarding/Index.vue` - Property setup wizard
  - `Leases/Create.vue` - Add tenant form
  - `Buildings/Edit.vue` - Building configuration ("The Architect")
  - `Readings/Index.vue` - Water meter readings
  - `Tenants/Show.vue` - Tenant profile modal
  - `Auth/` - Breeze authentication pages
  - `Profile/` - User profile management

- **Components/**: Reusable UI components (mostly Breeze defaults)
  - Form inputs, buttons, modals, dropdowns
  - Uses Heroicons for icons

- **Layouts/**:
  - `AuthenticatedLayout.vue` - Main app layout with navigation
  - `GuestLayout.vue` - Public pages layout

### Unit Status Color System

The dashboard visualizer uses color-coded cards:
- **Green** (`bg-green-50 border-green-200`): Occupied
- **Orange** (`bg-orange-50 border-orange-200`): Maintenance
- **Red** (`bg-red-50 border-red-200`): Arrears
- **Gray** (`bg-gray-50 border-gray-200`): Vacant

Status calculation logic is in the dashboard route (routes/web.php:48-55).

## Important Conventions

### Security & Data Access
- Always use models with `TenantScope` trait for landlord-owned data
- Never bypass tenant scoping unless explicitly needed (use `withoutGlobalScope('landlord')`)
- Encrypted fields: `national_id`, `bank_details` (configured in User model)
- Invoice document paths should be private (S3 or storage/app/private)

### Credential Storage Security Rules (CRITICAL)

This is a **multi-tenant SaaS application**. Follow these rules strictly:

#### 1. NEVER Put Per-Tenant Credentials in .env

**Violations include storing these in .env:**
- Payment provider API keys (Paystack, M-Pesa, IntaSend)
- Per-landlord OAuth credentials
- Per-landlord webhook secrets
- Any credential that varies per landlord/tenant

**Correct approach:**
- Store in `payment_configurations` table (or appropriate settings table)
- Use `encrypted` cast for secret keys
- Provide frontend UI in Settings for landlords to configure

#### 2. Platform-Level Secrets in .env (Acceptable)

These belong in .env because they're shared across all tenants:
- `APP_KEY` - Laravel encryption key
- `DB_*` - Database connection
- `MAIL_*` - Platform email sender (all emails go through platform)
- `AWS_*` - Platform storage account
- `REVERB_*` - WebSocket server
- Platform's own payment wallet IDs (for collecting platform fees)

#### 3. Encryption Requirements

All secret keys stored in database MUST use Laravel's `encrypted` cast:
```php
protected $casts = [
    'mpesa_passkey' => 'encrypted',
    'intasend_secret_key' => 'encrypted',
    // ... all secret keys
];
```

#### 4. Frontend Never Sees Secrets

- Never pass secret keys to frontend (even masked)
- Use separate endpoints for key validation
- Show only "Configured" / "Not configured" status

#### 5. Audit Trail

All credential changes must be logged via the `Auditable` trait.

#### Environment Variable Categories Reference

| Category | Storage | Example |
|----------|---------|---------|
| **Infrastructure** | .env | `DB_*`, `REDIS_*`, `CACHE_*` |
| **Platform Services** | .env | `MAIL_*`, `AWS_*`, `REVERB_*` |
| **Platform Fees** | .env | `INTASEND_PLATFORM_WALLET_ID` |
| **Security Policies** | .env | `RATE_LIMIT_*`, `PASSWORD_*` |
| **Per-Tenant Payment** | Database | Paystack, M-Pesa, IntaSend keys |
| **Per-Tenant OAuth** | Database | If white-label OAuth |
| **Per-Tenant Webhooks** | Database | Webhook secrets/challenges |

**See:** `app/Models/PaymentConfiguration.php`, `resources/js/Pages/Settings/partials/PaymentMethodsTab.vue`

### Database
- All tenant-scoped tables must have `landlord_id` foreign key
- Use soft deletes cautiously due to foreign key cascades
- Water readings use `is_invoiced` flag to prevent duplicate billing
- Leases have `is_active` boolean for current occupancy tracking

### Queued Mail & Transactions
All Mailable classes implementing `ShouldQueue` have `$afterCommit = true` property. This ensures:
- Emails are only queued AFTER the database transaction commits
- If a transaction rolls back, no email is sent
- Use `Mail::queue()` (not `Mail::send()`) inside transactions to benefit from this behavior

**Pattern:**
```php
DB::transaction(function () use ($invoice, $payment) {
    // ... database operations ...

    // Safe: Will only queue after transaction commits
    Mail::to($tenant->email)->queue(new PaymentReceived($payment, $invoice));
});
```

See `tests/Feature/TransactionRollbackTest.php` for verification tests.

### N+1 Query Detection (Development Only)

Lazy loading prevention is enabled in non-production environments (`local`, `testing`, `staging`). When a relationship is accessed without eager loading, it logs a warning to `storage/logs/security.log` instead of throwing an exception.

**What it catches**: N+1 query patterns like:
```php
// BAD - Triggers N+1 warning
$units = Unit::all();
foreach ($units as $unit) {
    echo $unit->building->name; // Lazy loads building for each unit
}

// GOOD - Eager load upfront
$units = Unit::with('building')->get();
foreach ($units as $unit) {
    echo $unit->building->name; // Already loaded
}
```

**Log format**:
```
[warning] N+1 Query Detected {"model":"App\\Models\\Unit","relation":"building","trace":[...]}
```

**Fixing N+1 queries**:
1. Check `storage/logs/security.log` for warnings
2. Add `->with(['relation'])` to your query
3. For conditional loading, use `->load('relation')` or `->loadMissing('relation')`
4. See DBP-020 in progress.md for optimization patterns

### Logging & Error Handling

**Never log secrets.** The codebase has established patterns for secure logging:

1. **Use `redactSecrets()` for external API responses**
   - `PaystackService::redactSecrets()` (line 599-609)
   - `MpesaService::redactSecrets()` (line 440-451)
   - Patterns redacted: `secret_key`, `authorization`, `Bearer`, `password`, `token`, `api_key`

2. **Mask PII to last 4 characters**
   ```php
   // Good - mask phone numbers
   Log::info('Payment received', ['phone' => substr($phone, -4)]);

   // Bad - full PII exposed
   Log::info('Payment received', ['phone' => $phone]);
   ```

3. **Always use structured context arrays**
   ```php
   // Good - structured context
   Log::error('Operation failed', [
       'reference' => $reference,
       'status' => $response->status(),
       'error' => $e->getMessage(),
   ]);

   // Bad - concatenated strings
   Log::error("Operation failed: $reference - " . $e->getMessage());
   ```

4. **Use DomainException for business exceptions**
   - Automatically sanitizes context before logging (line 145)
   - Strips: password, secret, ssn, token, api_key, credentials, credit_card
   - Masks: email, phone, mobile, account_number, national_id

**Reference implementations**: See `app/Services/PaystackService.php`, `app/Services/MpesaService.php`, `app/Exceptions/DomainException.php`

### Frontend
- Use Ziggy for route generation in Vue: `route('leases.create', unit.id)`
- Inertia props are passed from controllers, accessed via `defineProps()`
- Form submissions use Inertia's `useForm()` composable
- Modal state managed in parent components, passed as props

### Code Style
- Run `./vendor/bin/pint` before committing
- Follow Laravel naming conventions (StudlyCase for classes, snake_case for columns)
- Controllers should be RESTful where applicable
- Keep business logic in models, controllers thin

## Testing

### TDD Workflow (RED-GREEN-REFACTOR)

**Every production change starts with a failing test.** This is mandatory, not optional.

| Phase | What to Do |
|-------|------------|
| **RED** | Write a failing test first. Confirm it fails for the right reason. |
| **GREEN** | Write the simplest code to make the test pass. No extras. |
| **REFACTOR** | Clean up code while keeping tests green. Extract services if needed. |

**Why test-first?**
- Forces you to think about the API/interface before implementation
- Prevents "it works on my machine" syndrome
- Creates living documentation of expected behavior
- Makes refactoring safe

### Test Organization

```
tests/
├── Unit/                    # Isolated logic, no HTTP, no database (fast)
│   ├── Services/            # Service class unit tests
│   ├── ValueObjects/        # Value object tests
│   ├── Traits/              # Trait tests
│   └── Policies/            # Authorization policy tests
├── Feature/                 # HTTP/integration tests (slower, real database)
│   ├── Controllers/         # Controller workflow tests
│   ├── Api/                 # API endpoint tests
│   └── Services/            # Service integration tests
└── Browser/                 # Dusk E2E tests (slowest, real browser)
```

**When to use each:**
- **Unit tests**: Pure functions, value objects, transformers, calculations
- **Feature tests**: HTTP endpoints, database transactions, multi-step workflows
- **Browser tests**: JavaScript-heavy flows, visual regression (use sparingly)

### Test Commands

```bash
php artisan test                          # Run full suite
php artisan test --parallel               # Parallel execution (faster)
php artisan test --filter=InvoiceService  # Run specific test/class
php artisan test tests/Feature/Controllers/ # Run suite subset
php artisan test --coverage --min=70      # With coverage (CI enforced)
```

### Test Helpers

Two traits are available for common setup patterns:

| Trait | Purpose |
|-------|---------|
| `CreatesTestData` | Factory-based setup for landlords, properties, buildings, units, leases, invoices, payments |
| `MocksExternalServices` | Mock Paystack, M-Pesa, and other external APIs |

Usage:
```php
class InvoiceControllerTest extends TestCase
{
    use RefreshDatabase, CreatesTestData, MocksExternalServices;

    public function test_invoice_can_be_generated(): void
    {
        $this->createTestData();  // Sets up $this->landlord, $this->lease, etc.
        $this->mockPaystack();     // Prevents real API calls

        $response = $this->actingAs($this->landlord)
            ->post(route('invoices.generate'));

        $response->assertRedirect();
        $this->assertDatabaseHas('invoices', ['lease_id' => $this->lease->id]);
    }
}
```

### Writing Good Tests

**Do:**
- Use model factories, not manual `create()` calls
- Name tests by behavior: `test_rejects_payment_when_invoice_already_paid()`
- One assertion per test (or closely related assertions)
- Use `$this->assertDatabaseHas()` for state verification
- Mock external services (payment gateways, SMS APIs)

**Don't:**
- Use `sleep()` or time-dependent logic
- Test framework behavior (Laravel already tests Eloquent)
- Write tests after the code is "done"
- Skip the RED phase - you must see the test fail first

### PRD Task Template

When defining new PRD tasks, always include:
```json
{
  "steps": [
    "Write failing test for [acceptance criteria]",
    "Implement minimum code to pass",
    "Refactor if needed",
    "Run full test suite"
  ]
}
```

### Database Configuration

Tests run against MySQL by default (configure in `.env.testing`):
- Uses `propmanager_test` database
- `RefreshDatabase` trait resets between tests
- Factories available for all 76+ models (see `database/factories/`)

## Environment Configuration

Required environment variables (see `.env.example`):
- `DB_CONNECTION=mysql` (default)
- `QUEUE_CONNECTION=database` (for background jobs)
- `SESSION_DRIVER=database`
- `MAIL_MAILER=log` (development) or smtp (production)

Optional for production:
- AWS credentials for S3 file storage
- Payment gateway keys (Paystack/Stripe) - infrastructure exists in schema

## Common Development Patterns

### Adding a New Tenant-Scoped Model
1. Create migration with `landlord_id` foreign key
2. Add model with `use TenantScope;` trait
3. Define relationships to parent/child models
4. Add to relevant controller with proper authorization

### Adding a New Dashboard Feature
1. Update route in `routes/web.php`
2. Create/update controller method
3. Pass data via Inertia::render()
4. Create/update Vue component in `resources/js/Pages/`
5. Add navigation link in `AuthenticatedLayout.vue` if needed

### Modifying Unit Status Logic
Status is calculated in the dashboard route (routes/web.php). To add new statuses:
1. Update `units` table enum
2. Add color class mapping in dashboard route
3. Update unit card styling in Dashboard.vue

### Adding Water Billing Logic
Water readings flow: WaterReading → Invoice → Payment
- Create reading with `is_invoiced=false`
- Include in invoice generation
- Mark `is_invoiced=true` after invoice created
- Link invoice to lease for tenant association

---

## Current Implementation Status (Dec 2024)

### ✅ Completed Features

#### 1. Water Readings Module
- **WaterReadingObserver** (app/Observers/WaterReadingObserver.php)
  - Auto-calculates consumption (current - previous reading)
  - Auto-calculates cost (consumption × rate, currently 150 KES/unit)
  - Auto-sets landlord_id based on user role
  - Prevents modification of invoiced readings
- **Observer Registration**: Registered in AppServiceProvider
- **WaterReadingController** (app/Http/Controllers/WaterReadingController.php)
  - index(): Display water reading input form
  - store(): Record batch water readings with validation
  - history(): View reading history with filters
  - update(): Edit non-invoiced readings
  - destroy(): Delete non-invoiced readings
  - Duplicate reading detection
  - Reading validation (current >= previous)
  - Batch error handling
- **Frontend**:
  - Readings/Index.vue - Water meter input page
  - Readings/History.vue - Reading history with filters and inline editing

#### 2. Invoice Generation System
- **InvoiceService** (app/Services/InvoiceService.php)
  - Generates invoices for leases with rent + water + arrears
  - Auto-generates invoice numbers (format: INV-YYYYMM-NNNN)
  - Calculates water charges from uninvoiced readings
  - Tracks previous arrears and rolls them forward
  - Marks water readings as invoiced after inclusion
- **InvoiceController** (app/Http/Controllers/InvoiceController.php)
  - index(): List invoices with filters (search, status)
  - show(): View single invoice with payment details
  - generate(): Generate invoices for all active leases
  - updateStatus(): Change invoice status
  - recordPayment(): Record partial/full payments with email notification
  - destroy(): Delete draft invoices
- **GenerateMonthlyInvoices Command**
  - Artisan command: `php artisan invoices:generate --month=12 --year=2024`
  - Bulk generates invoices for all active leases
- **Frontend**:
  - Invoices/Index.vue - Invoice list with search/filter
  - Invoices/Show.vue - Invoice details with payment recording

#### 3. Payment Processing System
- **Payment Model** (app/Models/Payment.php)
  - Multi-tenant scoped with TenantScope trait
  - Relationships: invoice, lease, landlord
  - Supports: cash, bank_transfer, mobile_money, paystack, stripe
- **PaystackService** (app/Services/PaystackService.php)
  - Initialize transactions with metadata
  - Verify transactions via webhook
  - Generate unique payment references
- **PaymentController** (app/Http/Controllers/PaymentController.php)
  - initializePaystack(): Create Paystack payment session
  - handleCallback(): Process payment verification and auto-update invoice
  - getPublicKey(): Return Paystack public key for frontend
  - downloadReceipt(): Generate PDF receipt
- **PaymentReceived Mailable** (app/Mail/PaymentReceived.php)
  - Queued email notification to tenants
  - Includes payment details, invoice summary, receipt download link
- **PDF Receipt Generation**
  - DomPDF integration for professional receipts
  - Template: resources/views/receipts/payment-receipt.blade.php
- **Email Template**
  - resources/views/emails/payment-received.blade.php
  - Markdown format with payment and invoice details
- **Frontend Integration**:
  - Manual payment modal in Invoices/Show.vue
  - Paystack payment modal with redirect flow
  - Payment history display

#### 4. Caretaker Invitation System
- **Invitation Model** (app/Models/Invitation.php)
  - Relationships: landlord, property
  - Token generation and validation
  - Expiration tracking (30 days)
  - Query scopes: pending, accepted, expired
  - Helper methods: isAccepted(), isExpired(), isValid()
- **InvitationController** (app/Http/Controllers/InvitationController.php)
  - index(): List all invitations for landlord
  - store(): Create and send invitation via email
  - show(): Display invitation acceptance page (public)
  - accept(): Accept invitation and create caretaker account
  - resend(): Resend invitation email
  - destroy(): Cancel pending invitation
  - Validation: Duplicate prevention, ownership verification
- **CaretakerInvitation Mailable** (app/Mail/CaretakerInvitation.php)
  - Queued email with invitation link
  - Includes: landlord name, property name, expiration date
- **Email Template**
  - resources/views/emails/caretaker-invitation.blade.php
  - Professional invitation with accept button
- **Frontend**:
  - Invitations/Index.vue - Invitation management for landlords
    - Send new invitations with property selection
    - View invitation status (pending/accepted/expired)
    - Resend or cancel pending invitations
    - Copy invitation link functionality
  - Invitations/Accept.vue - Public invitation acceptance page
    - Display invitation details
    - Caretaker account creation form
    - Validation and error handling
- **User Model Enhancements**:
  - invitations() relationship for landlords
  - landlord() relationship for caretakers
  - caretakers() relationship for landlords
  - isCaretaker(), isTenant() helper methods
- **Navigation**:
  - Added "Caretakers" link to authenticated layout (landlords only)
  - Added "Invoices" link to authenticated layout (landlords only)

#### 5. Document Upload/Management System
- **Document Model** (app/Models/Document.php)
  - Polymorphic relationships (attach to any model - Lease, User, etc.)
  - Multi-tenant scoped with TenantScope trait
  - Support for 8 document types: lease_agreement, tenant_id, tenant_passport, bank_statement, payslip, reference_letter, utility_bill, other
  - File metadata tracking: size, MIME type, uploader
  - Soft deletes for audit trail
  - Helper methods: isImage(), isPdf(), fileExists(), deleteFile()
  - Formatted file size display
- **DocumentController** (app/Http/Controllers/DocumentController.php)
  - index(): List all documents with pagination and filters
  - store(): Upload new documents (max 10MB, validates file types)
  - download(): Secure file download with authorization
  - view(): View PDFs and images inline
  - destroy(): Delete documents and physical files
  - forModel(): AJAX endpoint to fetch documents for specific models
  - Role-based authorization (landlords, caretakers, tenants)
- **Storage Configuration**
  - Private storage in storage/app/private
  - Documents organized by landlord_id and model type
  - Secure file serving with authorization checks
  - Support for local and S3 storage (configured)
- **Model Integrations**:
  - Lease model: documents() and leaseAgreement() relationships
  - User model: documents() relationship for tenant documents
- **Frontend**:
  - Documents/Index.vue - Full document management interface
    - Upload modal with drag-and-drop support
    - Filter by document type and attachment
    - Search functionality
    - View/download/delete actions
    - File type icons (PDF, images)
    - Pagination
- **Navigation**:
  - Added "Documents" link to authenticated layout (landlords only)
- **Security**:
  - Authorization checks prevent cross-tenant access
  - Private file storage (not publicly accessible)
  - Validation of file types and sizes
  - Ownership verification on all operations

#### 6. M-Pesa Integration (Kenya)
- **MpesaService** (app/Services/MpesaService.php)
  - OAuth token management with caching
  - STK Push (Lipa Na M-Pesa Online) initiation
  - STK status query
  - C2B URL registration
  - Phone number formatting (0xx → 254xx)
  - IP whitelist validation for webhooks
- **MpesaWebhookController** (app/Http/Controllers/Api/MpesaWebhookController.php)
  - stkCallback(): Handle STK Push results
  - c2bValidation(): Validate incoming C2B payments
  - c2bConfirmation(): Confirm and record C2B payments
  - IP validation from Safaricom servers
  - Idempotency with pessimistic locking
- **Configuration** (config/mpesa.php)
  - Environment switching (sandbox/production)
  - STK Push, C2B, B2C settings
  - Safaricom IP whitelist
- **Database Fields**:
  - payments.mpesa_transaction_id - M-Pesa receipt number
  - payments.mpesa_checkout_request_id - STK Push tracking
- **Webhook Routes**:
  - POST /webhooks/mpesa/stk-callback
  - POST /webhooks/mpesa/c2b/validation
  - POST /webhooks/mpesa/c2b/confirmation

#### 7. REST API (Mobile App & Integrations)
- **Authentication** (app/Http/Controllers/Api/AuthController.php)
  - POST /api/v1/auth/login - Token-based login with abilities
  - POST /api/v1/auth/register - New tenant registration
  - POST /api/v1/auth/logout - Revoke current token
  - GET /api/v1/auth/user - Current user info
- **Tenant Endpoints** (for mobile app):
  - GET /api/v1/tenant/lease - Current lease details
  - GET /api/v1/tenant/invoices - Invoice list with pagination
  - GET /api/v1/tenant/payments - Payment history
  - POST /api/v1/tenant/payments/mpesa/initiate - Initiate M-Pesa STK Push
  - POST /api/v1/tenant/payments/paystack/initiate - Initiate Paystack payment
  - GET /api/v1/tenant/notifications - Notification list
- **Landlord Endpoints**:
  - GET /api/v1/landlord/properties - Property list
  - GET /api/v1/landlord/buildings - Building list with unit counts
  - GET /api/v1/landlord/units - Unit list with filters
  - GET /api/v1/landlord/invoices - All invoices
  - GET /api/v1/landlord/payments - All payments
  - GET /api/v1/landlord/reports/occupancy - Occupancy statistics
  - GET /api/v1/landlord/reports/revenue - Revenue by payment method
  - GET /api/v1/landlord/reports/arrears - Aged receivables report
- **API Resources** (app/Http/Resources/):
  - InvoiceResource, PaymentResource, LeaseResource
- **Token Abilities**:
  - tenant:read - Tenant mobile app access
  - landlord:manage - Landlord/caretaker access
  - integration:webhook - Third-party integrations

#### 8. IntaSend M-Pesa Integration (Configuration Complete)
- **Per-Landlord Configuration** (payment_configurations table):
  - `intasend_enabled` - Toggle IntaSend payments
  - `intasend_publishable_key` - Public key (ISPubKey_*)
  - `intasend_secret_key` - Secret key (encrypted, ISSecretKey_*)
  - `intasend_webhook_challenge` - Challenge-based webhook verification
  - `intasend_environment` - sandbox/production
- **Platform Configuration** (config/intasend.php):
  - API endpoints (sandbox/production URLs)
  - HTTP client settings (timeout, retry)
  - Platform wallet ID for fee collection
- **Settings UI**: Settings > Payment Methods tab
- **Webhook Verification**: Challenge-based (NOT HMAC)
- **Transaction States**: PENDING → PROCESSING → COMPLETE/FAILED
- **Docs**: https://developers.intasend.com/docs
- **Next**: PAY-007 (IntaSendService), PAY-009 (Webhook Controller)

### 📋 Planned Features (In Order)

#### 8. Reporting & Analytics
- Financial reports (rent collection rates, arrears aging)
- Occupancy statistics
- Revenue trends and projections
- Export to PDF/Excel

#### 9. Notification System (Partially Complete)
- ✅ Email notifications for payment received
- ✅ Email notifications for caretaker invitations
- Need: Rent due reminders
- Need: Arrears warnings
- Need: SMS notifications (optional via Africa's Talking API)
- Need: Notification preferences per user
- Need: Scheduled notifications (via Laravel queue/scheduler)

#### 10. Bulk Operations
- Bulk tenant import from CSV
- Bulk rent adjustments (partially exists)
- Bulk unit status updates
- Bulk email/SMS communications

### 💡 Usage Notes for Current Features

**Generate Invoices:**
```bash
# Generate for current month
php artisan invoices:generate

# Generate for specific month
php artisan invoices:generate --month=11 --year=2024
```

**Water Readings Workflow:**
1. Record readings at `/readings` (uses previous reading from last entry)
2. Readings stored with `is_invoiced=false`
3. When invoice generated, water charges calculated from uninvoiced readings
4. After invoice creation, readings marked `is_invoiced=true`
5. View history at `/readings/history` (pending Vue component)

**Invoice Status Flow:**
- `draft` → Created but not sent to tenant
- `sent` → Delivered to tenant
- `partial` → Some payment received
- `paid` → Fully paid
- `overdue` → Past due date without full payment

**API Authentication (Mobile App):**
```bash
# Login and get token
curl -X POST http://localhost/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"tenant@example.com","password":"password","device_name":"iPhone 15"}'

# Use token for authenticated requests
curl http://localhost/api/v1/tenant/invoices \
  -H "Authorization: Bearer {token}"
```

**M-Pesa STK Push (from API):**
```bash
curl -X POST http://localhost/api/v1/tenant/payments/mpesa/initiate \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{"invoice_id":1,"amount":5000,"phone":"0712345678"}'
```

### 🐛 Known Issues

- None currently - all core features fully implemented and tested

===

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to enhance the user's satisfaction building Laravel applications.

## Foundational Context
This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.4.12
- inertiajs/inertia-laravel (INERTIA) - v2
- laravel/framework (LARAVEL) - v12
- laravel/prompts (PROMPTS) - v0
- laravel/reverb (REVERB) - v1
- laravel/sanctum (SANCTUM) - v4
- tightenco/ziggy (ZIGGY) - v2
- laravel/breeze (BREEZE) - v2
- laravel/dusk (DUSK) - v8
- laravel/mcp (MCP) - v0
- laravel/pint (PINT) - v1
- laravel/sail (SAIL) - v1
- phpunit/phpunit (PHPUNIT) - v11
- @inertiajs/vue3 (INERTIA) - v2
- eslint (ESLINT) - v9
- laravel-echo (ECHO) - v2
- tailwindcss (TAILWINDCSS) - v4
- vue (VUE) - v3

## Conventions
- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts
- Do not create verification scripts or tinker when tests cover that functionality and prove it works. Unit and feature tests are more important.

## Application Structure & Architecture
- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling
- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Replies
- Be concise in your explanations - focus on what's important rather than explaining obvious details.

## Documentation Files
- You must only create documentation files if explicitly requested by the user.

=== boost rules ===

## Laravel Boost
- Laravel Boost is an MCP server that comes with powerful tools designed specifically for this application. Use them.

## Artisan
- Use the `list-artisan-commands` tool when you need to call an Artisan command to double-check the available parameters.

## URLs
- Whenever you share a project URL with the user, you should use the `get-absolute-url` tool to ensure you're using the correct scheme, domain/IP, and port.

## Tinker / Debugging
- You should use the `tinker` tool when you need to execute PHP to debug code or query Eloquent models directly.
- Use the `database-query` tool when you only need to read from the database.

## Reading Browser Logs With the `browser-logs` Tool
- You can read browser logs, errors, and exceptions using the `browser-logs` tool from Boost.
- Only recent browser logs will be useful - ignore old logs.

## Searching Documentation (Critically Important)
- Boost comes with a powerful `search-docs` tool you should use before any other approaches when dealing with Laravel or Laravel ecosystem packages. This tool automatically passes a list of installed packages and their versions to the remote Boost API, so it returns only version-specific documentation for the user's circumstance. You should pass an array of packages to filter on if you know you need docs for particular packages.
- The `search-docs` tool is perfect for all Laravel-related packages, including Laravel, Inertia, Livewire, Filament, Tailwind, Pest, Nova, Nightwatch, etc.
- You must use this tool to search for Laravel ecosystem documentation before falling back to other approaches.
- Search the documentation before making code changes to ensure we are taking the correct approach.
- Use multiple, broad, simple, topic-based queries to start. For example: `['rate limiting', 'routing rate limiting', 'routing']`.
- Do not add package names to queries; package information is already shared. For example, use `test resource table`, not `filament 4 test resource table`.

### Available Search Syntax
- You can and should pass multiple queries at once. The most relevant results will be returned first.

1. Simple Word Searches with auto-stemming - query=authentication - finds 'authenticate' and 'auth'.
2. Multiple Words (AND Logic) - query=rate limit - finds knowledge containing both "rate" AND "limit".
3. Quoted Phrases (Exact Position) - query="infinite scroll" - words must be adjacent and in that order.
4. Mixed Queries - query=middleware "rate limit" - "middleware" AND exact phrase "rate limit".
5. Multiple Queries - queries=["authentication", "middleware"] - ANY of these terms.

=== php rules ===

## PHP

- Always use curly braces for control structures, even if it has one line.

### Constructors
- Use PHP 8 constructor property promotion in `__construct()`.
    - <code-snippet>public function __construct(public GitHub $github) { }</code-snippet>
- Do not allow empty `__construct()` methods with zero parameters unless the constructor is private.

### Type Declarations
- Always use explicit return type declarations for methods and functions.
- Use appropriate PHP type hints for method parameters.

<code-snippet name="Explicit Return Types and Method Params" lang="php">
protected function isAccessible(User $user, ?string $path = null): bool
{
    ...
}
</code-snippet>

## Comments
- Prefer PHPDoc blocks over inline comments. Never use comments within the code itself unless there is something very complex going on.

## PHPDoc Blocks
- Add useful array shape type definitions for arrays when appropriate.

## Enums
- Typically, keys in an Enum should be TitleCase. For example: `FavoritePerson`, `BestLake`, `Monthly`.

=== tests rules ===

## Test Enforcement

- Every change must be programmatically tested. Write a new test or update an existing test, then run the affected tests to make sure they pass.
- Run the minimum number of tests needed to ensure code quality and speed. Use `php artisan test --compact` with a specific filename or filter.

=== inertia-laravel/core rules ===

## Inertia

- Inertia.js components should be placed in the `resources/js/Pages` directory unless specified differently in the JS bundler (`vite.config.js`).
- Use `Inertia::render()` for server-side routing instead of traditional Blade views.
- Use the `search-docs` tool for accurate guidance on all things Inertia.

<code-snippet name="Inertia Render Example" lang="php">
// routes/web.php example
Route::get('/users', function () {
    return Inertia::render('Users/Index', [
        'users' => User::all()
    ]);
});
</code-snippet>

=== inertia-laravel/v2 rules ===

## Inertia v2

- Make use of all Inertia features from v1 and v2. Check the documentation before making any changes to ensure we are taking the correct approach.

### Inertia v2 New Features
- Deferred props.
- Infinite scrolling using merging props and `WhenVisible`.
- Lazy loading data on scroll.
- Polling.
- Prefetching.

### Deferred Props & Empty States
- When using deferred props on the frontend, you should add a nice empty state with pulsing/animated skeleton.

### Inertia Form General Guidance
- The recommended way to build forms when using Inertia is with the `<Form>` component - a useful example is below. Use the `search-docs` tool with a query of `form component` for guidance.
- Forms can also be built using the `useForm` helper for more programmatic control, or to follow existing conventions. Use the `search-docs` tool with a query of `useForm helper` for guidance.
- `resetOnError`, `resetOnSuccess`, and `setDefaultsOnSuccess` are available on the `<Form>` component. Use the `search-docs` tool with a query of `form component resetting` for guidance.

=== laravel/core rules ===

## Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using the `list-artisan-commands` tool.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Database
- Always use proper Eloquent relationship methods with return type hints. Prefer relationship methods over raw queries or manual joins.
- Use Eloquent models and relationships before suggesting raw database queries.
- Avoid `DB::`; prefer `Model::query()`. Generate code that leverages Laravel's ORM capabilities rather than bypassing them.
- Generate code that prevents N+1 query problems by using eager loading.
- Use Laravel's query builder for very complex database operations.

### Model Creation
- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `list-artisan-commands` to check the available options to `php artisan make:model`.

### APIs & Eloquent Resources
- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

### Controllers & Validation
- Always create Form Request classes for validation rather than inline validation in controllers. Include both validation rules and custom error messages.
- Check sibling Form Requests to see if the application uses array or string based validation rules.

### Queues
- Use queued jobs for time-consuming operations with the `ShouldQueue` interface.

### Authentication & Authorization
- Use Laravel's built-in authentication and authorization features (gates, policies, Sanctum, etc.).

### URL Generation
- When generating links to other pages, prefer named routes and the `route()` function.

### Configuration
- Use environment variables only in configuration files - never use the `env()` function directly outside of config files. Always use `config('app.name')`, not `env('APP_NAME')`.

### Testing
- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

### Vite Error
- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== laravel/v12 rules ===

## Laravel 12

- Use the `search-docs` tool to get version-specific documentation.
- Since Laravel 11, Laravel has a new streamlined file structure which this project uses.

### Laravel 12 Structure
- In Laravel 12, middleware are no longer registered in `app/Http/Kernel.php`.
- Middleware are configured declaratively in `bootstrap/app.php` using `Application::configure()->withMiddleware()`.
- `bootstrap/app.php` is the file to register middleware, exceptions, and routing files.
- `bootstrap/providers.php` contains application specific service providers.
- The `app\Console\Kernel.php` file no longer exists; use `bootstrap/app.php` or `routes/console.php` for console configuration.
- Console commands in `app/Console/Commands/` are automatically available and do not require manual registration.

### Database
- When modifying a column, the migration must include all of the attributes that were previously defined on the column. Otherwise, they will be dropped and lost.
- Laravel 12 allows limiting eagerly loaded records natively, without external packages: `$query->latest()->limit(10);`.

### Models
- Casts can and likely should be set in a `casts()` method on a model rather than the `$casts` property. Follow existing conventions from other models.

=== pint/core rules ===

## Laravel Pint Code Formatter

- You must run `vendor/bin/pint --dirty` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test`, simply run `vendor/bin/pint` to fix any formatting issues.

=== phpunit/core rules ===

## PHPUnit

- This application uses PHPUnit for testing. All tests must be written as PHPUnit classes. Use `php artisan make:test --phpunit {name}` to create a new test.
- If you see a test using "Pest", convert it to PHPUnit.
- Every time a test has been updated, run that singular test.
- When the tests relating to your feature are passing, ask the user if they would like to also run the entire test suite to make sure everything is still passing.
- Tests should test all of the happy paths, failure paths, and weird paths.
- You must not remove any tests or test files from the tests directory without approval. These are not temporary or helper files; these are core to the application.

### Running Tests
- Run the minimal number of tests, using an appropriate filter, before finalizing.
- To run all tests: `php artisan test --compact`.
- To run all tests in a file: `php artisan test --compact tests/Feature/ExampleTest.php`.
- To filter on a particular test name: `php artisan test --compact --filter=testName` (recommended after making a change to a related file).

=== inertia-vue/core rules ===

## Inertia + Vue

- Vue components must have a single root element.
- Use `router.visit()` or `<Link>` for navigation instead of traditional links.

<code-snippet name="Inertia Client Navigation" lang="vue">

    import { Link } from '@inertiajs/vue3'
    <Link href="/">Home</Link>

</code-snippet>

=== inertia-vue/v2/forms rules ===

## Inertia v2 + Vue Forms

<code-snippet name="`<Form>` Component Example" lang="vue">

<Form
    action="/users"
    method="post"
    #default="{
        errors,
        hasErrors,
        processing,
        progress,
        wasSuccessful,
        recentlySuccessful,
        setError,
        clearErrors,
        resetAndClearErrors,
        defaults,
        isDirty,
        reset,
        submit,
  }"
>
    <input type="text" name="name" />

    <div v-if="errors.name">
        {{ errors.name }}
    </div>

    <button type="submit" :disabled="processing">
        {{ processing ? 'Creating...' : 'Create User' }}
    </button>

    <div v-if="wasSuccessful">User created successfully!</div>
</Form>

</code-snippet>

=== tailwindcss/core rules ===

## Tailwind CSS

- Use Tailwind CSS classes to style HTML; check and use existing Tailwind conventions within the project before writing your own.
- Offer to extract repeated patterns into components that match the project's conventions (i.e. Blade, JSX, Vue, etc.).
- Think through class placement, order, priority, and defaults. Remove redundant classes, add classes to parent or child carefully to limit repetition, and group elements logically.
- You can use the `search-docs` tool to get exact examples from the official documentation when needed.

### Spacing
- When listing items, use gap utilities for spacing; don't use margins.

<code-snippet name="Valid Flex Gap Spacing Example" lang="html">
    <div class="flex gap-8">
        <div>Superior</div>
        <div>Michigan</div>
        <div>Erie</div>
    </div>
</code-snippet>

### Dark Mode
- If existing pages and components support dark mode, new pages and components must support dark mode in a similar way, typically using `dark:`.

=== tailwindcss/v4 rules ===

## Tailwind CSS 4

- Always use Tailwind CSS v4; do not use the deprecated utilities.
- `corePlugins` is not supported in Tailwind v4.
- In Tailwind v4, configuration is CSS-first using the `@theme` directive — no separate `tailwind.config.js` file is needed.

<code-snippet name="Extending Theme in CSS" lang="css">
@theme {
  --color-brand: oklch(0.72 0.11 178);
}
</code-snippet>

- In Tailwind v4, you import Tailwind using a regular CSS `@import` statement, not using the `@tailwind` directives used in v3:

<code-snippet name="Tailwind v4 Import Tailwind Diff" lang="diff">
   - @tailwind base;
   - @tailwind components;
   - @tailwind utilities;
   + @import "tailwindcss";
</code-snippet>

### Replaced Utilities
- Tailwind v4 removed deprecated utilities. Do not use the deprecated option; use the replacement.
- Opacity values are still numeric.

| Deprecated |	Replacement |
|------------+--------------|
| bg-opacity-* | bg-black/* |
| text-opacity-* | text-black/* |
| border-opacity-* | border-black/* |
| divide-opacity-* | divide-black/* |
| ring-opacity-* | ring-black/* |
| placeholder-opacity-* | placeholder-black/* |
| flex-shrink-* | shrink-* |
| flex-grow-* | grow-* |
| overflow-ellipsis | text-ellipsis |
| decoration-slice | box-decoration-slice |
| decoration-clone | box-decoration-clone |
</laravel-boost-guidelines>
