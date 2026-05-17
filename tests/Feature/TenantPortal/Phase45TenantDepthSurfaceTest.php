<?php

declare(strict_types=1);

namespace Tests\Feature\TenantPortal;

use App\Models\Document;
use App\Models\EmergencyContact;
use App\Models\LeaseRenewal;
use App\Models\PaymentPlan;
use App\Models\PaymentPlanModification;
use App\Models\TenantStatementPreference;
use App\Services\Sms\Contracts\SmsDriver;
use App\Services\Sms\SmsOtpService;
use App\Services\Sms\StubSmsDriver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Phase-45 CI-1: consolidated tenant-depth surface watchdog.
 *
 * Per-finding tests cover the individual surfaces; this is the
 * integration assertion that all Phase-45 invariants hold together.
 * Same pattern as Phase24CiTest / Phase44I18nRtlSurfaceTest.
 */
class Phase45TenantDepthSurfaceTest extends TestCase
{
    use RefreshDatabase;

    // -- STATEMENT-DEPTH -----------------------------------------------------

    public function test_tenant_statement_controller_accepts_new_period_modes(): void
    {
        $src = file_get_contents(app_path('Http/Controllers/TenantStatementController.php'));
        foreach (['calendar_year', 'last_12_months', 'custom'] as $period) {
            $this->assertStringContainsString("'{$period}'", $src, "CI-1: TenantStatementController must support '{$period}' period.");
        }
    }

    public function test_tenant_statement_preferences_table_present(): void
    {
        $this->assertTrue(Schema::hasTable('tenant_statement_preferences'));
        $this->assertTrue(Schema::hasColumn('tenant_statement_preferences', 'user_id'));
        $this->assertTrue(Schema::hasColumn('tenant_statement_preferences', 'columns'));
        $this->assertNotEmpty(TenantStatementPreference::ALLOWED_COLUMNS);
    }

    public function test_xlsx_export_service_exposes_multi_sheet(): void
    {
        $src = file_get_contents(app_path('Services/Reports/XlsxExportService.php'));
        $this->assertStringContainsString('writeMultiSheet', $src, 'CI-1: XlsxExportService must expose writeMultiSheet.');
    }

    // -- TICKET-PHOTOS -------------------------------------------------------

    public function test_document_annotation_columns_and_relationships_present(): void
    {
        $this->assertTrue(Schema::hasColumn('documents', 'annotates_document_id'));
        $this->assertTrue(Schema::hasColumn('documents', 'annotation_data'));

        $document = new Document;
        $this->assertTrue(method_exists($document, 'annotates'));
        $this->assertTrue(method_exists($document, 'annotations'));
        $this->assertTrue(method_exists($document, 'isAnnotation'));
    }

    public function test_ticket_photo_annotator_component_exists(): void
    {
        $path = resource_path('js/Components/TicketPhotoAnnotator.vue');
        $this->assertFileExists($path, 'CI-1: TicketPhotoAnnotator.vue must exist.');

        $contents = file_get_contents($path);
        $this->assertStringContainsString("'pen'", $contents);
        $this->assertStringContainsString("'rect'", $contents);
        $this->assertStringContainsString("'arrow'", $contents);
        $this->assertStringContainsString('toDataURL', $contents);
    }

    // -- LEASE-COUNTER -------------------------------------------------------

    public function test_lease_renewal_status_includes_counter_proposed(): void
    {
        $this->assertSame('counter_proposed', LeaseRenewal::STATUS_COUNTER_PROPOSED);
        $this->assertContains('counter_proposed', LeaseRenewal::OPEN_STATUSES);
        $this->assertTrue(Schema::hasColumn('lease_renewals', 'counter_rent_amount_cents'));
        $this->assertTrue(Schema::hasColumn('lease_renewals', 'counter_end_date'));
        $this->assertTrue(Schema::hasColumn('lease_renewals', 'counter_submitted_at'));
    }

    public function test_lease_renewal_counter_history_table_present(): void
    {
        $this->assertTrue(Schema::hasTable('lease_renewal_counter_history'));
        $this->assertTrue(Schema::hasColumn('lease_renewal_counter_history', 'action'));
        $this->assertTrue(Schema::hasColumn('lease_renewal_counter_history', 'actor_user_id'));
    }

    public function test_lease_counter_expiry_cron_registered(): void
    {
        $src = file_get_contents(base_path('routes/console.php'));
        $this->assertStringContainsString(
            "Schedule::command('lease-renewal:expire-stale-counters')",
            $src,
            'CI-1: lease-renewal:expire-stale-counters cron must be scheduled.',
        );
        $this->assertSame(14, LeaseRenewal::COUNTER_EXPIRY_DAYS);
    }

    // -- PAY-PLAN-MOD --------------------------------------------------------

    public function test_payment_plan_status_includes_modified_pending(): void
    {
        $this->assertSame('modified_pending', PaymentPlan::STATUS_MODIFIED_PENDING);
        $this->assertContains('modified_pending', PaymentPlan::STATUSES);
    }

    public function test_payment_plan_modifications_table_present(): void
    {
        $this->assertTrue(Schema::hasTable('payment_plan_modifications'));
        foreach (['payment_plan_id', 'requested_by_user_id', 'original_installments', 'proposed_installments', 'status', 'decided_at', 'decided_by_user_id'] as $col) {
            $this->assertTrue(Schema::hasColumn('payment_plan_modifications', $col), "CI-1: payment_plan_modifications.{$col} must exist.");
        }
    }

    public function test_payment_plan_modifications_status_enum_has_three_values(): void
    {
        $this->assertSame('pending', PaymentPlanModification::STATUS_PENDING);
        $this->assertSame('approved', PaymentPlanModification::STATUS_APPROVED);
        $this->assertSame('rejected', PaymentPlanModification::STATUS_REJECTED);
    }

    public function test_audit_stale_modifications_cron_registered(): void
    {
        $src = file_get_contents(base_path('routes/console.php'));
        $this->assertStringContainsString(
            "Schedule::command('payment-plans:audit-stale-modifications')",
            $src,
            'CI-1: payment-plans:audit-stale-modifications cron must be scheduled.',
        );
    }

    // -- EMERGENCY-CONTACT-SMS -----------------------------------------------

    public function test_sms_driver_default_binding_is_stub(): void
    {
        $this->assertInstanceOf(StubSmsDriver::class, $this->app->make(SmsDriver::class));
    }

    public function test_sms_otp_service_class_exists(): void
    {
        $this->assertTrue(class_exists(SmsOtpService::class));
        $this->assertSame(10, SmsOtpService::OTP_TTL_MINUTES);
    }

    public function test_emergency_contacts_verification_columns_present(): void
    {
        $this->assertTrue(Schema::hasColumn('emergency_contacts', 'verified_at'));
        $this->assertTrue(Schema::hasColumn('emergency_contacts', 'verification_attempts_24h'));
        $this->assertTrue(Schema::hasColumn('emergency_contacts', 'last_otp_sent_at'));
        $this->assertTrue(method_exists(new EmergencyContact, 'isVerified'));
    }

    public function test_emergency_contact_saving_listener_registered_in_booted(): void
    {
        $src = file_get_contents(app_path('Models/EmergencyContact.php'));
        $this->assertStringContainsString('static::saving', $src);
        $this->assertStringContainsString('static::saved', $src);
        $this->assertStringContainsString('emergency_contact_name', $src);
        $this->assertStringContainsString('emergency_contact_phone', $src);
    }

    // -- CI ------------------------------------------------------------------

    public function test_alert_thresholds_md_has_phase_45_rows(): void
    {
        $src = file_get_contents(base_path('docs/runbooks/alert-thresholds.md'));

        $this->assertStringContainsString(
            'lease_renewal_counter_expired_count',
            $src,
            'CI-3: alert-thresholds.md must carry lease_renewal_counter_expired_count row.',
        );
        $this->assertStringContainsString(
            'payment_plan_modification_pending_24h',
            $src,
            'CI-3: alert-thresholds.md must carry payment_plan_modification_pending_24h row.',
        );
    }
}
