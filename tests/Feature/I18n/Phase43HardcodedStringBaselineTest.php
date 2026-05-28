<?php

declare(strict_types=1);

namespace Tests\Feature\I18n;

use App\Support\HardcodedEnglishScanner;
use Tests\TestCase;

/**
 * Phase-43 LANG-COVERAGE-2: shrink-only baseline ratcheting the
 * count of hardcoded English text nodes inside Vue `<template>`
 * blocks. The Phase-22 PERF-NPLUS1-1 NPlusOneBaseline pattern —
 * existing literals are technical debt to migrate incrementally,
 * new code must use $t().
 *
 * To migrate a chunk: wrap the literals in $t(), ratchet this
 * baseline downward, commit. Never raise the baseline.
 */
class Phase43HardcodedStringBaselineTest extends TestCase
{
    /**
     * Baseline of hardcoded English text-node lines under resources/js/.
     *
     * - 2026-05-17: initial 3263.
     * - 2026-05-24: recalibrated to 2078 after (a) fixing a scanner
     *   false-positive — line-leading `class="..."` Tailwind attributes
     *   survived stripNoise's `\s`-anchored attribute strip and were
     *   mis-counted as English prose (~1400 of them), and (b) migrating
     *   TenantInvitations/Index.vue to $t().
     * - 2026-05-24: lowered to 1778 after migrating six high-traffic
     *   screens (Onboarding, Finances Settings/Expenses tabs,
     *   PaymentMethods, Tenants/Show, Leases/Create) to $t().
     * - 2026-05-25: lowered to 1581 after migrating six more screens
     *   (Buildings/Dashboard, Tenant/Dashboard, the landlord Dashboard,
     *   Finances BulkImport, MoveOuts/Show, Settings/Privacy) to $t().
     * - 2026-05-25: lowered to 1425 after migrating six more screens
     *   (Settings/TwoFactor, Finances LateFee/Overview/Reports tabs,
     *   Notifications SetupWizard, Reports/Index) to $t().
     * - 2026-05-25: lowered to 1282 after migrating six more screens
     *   (Subscription/Index, Finances/TemplatesTab, Tickets/Show,
     *   Verifications/Templates, Operations/NotificationsTab,
     *   TenantInvitations/Accept) to $t().
     * - 2026-05-25: lowered to 1158 after migrating six more screens
     *   (Tickets/Create, Imports/Index, Readings/Review, NotificationBell,
     *   Settings/IntegrationsTab, Tenants/Index) to $t().
     * - 2026-05-25: lowered to 1048 after migrating six more screens
     *   (Invitations/Index, MoveOuts/Create, Notifications Overview/Scheduled
     *   tabs, Profile/NotificationsTab, Finances/DepositsTab) to $t().
     * - 2026-05-25: lowered to 949 after migrating six more screens
     *   (Onboarding/TenantSteps, Subscription/Plans, Verifications/Conduct,
     *   Caretaker/Tickets, Settings/BrandingTab, Tenant/Lease) to $t().
     * - 2026-05-25: lowered to 860 after migrating six more screens
     *   (Finances PaymentDetail/Refund/InvoiceDetail modals, Imports/Show,
     *   Profile/VerificationTab, Settings/NotificationsTab) to $t().
     * - 2026-05-25: lowered to 782 after migrating six more screens
     *   (PushNotificationPrompt, Admin/Settings, BulkOps/LeaseManagementTab,
     *   Finances RefundDeposit modal + Payments/Record, Invitations/Accept) to $t().
     * - 2026-05-25: lowered to 707 after migrating six more screens
     *   (Notifications/TemplatesTab, Profile DangerZone/Security tabs,
     *   Leases/Index, Maintenance/TicketsTab, Onboarding/CaretakerSteps) to $t().
     * - 2026-05-25: lowered to 639 after migrating six more screens
     *   (Profile/BusinessProfileTab, Settings/TwoFactorRecoveryCodes,
     *   ActivityLogs/Index, BulkOps Index + RentAdjustmentTab,
     *   Finances/RecordPaymentModal) to $t().
     * - 2026-05-25: lowered to 576 after migrating six more screens
     *   (Finances/Refunds/Create, Help/Index, Settings/SecurityTab,
     *   AddWingModal, TenantProfile/OverviewTab, Documents/Index) to $t().
     * - 2026-05-25: lowered to 518 after migrating six more screens
     *   (Finances/MatchPaymentModal, Inbox/Index, Notifications/HistoryTab,
     *   Profile/DeleteUserForm, Finances/FilterBar, BulkSendNotificationModal) to $t().
     * - 2026-05-25: lowered to 465 after migrating six more screens
     *   (BulkOps/TargetRentTab, PaymentLink/Show, Profile/PersonalInfoTab,
     *   Settings/TwoFactorSetup, Tenant/Notifications, Consent/Required) to $t().
     * - 2026-05-25: lowered to 420 after migrating six more screens
     *   (Finances/ForfeitDepositModal, MoveOuts/Index, Readings/History,
     *   EvictionNoticeModal, MassHikeModal, Offline/ConflictDialog) to $t().
     * - 2026-05-25: lowered to 378 after migrating six more screens
     *   (TenantProfile/LeaseFinancesTab, Archive/LeasesTab, Finances
     *   SendReminders+Arrears, Operations Imports+Team tabs) to $t().
     * - 2026-05-25: lowered to 337 after migrating six more screens
     *   (Profile/UpdateProfileInformationForm, Readings/Index, Tenants/History,
     *   Tickets/Index, Water/ReadingsTab, Finances/ExportDropdown) to $t().
     * - 2026-05-25: lowered to 301 after migrating six more screens
     *   (Admin/Landlords, Archive/ActivityTab, Finances/ReconciliationTab,
     *   Inbox/Show, Invitations/AcceptExisting, Notifications/SettingsTab) to $t().
     * - 2026-05-25: lowered to 267 after migrating six more screens
     *   (Offline, Operations/InboxTab, Settings/Index, Tenants/tabs/HistoryTab,
     *   ConnectionStatus, TenantProfile/HistoryTab) to $t().
     * - 2026-05-25: lowered to 237 after migrating six more screens
     *   (Admin/Users, Buildings/Edit, BulkOps/UnitStatusTab,
     *   Profile/UpdatePasswordForm, TenantFinances/History, Tenants/tabs/DirectoryTab) to $t().
     * - 2026-05-26: lowered to 211 after migrating six more screens
     *   (Tenants/tabs/MoveOutsTab, Water/tabs/HistoryTab, CursorPagination,
     *   FinancialSummaryCard, MetricCard, Modal) to $t().
     * - 2026-05-26: lowered to 187 after migrating six more screens
     *   (UploadDocumentModal, SlideOutPanel, Tenant/DocumentExpiryBanner,
     *   TenantProfile/NotesContactsTab, Finances/PaymentsTab,
     *   Operations/BulkTab) to $t().
     * - 2026-05-26: lowered to 166 after migrating six more screens
     *   (Tenants/OnboardingTab, Tenants/PaymentVerificationsTab,
     *   Tenants/VerificationsTab, BuildingMap, Inbox/MessageBubble,
     *   Modals/SendNotificationModal) to $t().
     * - 2026-05-27: lowered to 148 after migrating six more screens
     *   (Modals/TenantProfileModal, TicketFeedbackForm, TimeFilter,
     *   UnitFilters, ApiTokens/Index, Auth/Register) to $t().
     * - 2026-05-28: lowered to 130 after migrating six more screens
     *   (Buildings/Index, Buildings/Show, Errors/403,
     *   Finances/InvoicesTab, MoveOutCategories/Index,
     *   Notifications/Index) to $t().
     * - 2026-05-28: lowered to 112 after migrating six more screens
     *   (PaymentVerifications/Index, Settings/KycRequirements,
     *   Settings/partials/BusinessProfileTab,
     *   Settings/partials/PaymentMethodsTab — scanner-FP-only,
     *   Tenant/Inbox/Index, TenantFinances/Pay) to $t().
     * - 2026-05-28: lowered to 100 after migrating six more components
     *   (ActionItemCard/Dropdown/FormSubmitButton/IconButton — slot-only,
     *   no lang stubs; InvitationBanner; LeaseCounter/CounterOfferHistory)
     *   to $t().
     * - 2026-05-28: lowered to 88 after migrating six more components
     *   (LegalHold/HoldCreateModal, Modals/AddBuildingModal,
     *   Offline/PhotoUploadStatusList, Water/WaterSettingsForm,
     *   Layouts/AuthenticatedLayout — FPs only, no new keys,
     *   Admin/AuditLogs) to $t().
     * - 2026-05-28: lowered to 76 after migrating six more screens
     *   (Admin/Dashboard, CreditNotes/Create, CreditNotes/Index,
     *   Dashboards/Show, Errors/404, Finances/Index) to $t().
     * - 2026-05-28: lowered to 64 after migrating six more screens
     *   (Finances/RefundsTab, Help/Show, Invoices/Show,
     *   InvoiceSettings/Edit, MessageThreads/Index, Ops/Onboarding/Funnel)
     *   to $t().
     * - 2026-05-28: lowered to 52 after migrating six more screens
     *   (PaymentLink/Invalid, Profile/Edit, Settings/PayoutAccounts,
     *   Tenant/CompleteKyc, Tenant/PaymentRequired, TenantFinances/Index)
     *   to $t().
     * - 2026-05-28: lowered to 44 after migrating six more components
     *   (VendorPortal/Sla [namespace rename + orphan vendor_portal.sla
     *   block trimmed], Water/tabs/ReviewTab, Dashboard/ChartCard,
     *   Dashboard/KpiCard, DropdownLink [slot-only], Finances/DataTable)
     *   to $t().
     * - 2026-05-28: lowered to 38 after migrating six more components
     *   (Finances/PaymentMethodSelector [slot-only], Finances/VirtualDataTable
     *   [reuses finances_data_table; same DataTable withDefaults fix pattern],
     *   Growth/FunnelSankey, Insight/UsageRatioCard, Pagination [scanner FP
     *   only], TenantProfile/DocumentsTab) to $t().
     *   Lowering the constant requires the scanner to confirm the new floor.
     */
    private const BASELINE = 38;

    public function test_hardcoded_english_count_does_not_grow_beyond_baseline(): void
    {
        $scanner = new HardcodedEnglishScanner;
        $result = $scanner->scan(resource_path('js'));

        $this->assertLessThanOrEqual(
            self::BASELINE,
            $result['count'],
            sprintf(
                "Hardcoded English count grew above the baseline of %d (saw %d).\n".
                "Wrap new text in \$t() OR migrate an existing literal and ratchet the baseline down.\n".
                'Top offenders: %s',
                self::BASELINE,
                $result['count'],
                $this->formatTopOffenders($result['files']),
            ),
        );
    }

    public function test_scanner_recognises_unwrapped_english(): void
    {
        $scanner = new HardcodedEnglishScanner;
        $template = '<template><p>Please confirm your password.</p></template>';
        $this->assertSame(1, $scanner->scanContents($template));
    }

    public function test_scanner_ignores_wrapped_t_call(): void
    {
        $scanner = new HardcodedEnglishScanner;
        $template = '<template><p>{{ $t("auth.login.title") }}</p></template>';
        $this->assertSame(0, $scanner->scanContents($template));
    }

    public function test_scanner_ignores_i18n_ignore_comment(): void
    {
        $scanner = new HardcodedEnglishScanner;
        $template = '<template><p><!-- i18n-ignore -->Brand name PropManager</p></template>';
        $this->assertSame(0, $scanner->scanContents($template));
    }

    /**
     * A Tailwind class attribute that *leads* a wrapped attribute line is not
     * English prose — stripNoise must drop it even without preceding whitespace.
     * Before this fix the `\s`-anchored strip missed line-leading attributes and
     * counted ~1400 class strings as violations.
     */
    public function test_scanner_ignores_line_leading_class_attribute(): void
    {
        $scanner = new HardcodedEnglishScanner;
        $template = "<template>\n<button\nclass=\"inline-flex items-center bg-indigo-600 text-white rounded-lg\"\n>{{ t('a.b') }}</button>\n</template>";
        $this->assertSame(0, $scanner->scanContents($template));
    }

    /**
     * @param  array<string, int>  $files
     */
    private function formatTopOffenders(array $files): string
    {
        arsort($files);
        $top = array_slice($files, 0, 5, true);
        $lines = [];
        foreach ($top as $file => $count) {
            $lines[] = "  {$count}  {$file}";
        }

        return PHP_EOL.implode(PHP_EOL, $lines);
    }
}
