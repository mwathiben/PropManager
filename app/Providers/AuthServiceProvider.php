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
use App\Models\Meter;
use App\Models\MoveOutDeductionCategory;
use App\Models\Payment;
use App\Models\Property;
use App\Models\ReceiptTemplate;
use App\Models\Refund;
use App\Models\SavedReport;
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
use App\Policies\MeterPolicy;
use App\Policies\MoveOutDeductionCategoryPolicy;
use App\Policies\PaymentPolicy;
use App\Policies\PropertyPolicy;
use App\Policies\ReceiptTemplatePolicy;
use App\Policies\RefundPolicy;
use App\Policies\SavedReportPolicy;
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
        Meter::class => MeterPolicy::class,
        \App\Models\WaterProductionCost::class => \App\Policies\WaterProductionCostPolicy::class,
        \App\Models\WaterConnection::class => \App\Policies\WaterConnectionPolicy::class,
        \App\Models\PropertyOwner::class => \App\Policies\PropertyOwnerPolicy::class,
        \App\Models\OwnerPayout::class => \App\Policies\OwnerPayoutPolicy::class,
        \App\Models\ManagementAgreement::class => \App\Policies\ManagementAgreementPolicy::class,
        \App\Models\AgreementSignature::class => \App\Policies\AgreementSignaturePolicy::class,
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
        // Phase-27 BI-BUILDER-1: only the owning landlord may view +
        // modify their saved reports.
        SavedReport::class => SavedReportPolicy::class,
        LandlordPayoutAccount::class => LandlordPayoutAccountPolicy::class,
        // PRIV-12: tenant-target authorization (User model represents
        // tenants in ledger/modalData/etc. contexts).
        User::class => TenantPolicy::class,
        // Phase-29 WF-PAY-APPROVE-1/2: landlord approval gates.
        \App\Models\PaymentPlan::class => \App\Policies\PaymentPlanPolicy::class,
        \App\Models\DepositRefundRequest::class => \App\Policies\DepositRefundRequestPolicy::class,
        // Phase-54 SLA-LANDLORD-UI-3: landlord-scoped SLA overrides.
        \App\Models\SlaDefinition::class => \App\Policies\SlaDefinitionPolicy::class,
        // Phase-63 INBOX-COMPOSE-1: landlord<->tenant message threads.
        \App\Models\MessageThread::class => \App\Policies\MessageThreadPolicy::class,
        \App\Models\Message::class => \App\Policies\MessagePolicy::class,
        // Phase-64 LEGAL-HOLD-3: court-ordered preservation directives.
        \App\Models\LegalHold::class => \App\Policies\LegalHoldPolicy::class,
        // Phase-72 MATTER-GROUPING: case-level grouping of holds.
        \App\Models\LegalMatter::class => \App\Policies\LegalMatterPolicy::class,
        // Phase-66 NPS-SURVEY-1: respondent owns their own NPS response.
        \App\Models\NpsResponse::class => \App\Policies\NpsResponsePolicy::class,
        // Phase-76 CREDIT-WALLET-3: landlord-owned credit notes.
        \App\Models\CreditNote::class => \App\Policies\CreditNotePolicy::class,
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
        $this->registerRestrictionGate();
        $this->registerSuperAdminGate();
        $this->registerAdminGates();
        $this->registerSanctumTokenGates();
        $this->registerManageGates();
    }

    /**
     * Phase-13 DPA-4: Article 18 restriction gate.
     *
     * A restricted user (whatever their role) is read-only; deny any
     * write-side ability while restricted. Release path goes through
     * GdprController::releaseRestriction.
     */
    private function registerRestrictionGate(): void
    {
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
    }

    /**
     * Super-admin bypass gate.
     *
     * Applied AFTER the DPA-4 restriction check, so a restricted
     * super-admin is still denied write-side abilities.
     */
    private function registerSuperAdminGate(): void
    {
        Gate::before(function ($user, $ability) {
            if ($user->isSuperAdmin()) {
                return true;
            }
        });
    }

    /**
     * Cross-cutting admin and GDPR gates.
     *
     * Phase-18 AUTHZ-1: every Gate here must have a matching call site.
     * Phase-19 POLICY-5: manage-subscription is landlord-only BY DESIGN —
     * hatched in ManagerAuthzGateTest::INTENTIONALLY_LANDLORD_ONLY and
     * pinned by ManagerProviderGatesTest.
     * Phase-25 API-DOC-2: viewApiDocs widened to scope owners (MANAGER-AUTHZ-2).
     */
    private function registerAdminGates(): void
    {
        Gate::define('access-admin', fn ($user) => $user->isSuperAdmin());

        Gate::define('impersonate', fn ($user, $target) => $user->isSuperAdmin() && ! $target->isSuperAdmin());

        Gate::define('view-security-logs', fn ($user) => $user->isSuperAdmin());

        // MANAGER-AUTHZ-2: managers see their own scope's audit trail.
        Gate::define('view-audit-logs', fn ($user) => $user->isSuperAdmin() || $user->isScopeOwner());

        Gate::define('manage-subscription', fn ($user) => $user->isLandlord());

        Gate::define('viewApiDocs', fn ($user) => $user->isScopeOwner() || $user->isSuperAdmin());

        // GDPR rights — available to all authenticated users.
        Gate::define('export-data', fn ($user) => true);
        Gate::define('request-deletion', fn ($user) => true);
    }

    /**
     * Phase-19 POLICY-7 + Phase-20 AUTHZ-FRONT-8: Sanctum token ability gates.
     *
     * Every ability issued by AuthController::getAbilitiesForUser is mirrored
     * here so DPA-4 restriction enforcement applies symmetrically.
     */
    private function registerSanctumTokenGates(): void
    {
        Gate::define('integration:webhook', fn ($user) => $user->tokenCan('integration:webhook'));
        Gate::define('landlord:manage', fn ($user) => $user->tokenCan('landlord:manage'));
        Gate::define('tenant:read', fn ($user) => $user->tokenCan('tenant:read'));
        Gate::define('admin:all', fn ($user) => $user->tokenCan('admin:all'));
    }

    /**
     * Phase-21 DEFER-AUTHZ-1: resource management gates.
     *
     * Each gate represents "this user role can perform management operations
     * on this resource class". UI consumes via useAuth().can('tenants:manage').
     * Per-record authorization is still resolved at the Policy layer.
     *
     * MANAGER-AUTHZ-2: resolver is isScopeOwner() (landlord + manager), not
     * isLandlord() — a manager is a full scope owner. The || isCaretaker()
     * grant is unchanged for operational roles.
     */
    private function registerManageGates(): void
    {
        $scopeOwnerOrCaretaker = fn ($user) => $user->isScopeOwner() || $user->isCaretaker();
        $scopeOwnerOnly = fn ($user) => $user->isScopeOwner();

        $manageGates = [
            'tenants:manage' => $scopeOwnerOrCaretaker,
            'invoices:manage' => $scopeOwnerOrCaretaker,
            'payments:manage' => $scopeOwnerOrCaretaker,
            'documents:manage' => $scopeOwnerOrCaretaker,
            'properties:manage' => $scopeOwnerOnly,
            'buildings:manage' => $scopeOwnerOnly,
            'units:manage' => $scopeOwnerOnly,
            'settings:manage' => $scopeOwnerOnly,
            'team:manage' => $scopeOwnerOnly,
            'templates:manage' => $scopeOwnerOnly,
            'finances:manage' => $scopeOwnerOnly,
            'imports:manage' => $scopeOwnerOnly,
        ];

        foreach ($manageGates as $ability => $resolver) {
            Gate::define($ability, $resolver);
        }
    }
}
