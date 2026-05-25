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
     *   Lowering the constant requires the scanner to confirm the new floor.
     */
    private const BASELINE = 782;

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
