<?php

namespace App\Providers;

use App\Models\Building;
use App\Models\DepositTransaction;
use App\Models\Document;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Invitation;
use App\Models\Invoice;
use App\Models\InvoiceTemplate;
use App\Models\LateFeePolicy;
use App\Models\Lease;
use App\Models\Payment;
use App\Models\Property;
use App\Models\ReceiptTemplate;
use App\Models\Ticket;
use App\Models\Unit;
use App\Models\Vendor;
use App\Models\WaterReading;
use App\Models\WaterSetting;
use App\Policies\BuildingPolicy;
use App\Policies\DepositTransactionPolicy;
use App\Policies\DocumentPolicy;
use App\Policies\ExpenseCategoryPolicy;
use App\Policies\ExpensePolicy;
use App\Policies\InvitationPolicy;
use App\Policies\InvoicePolicy;
use App\Policies\InvoiceTemplatePolicy;
use App\Policies\LateFeeRulePolicy;
use App\Policies\LeasePolicy;
use App\Policies\PaymentPolicy;
use App\Policies\PropertyPolicy;
use App\Policies\ReceiptTemplatePolicy;
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
        WaterSetting::class => WaterSettingPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();

        // Define Gates for cross-cutting authorization concerns

        // Super admin can do everything
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

        // Gate for managing caretakers
        Gate::define('manage-caretakers', function ($user) {
            return $user->isLandlord();
        });

        // Gate for generating invoices
        Gate::define('generate-invoices', function ($user) {
            return $user->isLandlord() || $user->isCaretaker();
        });

        // Gate for bulk operations
        Gate::define('perform-bulk-operations', function ($user) {
            return $user->isLandlord() && $user->canAccessFeature('bulk_operations');
        });

        // Gate for accessing reports
        Gate::define('access-reports', function ($user) {
            return ($user->isLandlord() || $user->isCaretaker())
                && $user->canAccessFeature('reports');
        });

        // Gate for managing subscriptions
        Gate::define('manage-subscription', function ($user) {
            return $user->isLandlord();
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
