<?php

declare(strict_types=1);

namespace Tests\Feature\Frontend;

use Tests\TestCase;

/**
 * Phase-51 CI-1: consolidated VUE-TAIL-1 surface watchdog.
 *
 * PHPUnit can't compile Vue, so this watchdog asserts file existence +
 * content presence of the token strings that prove the polish landed.
 * If a future refactor removes a file or drops the expected token, the
 * watchdog fails fast so reviewers see the regression before merge.
 *
 * NOT a substitute for real Vue tests — it catches drift, not behavior.
 */
class Phase51VueTailSurfaceTest extends TestCase
{
    // -- REPORTS-DRILL-UI --------------------------------------------------

    public function test_builder_vue_reads_drill_context_prop(): void
    {
        $source = $this->read('resources/js/Pages/Reports/Builder.vue');
        $this->assertStringContainsString('drillContext', $source);
        $this->assertStringContainsString('DrillContext', $source);
    }

    public function test_builder_vue_renders_parent_banner(): void
    {
        $source = $this->read('resources/js/Pages/Reports/Builder.vue');
        $this->assertStringContainsString('Drill-down from', $source);
        $this->assertStringContainsString('backToParent', $source);
    }

    public function test_builder_vue_wires_row_click_and_drill_field_highlight(): void
    {
        $source = $this->read('resources/js/Pages/Reports/Builder.vue');
        $this->assertStringContainsString('onRowClick', $source);
        $this->assertStringContainsString('drillFieldColumnKey', $source);
        $this->assertStringContainsString('loadReportForDrill', $source);
    }

    public function test_builder_controller_emits_drill_field_in_saved_reports(): void
    {
        $source = $this->read('app/Http/Controllers/Reports/BuilderController.php');
        $this->assertStringContainsString("'drill_field'", $source);
    }

    // -- SCHEDULED-PREVIEW-UX ----------------------------------------------

    public function test_scheduled_vue_uses_visibilitychange_listener(): void
    {
        $source = $this->read('resources/js/Pages/Reports/Scheduled.vue');
        $this->assertStringContainsString('visibilitychange', $source);
        $this->assertStringContainsString('pollPaused', $source);
        $this->assertStringContainsString('pollPauseCount', $source);
    }

    public function test_scheduled_vue_has_exponential_backoff_retry(): void
    {
        $source = $this->read('resources/js/Pages/Reports/Scheduled.vue');
        $this->assertStringContainsString('fetchWithBackoff', $source);
        $this->assertStringContainsString('[0, 1000, 2000, 4000]', $source);
    }

    public function test_scheduled_vue_has_click_to_sort(): void
    {
        $source = $this->read('resources/js/Pages/Reports/Scheduled.vue');
        $this->assertStringContainsString('sortedRows', $source);
        $this->assertStringContainsString('setSort', $source);
        $this->assertStringContainsString('sortKey', $source);
    }

    // -- TENANT-WIZARD-POLISH ----------------------------------------------

    public function test_caretaker_steps_renders_decline_form_with_char_counter(): void
    {
        $source = $this->read('resources/js/Pages/Onboarding/CaretakerSteps.vue');
        $this->assertStringContainsString('PendingAssignment', $source);
        $this->assertStringContainsString('MAX_DECLINE_REASON_LENGTH', $source);
        $this->assertStringContainsString('reasonLength', $source);
        $this->assertStringContainsString('isDeclined', $source);
    }

    public function test_tenant_steps_renders_payment_icons(): void
    {
        $source = $this->read('resources/js/Pages/Onboarding/TenantSteps.vue');
        $this->assertStringContainsString('mpesa', $source);
        $this->assertStringContainsString('bank', $source);
        $this->assertStringContainsString('<svg', $source);
    }

    public function test_tenant_steps_renders_kyc_progress(): void
    {
        $source = $this->read('resources/js/Pages/Onboarding/TenantSteps.vue');
        $this->assertStringContainsString('kycProgress', $source);
        $this->assertStringContainsString('KycProgress', $source);
        $this->assertStringContainsString('remaining_labels', $source);
    }

    public function test_onboarding_controller_injects_role_specific_props(): void
    {
        $source = $this->read('app/Http/Controllers/OnboardingController.php');
        $this->assertStringContainsString('pendingAssignments', $source);
        $this->assertStringContainsString('kycProgress', $source);
        $this->assertStringContainsString('CaretakerAssignment', $source);
    }

    // -- PHASE-46-WIZARD-STYLE ---------------------------------------------

    public function test_register_vue_uses_card_grid_role_picker(): void
    {
        $source = $this->read('resources/js/Pages/Auth/Register.vue');
        $this->assertStringContainsString("role=\"radiogroup\"", $source);
        $this->assertStringContainsString('from-indigo-100', $source);
        $this->assertStringContainsString("ring-2 ring-indigo-500", $source);
    }

    public function test_register_vue_role_help_card_is_branded(): void
    {
        $source = $this->read('resources/js/Pages/Auth/Register.vue');
        $this->assertStringContainsString('from-indigo-50 via-white to-purple-50 ring-1', $source);
    }

    public function test_onboarding_resume_mailable_uses_plain_text_view(): void
    {
        $source = $this->read('app/Mail/OnboardingResumeMailable.php');
        $this->assertStringContainsString("text: 'emails.onboarding.resume-text'", $source);
        $this->assertTrue(file_exists(base_path('resources/views/emails/onboarding/resume-text.blade.php')));
    }

    // -- LEASE-COUNTER-UI --------------------------------------------------

    public function test_lease_counter_components_exist(): void
    {
        $this->assertTrue(file_exists(base_path('resources/js/Components/LeaseCounter/CounterOfferStatusBadge.vue')));
        $this->assertTrue(file_exists(base_path('resources/js/Components/LeaseCounter/CounterOfferCountdown.vue')));
        $this->assertTrue(file_exists(base_path('resources/js/Components/LeaseCounter/CounterOfferHistory.vue')));
    }

    public function test_counter_offer_status_badge_handles_four_statuses(): void
    {
        $source = $this->read('resources/js/Components/LeaseCounter/CounterOfferStatusBadge.vue');
        $this->assertStringContainsString('counter_proposed', $source);
        $this->assertStringContainsString('accepted', $source);
        $this->assertStringContainsString('declined', $source);
        $this->assertStringContainsString('expired', $source);
    }

    public function test_counter_offer_countdown_ticks_every_minute(): void
    {
        $source = $this->read('resources/js/Components/LeaseCounter/CounterOfferCountdown.vue');
        $this->assertStringContainsString('60_000', $source);
        $this->assertStringContainsString('isExpired', $source);
        $this->assertStringContainsString('isUrgent', $source);
    }

    public function test_counter_offer_history_sorts_most_recent_first(): void
    {
        $source = $this->read('resources/js/Components/LeaseCounter/CounterOfferHistory.vue');
        $this->assertStringContainsString("Intl.NumberFormat('en-KE'", $source);
        $this->assertStringContainsString('Date.parse(b.created_at) - Date.parse(a.created_at)', $source);
    }

    // -- RUNBOOK + ALERT ---------------------------------------------------

    public function test_frontend_polish_runbook_exists(): void
    {
        $this->assertTrue(file_exists(base_path('docs/runbooks/frontend-polish.md')));
    }

    public function test_alert_thresholds_carries_vue_preview_poll_pause_count(): void
    {
        $md = file_get_contents(base_path('docs/runbooks/alert-thresholds.md'));
        $this->assertStringContainsString('vue_preview_poll_pause_count', $md);
    }

    // -- HELPER ------------------------------------------------------------

    private function read(string $relative): string
    {
        $path = base_path($relative);
        $this->assertTrue(file_exists($path), "Expected file to exist: {$relative}");

        return (string) file_get_contents($path);
    }
}
