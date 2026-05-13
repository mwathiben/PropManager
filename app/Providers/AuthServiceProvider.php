<?php

namespace App\Providers;

use App\Models\Building;
use App\Models\DepositTransaction;
use App\Models\Document;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Import;
use App\Models\Invitation;
use App\Models\Invoice;
use App\Models\InvoiceTemplate;
use App\Models\KycRequirement;
use App\Models\LandlordPayoutAccount;
use App\Models\LateFeePolicy;
use App\Models\Lease;
use App\Models\MoveOutDeductionCategory;
use App\Models\Payment;
use App\Models\Property;
use App\Models\ReceiptTemplate;
use App\Models\Refund;
use App\Models\TenantKycSubmission;
use App\Models\TenantPaymentVerification;
use App\Models\Ticket;
use App\Models\Unit;
use App\Models\User;
use App\Models\Vendor;
use App\Models\WaterReading;
use App\Models\WaterSetting;
use App\Policies\BuildingPolicy;
use App\Policies\DepositTransactionPolicy;
use App\Policies\DocumentPolicy;
use App\Policies\ExpenseCategoryPolicy;
use App\Policies\ExpensePolicy;
use App\Policies\ImportPolicy;
use App\Policies\InvitationPolicy;
use App\Policies\InvoicePolicy;
use App\Policies\InvoiceTemplatePolicy;
use App\Policies\KycRequirementPolicy;
use App\Policies\LandlordPayoutAccountPolicy;
use App\Policies\LateFeeRulePolicy;
use App\Policies\LeasePolicy;
use App\Policies\MoveOutDeductionCategoryPolicy;
use App\Policies\PaymentPolicy;
use App\Policies\PropertyPolicy;
use App\Policies\ReceiptTemplatePolicy;
use App\Policies\RefundPolicy;
use App\Policies\TenantKycSubmissionPolicy;
use App\Policies\TenantPaymentVerificationPolicy;
use App\Policies\TenantPolicy;
use App\Policies\TicketPolicy;
use App\Policies\UnitPolicy;
use App\Policies\VendorPolicy;
use App\Policies\WaterReadingPolicy;
use App\Policies\WaterSettingPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Property::class => PropertyPolicy::class,
        Building::class => BuildingPolicy::class,
        Unit::class => UnitPolicy::class,
        Lease::class => LeasePolicy::class,
        Invoice::class => InvoicePolicy::class,
        InvoiceTemplate::class => InvoiceTemplatePolicy::class,
        ReceiptTemplate::class => ReceiptTemplatePolicy::class,
        Payment::class => PaymentPolicy::class,
        Document::class => DocumentPolicy::class,
        Ticket::class => TicketPolicy::class,
        WaterReading::class => WaterReadingPolicy::class,
        Invitation::class => InvitationPolicy::class,
        Expense::class => ExpensePolicy::class,
        ExpenseCategory::class => ExpenseCategoryPolicy::class,
        Vendor::class => VendorPolicy::class,
        LateFeePolicy::class => LateFeeRulePolicy::class,
        DepositTransaction::class => DepositTransactionPolicy::class,
        MoveOutDeductionCategory::class => MoveOutDeductionCategoryPolicy::class,
        WaterSetting::class => WaterSettingPolicy::class,
        TenantKycSubmission::class => TenantKycSubmissionPolicy::class,
        TenantPaymentVerification::class => TenantPaymentVerificationPolicy::class,
        KycRequirement::class => KycRequirementPolicy::class,
        // SCOPE-P4: closed authz-layer gap. These models had landlord_id +
        // TenantScope but no registered Policy, so @can directives silently
        // returned false and central authz audit was missing.
        Refund::class => RefundPolicy::class,
        Import::class => ImportPolicy::class,
        LandlordPayoutAccount::class => LandlordPayoutAccountPolicy::class,
        // PRIV-12: tenant-target authorization (User model represents
        // tenants in ledger/modalData/etc. contexts).
        User::class => TenantPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();

        // Define Gates for cross-cutting authorization concerns.
        //
        // Phase-18 AUTHZ-8: ORDER MATTERS. Pre-Phase-18 the super-admin
        // Gate::before fired FIRST and returned true for every ability,
        // which short-circuited the Phase-13 DPA-4 restriction check.
        // A DPA-restricted super-admin would NOT actually be restricted.
        // Post-Phase-18 the DPA-4 check fires first; super-admin bypass
        // is constrained to abilities NOT denied by DPA-4.

        // Phase-13 DPA-4: Article 18 restriction. A restricted user
        // (whatever their role) is read-only; deny any write-side
        // ability while restricted. The list of allowed abilities is
        // intentionally narrow — anything not on it is denied. Release
        // path goes through GdprController::releaseRestriction, which
        // doesn't need to pass a Gate (the user is acting on their
        // own record).
        Gate::before(function ($user, $ability) {
            if (! method_exists($user, 'isRestricted') || ! $user->isRestricted()) {
                return null;
            }

            $allowedWhileRestricted = [
                'view',
                'viewAny',
                'viewLedger',
                'export-data',
                'request-deletion',
                'access-admin',
                'view-security-logs',
                'view-audit-logs',
            ];

            if (in_array($ability, $allowedWhileRestricted, true)) {
                return null;
            }

            return false;
        });

        // Super-admin bypass. Applied AFTER the DPA-4 restriction check
        // above, so a restricted super-admin is denied write-side
        // abilities (the DPA-4 hook returns false first; this hook
        // never runs for those abilities).
        Gate::before(function ($user, $ability) {
            if ($user->isSuperAdmin()) {
                return true;
            }
        });

        // Gate for accessing admin panel
        Gate::define('access-admin', function ($user) {
            return $user->isSuperAdmin();
        });

        // Gate for impersonating users
        Gate::define('impersonate', function ($user, $target) {
            return $user->isSuperAdmin() && ! $target->isSuperAdmin();
        });

        // Gate for viewing security logs
        Gate::define('view-security-logs', function ($user) {
            return $user->isSuperAdmin();
        });

        // Gate for viewing audit logs
        Gate::define('view-audit-logs', function ($user) {
            return $user->isSuperAdmin() || $user->isLandlord();
        });

        // Phase-18 AUTHZ-1: removed 5 dead Gates that had zero call
        // sites across app/ + resources/. Re-adding any of these
        // requires a corresponding Gate::allows / $this->authorize()
        // call site — orphan Gates create a misleading 'authorization
        // is comprehensive' impression for security review.
        // Still dead post-Phase-19:
        //   manage-caretakers (covered by role:landlord middleware)
        //   generate-invoices (covered by role:landlord,caretaker middleware)
        //   perform-bulk-operations (covered by role:landlord + feature gate)
        //   access-reports (covered by role:landlord,caretaker + feature gate)

        // Phase-19 POLICY-5: manage-subscription resurrected. Wired into
        // SubscriptionController::index/plans/subscribe — replaces three
        // inline `$user->role !== 'landlord'` checks that bypassed the
        // Gate layer (and therefore bypassed Phase-13 DPA-4 restriction
        // enforcement; a restricted landlord could previously still
        // initiate a paid subscription). Landlord-only by design;
        // restricted landlord blocked by DPA-4 hook above.
        Gate::define('manage-subscription', function ($user) {
            return $user->isLandlord();
        });

        // Phase-19 POLICY-7 + Phase-20 AUTHZ-FRONT-8: every Sanctum
        // ability issued by AuthController::getAbilitiesForUser is
        // mirrored in the Gate registry so DPA-4 restriction
        // enforcement applies symmetrically. The Gate hook simply
        // forwards tokenCan() — super-admin handled by Gate::before
        // bypass above, restricted user denied by DPA-4 hook (none
        // of these abilities are on the read-side allow-list).
        Gate::define('integration:webhook', function ($user) {
            return $user->tokenCan('integration:webhook');
        });

        Gate::define('landlord:manage', function ($user) {
            return $user->tokenCan('landlord:manage');
        });

        Gate::define('tenant:read', function ($user) {
            return $user->tokenCan('tenant:read');
        });

        Gate::define('admin:all', function ($user) {
            return $user->tokenCan('admin:all');
        });

        // Gate for data export (GDPR)
        Gate::define('export-data', function ($user) {
            return true; // All users can export their own data
        });

        // Gate for requesting data deletion (GDPR)
        Gate::define('request-deletion', function ($user) {
            return true; // All users can request their data to be deleted
        });
    }
}
