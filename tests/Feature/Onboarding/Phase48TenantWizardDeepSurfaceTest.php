<?php

declare(strict_types=1);

namespace Tests\Feature\Onboarding;

use App\Enums\KycSubmissionStatus;
use App\Models\Building;
use App\Models\CaretakerAssignment;
use App\Models\KycRequirement;
use App\Models\NotificationPreference;
use App\Models\Property;
use App\Models\TenantKycSubmission;
use App\Models\TenantPaymentMethod;
use App\Models\User;
use App\Services\Caretaker\CaretakerAssignmentService;
use App\Services\Onboarding\CaretakerOnboardingService;
use App\Services\Onboarding\TenantOnboardingService;
use App\Services\Tenant\TenantPaymentMethodService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Phase-48 CI-1: consolidated TENANT-WIZARD-DEEP surface watchdog.
 */
class Phase48TenantWizardDeepSurfaceTest extends TestCase
{
    use RefreshDatabase;

    // -- TENANT-KYC-BRIDGE -------------------------------------------------

    public function test_user_kyc_progress_returns_satisfied_shape_for_non_tenants(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        $progress = $landlord->kycProgress();

        $this->assertSame(100, $progress['percent']);
        $this->assertSame(0, $progress['required']);
        $this->assertSame([], $progress['remaining_labels']);
    }

    public function test_user_kyc_progress_counts_required_submitted_approved_for_tenant(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        $tenant = User::factory()->create([
            'role' => 'tenant',
            'landlord_id' => $landlord->id,
        ]);

        $req1 = KycRequirement::create([
            'landlord_id' => $landlord->id,
            'requirement_type' => 'national_id',
            'label' => 'National ID',
            'is_required' => true,
            'is_active' => true,
        ]);
        $req2 = KycRequirement::create([
            'landlord_id' => $landlord->id,
            'requirement_type' => 'proof_of_income',
            'label' => 'Proof of income',
            'is_required' => true,
            'is_active' => true,
        ]);

        TenantKycSubmission::create([
            'user_id' => $tenant->id,
            'landlord_id' => $landlord->id,
            'requirement_id' => $req1->id,
            'submission_value' => '12345678',
            'status' => KycSubmissionStatus::Approved,
            'reviewed_at' => now(),
        ]);

        $progress = $tenant->kycProgress();

        $this->assertSame(2, $progress['required']);
        $this->assertSame(1, $progress['submitted']);
        $this->assertSame(1, $progress['approved']);
        $this->assertSame(50, $progress['percent']);
        $this->assertContains('Proof of income', $progress['remaining_labels']);
    }

    public function test_kyc_submission_saved_listener_invalidates_progress_cache(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        $tenant = User::factory()->create([
            'role' => 'tenant',
            'landlord_id' => $landlord->id,
        ]);

        $first = $tenant->kycProgress();
        $this->assertSame(0, $first['required']);

        // After requirement creation cache is still warm (5min); only a
        // submission saved invalidates. Force-invalidate by creating a
        // submission row + asserting the new progress reflects it.
        $req = KycRequirement::create([
            'landlord_id' => $landlord->id,
            'requirement_type' => 'national_id',
            'label' => 'National ID',
            'is_required' => true,
            'is_active' => true,
        ]);

        // Cache warm — still 0 required because cached.
        $cached = Cache::get("user:{$tenant->id}:kyc-progress");
        $this->assertNotNull($cached);

        TenantKycSubmission::create([
            'user_id' => $tenant->id,
            'landlord_id' => $landlord->id,
            'requirement_id' => $req->id,
            'submission_value' => '12345678',
            'status' => KycSubmissionStatus::Pending,
        ]);

        // Saved listener should have flushed the cache.
        $this->assertNull(Cache::get("user:{$tenant->id}:kyc-progress"));
        $fresh = $tenant->kycProgress();
        $this->assertSame(1, $fresh['required']);
        $this->assertSame(1, $fresh['submitted']);
    }

    public function test_process_kyc_gates_on_submitted_when_required_present(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        $tenant = User::factory()->create([
            'role' => 'tenant',
            'landlord_id' => $landlord->id,
        ]);
        KycRequirement::create([
            'landlord_id' => $landlord->id,
            'requirement_type' => 'national_id',
            'label' => 'National ID',
            'is_required' => true,
            'is_active' => true,
        ]);

        $service = app(TenantOnboardingService::class);
        $progress = $tenant->getOrCreateOnboardingProgress();

        // No submission yet → gate blocks.
        $this->assertFalse($service->processStep(2, ['acknowledged' => true], $tenant, $progress));
    }

    // -- TENANT-PAYMENT-METHOD ---------------------------------------------

    public function test_tenant_payment_methods_table_exists_with_encrypted_cast(): void
    {
        $this->assertTrue(Schema::hasTable('tenant_payment_methods'));

        $user = User::factory()->create(['role' => 'tenant']);
        $method = app(TenantPaymentMethodService::class)->store(
            $user,
            'mpesa',
            ['phone' => '0712345678'],
            true,
        );

        $this->assertSame('mpesa', $method->type);
        $this->assertSame(['phone' => '0712345678'], $method->details_encrypted);
        $this->assertTrue($method->is_default);

        // Stored value should be encrypted at rest — fetch raw column.
        $raw = \DB::table('tenant_payment_methods')->where('id', $method->id)->value('details_encrypted');
        $this->assertNotEquals(json_encode(['phone' => '0712345678']), $raw, 'details_encrypted must be encrypted at rest');
    }

    public function test_set_default_unsets_other_methods_of_same_type(): void
    {
        $user = User::factory()->create(['role' => 'tenant']);
        $service = app(TenantPaymentMethodService::class);

        $mpesa = $service->store($user, 'mpesa', ['phone' => '0711111111'], true);

        // Second store with a different type but is_default — should not
        // unset mpesa default.
        $bank = $service->store($user, 'bank', [
            'bank_name' => 'KCB',
            'account_number' => '12345',
            'account_name' => 'Test',
        ], true);

        $mpesa->refresh();
        $this->assertTrue($mpesa->is_default);
        $this->assertTrue($bank->is_default);
    }

    public function test_process_payment_method_persists_when_form_supplies_details(): void
    {
        $user = User::factory()->create(['role' => 'tenant']);
        $service = app(TenantOnboardingService::class);
        $progress = $user->getOrCreateOnboardingProgress();

        $service->processStep(3, [
            'type' => 'mpesa',
            'details' => ['phone' => '0712345678'],
            'is_default' => true,
        ], $user, $progress);

        $this->assertSame(1, TenantPaymentMethod::where('user_id', $user->id)->count());
    }

    public function test_process_payment_method_skips_when_no_details(): void
    {
        $user = User::factory()->create(['role' => 'tenant']);
        $service = app(TenantOnboardingService::class);
        $progress = $user->getOrCreateOnboardingProgress();

        $service->processStep(3, ['acknowledged' => true], $user, $progress);

        $this->assertSame(0, TenantPaymentMethod::where('user_id', $user->id)->count());
    }

    // -- CARETAKER-ASSIGNMENT-UX -------------------------------------------

    public function test_caretaker_assignments_table_exists(): void
    {
        $this->assertTrue(Schema::hasTable('caretaker_assignments'));
    }

    public function test_caretaker_assignment_service_record_accept_decline(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        $caretaker = User::factory()->create(['role' => 'caretaker', 'landlord_id' => $landlord->id]);
        $property = Property::create([
            'landlord_id' => $landlord->id,
            'name' => 'Test Estate',
            'type' => 'residential',
        ]);
        $building = Building::create([
            'property_id' => $property->id,
            'landlord_id' => $landlord->id,
            'name' => 'Block A',
            'total_floors' => 2,
            'units_per_floor' => 4,
        ]);

        $service = app(CaretakerAssignmentService::class);

        $assignment = $service->recordAssignment($caretaker, $building);
        $this->assertSame('pending', $assignment->status);
        $this->assertSame($caretaker->id, $building->fresh()->caretaker_id);

        $service->accept($assignment);
        $this->assertSame('accepted', $assignment->fresh()->status);

        $service->decline($assignment->fresh(), 'Too many buildings');
        $this->assertSame('declined', $assignment->fresh()->status);
        $this->assertNull($building->fresh()->caretaker_id);
    }

    public function test_process_building_assignment_ack_flips_pending_rows(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        $caretaker = User::factory()->create(['role' => 'caretaker', 'landlord_id' => $landlord->id]);
        $property = Property::create([
            'landlord_id' => $landlord->id,
            'name' => 'Test Estate',
            'type' => 'residential',
        ]);
        $b1 = Building::create(['property_id' => $property->id, 'landlord_id' => $landlord->id, 'name' => 'A', 'total_floors' => 1, 'units_per_floor' => 1]);
        $b2 = Building::create(['property_id' => $property->id, 'landlord_id' => $landlord->id, 'name' => 'B', 'total_floors' => 1, 'units_per_floor' => 1]);

        app(CaretakerAssignmentService::class)->recordAssignment($caretaker, $b1);
        app(CaretakerAssignmentService::class)->recordAssignment($caretaker, $b2);

        $service = app(CaretakerOnboardingService::class);
        $progress = $caretaker->getOrCreateOnboardingProgress();

        // Phase-77 CARETAKER-FLOW-1: building assignment is now step 3.
        $service->processStep(3, [
            'decline' => [$b2->id],
            'decline_reason' => [$b2->id => 'Outside my coverage'],
        ], $caretaker, $progress);

        $a1 = CaretakerAssignment::where('building_id', $b1->id)->first();
        $a2 = CaretakerAssignment::where('building_id', $b2->id)->first();
        $this->assertSame('accepted', $a1->status);
        $this->assertSame('declined', $a2->status);
        $this->assertSame('Outside my coverage', $a2->decision_reason);
    }

    // -- CARETAKER-NOTIF-PREFS ---------------------------------------------

    public function test_caretaker_notification_preferences_writes_per_type_columns(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        $caretaker = User::factory()->create(['role' => 'caretaker', 'landlord_id' => $landlord->id]);
        $service = app(CaretakerOnboardingService::class);
        $progress = $caretaker->getOrCreateOnboardingProgress();

        // Phase-77 CARETAKER-FLOW-1: notification preferences is now step 4.
        $service->processStep(4, [
            'email_enabled' => true,
            'maintenance_notice_enabled' => true,
            'general_enabled' => false,
        ], $caretaker, $progress);

        $pref = NotificationPreference::withoutGlobalScopes()
            ->where('user_id', $caretaker->id)
            ->first();
        $this->assertNotNull($pref);
        $this->assertTrue($pref->email_enabled);
        $this->assertTrue($pref->maintenance_notice_enabled);
        $this->assertFalse($pref->general_enabled);
    }

    public function test_caretaker_types_helper_returns_expected_list(): void
    {
        $types = NotificationPreference::caretakerTypes();
        $this->assertContains('maintenance_notice_enabled', $types);
        $this->assertContains('general_enabled', $types);
        $this->assertContains('caretaker_invitation_enabled', $types);
        $this->assertContains('tenant_invitation_enabled', $types);
        $this->assertContains('lease_expiry_enabled', $types);
    }

    // -- WIZARD-PROGRESS-UX ------------------------------------------------

    public function test_wizard_progress_bar_component_exists(): void
    {
        $this->assertTrue(file_exists(base_path('resources/js/Pages/Onboarding/Components/WizardProgressBar.vue')));
    }

    public function test_tenant_steps_imports_wizard_progress_bar(): void
    {
        $src = file_get_contents(base_path('resources/js/Pages/Onboarding/TenantSteps.vue'));
        $this->assertStringContainsString('WizardProgressBar', $src);
        $this->assertStringContainsString('form.errors', $src);
    }

    public function test_caretaker_steps_imports_wizard_progress_bar(): void
    {
        $src = file_get_contents(base_path('resources/js/Pages/Onboarding/CaretakerSteps.vue'));
        $this->assertStringContainsString('WizardProgressBar', $src);
        $this->assertStringContainsString('maintenance_notice_enabled', $src);
    }

    // -- RUNBOOK + ALERTS --------------------------------------------------

    public function test_runbook_has_phase_48_section(): void
    {
        $md = file_get_contents(base_path('docs/runbooks/onboarding.md'));
        $this->assertStringContainsString('Phase-48', $md);
    }

    public function test_alert_thresholds_has_tenant_kyc_blocked_row(): void
    {
        $md = file_get_contents(base_path('docs/runbooks/alert-thresholds.md'));
        $this->assertStringContainsString('tenant_kyc_blocked_count', $md);
    }
}
