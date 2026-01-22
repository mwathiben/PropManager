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
| Testing | `laraveltdd-with-pest`, `verification-first` |
| Feature implementation | `feature-development`, `verification-first` |
| PRD/autonomous | `ralph-wiggum` |
| Frontend Vue | `web-design-guidelines` |
| Refactoring | `laravelcontroller-cleanup`, `laravelinterfaces-and-di` |
| Performance | `laravelperformance-caching`, `laravelperformance-select-columns` |
| Auth/policies | `laravelpolicies-and-authorization` |

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

## Tech Stack

- **Backend**: Laravel 12 (PHP 8.2+)
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
```

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

Tests run against MySQL by default (configure in `.env.testing`). Key test files:
- `tests/Feature/Auth/*` - Breeze authentication tests
- `tests/Feature/ProfileTest.php` - Profile management
- `tests/Unit/` - Unit tests for models/services

When adding features:
1. Write feature tests for user-facing flows
2. Write unit tests for complex business logic
3. Test tenant scoping to ensure data isolation

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
