<?php

namespace Tests\Feature\Controllers;

use App\Enums\KycSubmissionStatus;
use App\Models\KycRequirement;
use App\Models\TenantKycSubmission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

class TenantKycControllerTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    protected User $landlord;

    protected User $tenant;

    protected array $setupData;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $this->setupData = $this->createLandlordWithFullSetup();
        $this->landlord = $this->setupData['landlord'];

        $unit = $this->setupData['units']->first();
        ['tenant' => $this->tenant] = $this->createTenantWithActiveLease($this->landlord, $unit);
    }

    // ===== SHOW TESTS =====

    public function test_show_displays_dynamic_requirements_for_tenant(): void
    {
        $requirement = KycRequirement::factory()
            ->forLandlord($this->landlord)
            ->nationalId()
            ->required()
            ->create();

        $response = $this->actingAs($this->tenant)
            ->get(route('tenant.kyc.show'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Tenant/CompleteKyc')
            ->has('requirements', 1)
            ->where('requirements.0.id', $requirement->id)
            ->where('requirements.0.label', 'National ID')
        );
    }

    public function test_show_includes_existing_submissions(): void
    {
        $requirement = KycRequirement::factory()
            ->forLandlord($this->landlord)
            ->nationalId()
            ->create();

        TenantKycSubmission::factory()
            ->forTenant($this->tenant)
            ->forLandlord($this->landlord)
            ->forRequirement($requirement)
            ->pending()
            ->create();

        $response = $this->actingAs($this->tenant)
            ->get(route('tenant.kyc.show'));

        $response->assertInertia(fn ($page) => $page
            ->has('submissions', 1)
            ->where('submissions.0.requirement_id', $requirement->id)
            ->where('submissions.0.status', 'pending')
        );
    }

    public function test_show_prioritizes_building_specific_requirements(): void
    {
        $building = $this->setupData['building'];

        // Global requirement (should be overridden)
        KycRequirement::factory()
            ->platformDefault()
            ->selfie()
            ->required()
            ->create(['sort_order' => 1]);

        // Building-specific requirement (same type, should win)
        $buildingReq = KycRequirement::factory()
            ->forBuilding($building)
            ->selfie()
            ->optional()
            ->create(['label' => 'Building Selfie', 'sort_order' => 1]);

        $response = $this->actingAs($this->tenant)
            ->get(route('tenant.kyc.show'));

        $response->assertInertia(fn ($page) => $page
            ->has('requirements', 1)
            ->where('requirements.0.label', 'Building Selfie')
            ->where('requirements.0.is_required', false)
        );
    }

    public function test_show_includes_global_and_landlord_requirements(): void
    {
        // Global requirement
        KycRequirement::factory()
            ->platformDefault()
            ->selfie()
            ->create(['sort_order' => 1]);

        // Landlord-specific requirement (different type)
        KycRequirement::factory()
            ->forLandlord($this->landlord)
            ->nationalId()
            ->create(['sort_order' => 2]);

        $response = $this->actingAs($this->tenant)
            ->get(route('tenant.kyc.show'));

        $response->assertInertia(fn ($page) => $page
            ->has('requirements', 2)
        );
    }

    // ===== UPDATE TESTS =====

    public function test_tenant_can_submit_kyc_documents(): void
    {
        $requirement = KycRequirement::factory()
            ->forLandlord($this->landlord)
            ->nationalId()
            ->required()
            ->create();

        $file = UploadedFile::fake()->image('id.jpg', 800, 600);

        $response = $this->actingAs($this->tenant)
            ->post(route('tenant.kyc.update'), [
                'submissions' => [
                    [
                        'requirement_id' => $requirement->id,
                        'file' => $file,
                    ],
                ],
            ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $this->assertDatabaseHas('tenant_kyc_submissions', [
            'user_id' => $this->tenant->id,
            'requirement_id' => $requirement->id,
            'status' => 'pending',
        ]);

        $this->assertDatabaseHas('documents', [
            'uploaded_by' => $this->tenant->id,
            'document_type' => 'tenant_id',
        ]);
    }

    public function test_tenant_can_submit_multiple_documents(): void
    {
        $requirement1 = KycRequirement::factory()
            ->forLandlord($this->landlord)
            ->nationalId()
            ->required()
            ->create();

        $requirement2 = KycRequirement::factory()
            ->forLandlord($this->landlord)
            ->selfie()
            ->required()
            ->create();

        $file1 = UploadedFile::fake()->image('id.jpg', 800, 600);
        $file2 = UploadedFile::fake()->image('selfie.jpg', 600, 600);

        $response = $this->actingAs($this->tenant)
            ->post(route('tenant.kyc.update'), [
                'submissions' => [
                    [
                        'requirement_id' => $requirement1->id,
                        'file' => $file1,
                    ],
                    [
                        'requirement_id' => $requirement2->id,
                        'file' => $file2,
                    ],
                ],
            ]);

        $response->assertRedirect();
        $this->assertDatabaseCount('tenant_kyc_submissions', 2);
    }

    public function test_tenant_can_resubmit_rejected_document(): void
    {
        $requirement = KycRequirement::factory()
            ->forLandlord($this->landlord)
            ->nationalId()
            ->required()
            ->create();

        // Create existing rejected submission
        TenantKycSubmission::factory()
            ->forTenant($this->tenant)
            ->forLandlord($this->landlord)
            ->forRequirement($requirement)
            ->rejected()
            ->create(['rejection_reason' => 'Photo blurry']);

        $file = UploadedFile::fake()->image('id_v2.jpg', 800, 600);

        $response = $this->actingAs($this->tenant)
            ->post(route('tenant.kyc.update'), [
                'submissions' => [
                    [
                        'requirement_id' => $requirement->id,
                        'file' => $file,
                    ],
                ],
            ]);

        $response->assertRedirect();

        // Should update existing record, not create new
        $this->assertDatabaseCount('tenant_kyc_submissions', 1);
        $this->assertDatabaseHas('tenant_kyc_submissions', [
            'user_id' => $this->tenant->id,
            'requirement_id' => $requirement->id,
            'status' => 'pending',
            'rejection_reason' => null,
        ]);
    }

    public function test_update_fails_without_required_documents(): void
    {
        $required = KycRequirement::factory()
            ->forLandlord($this->landlord)
            ->nationalId()
            ->required()
            ->create();

        $optional = KycRequirement::factory()
            ->forLandlord($this->landlord)
            ->selfie()
            ->optional()
            ->create();

        $file = UploadedFile::fake()->image('selfie.jpg');

        $response = $this->actingAs($this->tenant)
            ->post(route('tenant.kyc.update'), [
                'submissions' => [
                    [
                        'requirement_id' => $optional->id,
                        'file' => $file,
                    ],
                ],
            ]);

        $response->assertSessionHasErrors();
    }

    public function test_update_validates_file_types(): void
    {
        $requirement = KycRequirement::factory()
            ->forLandlord($this->landlord)
            ->nationalId()
            ->required()
            ->create();

        $file = UploadedFile::fake()->create('document.exe', 100, 'application/x-msdownload');

        $response = $this->actingAs($this->tenant)
            ->post(route('tenant.kyc.update'), [
                'submissions' => [
                    [
                        'requirement_id' => $requirement->id,
                        'file' => $file,
                    ],
                ],
            ]);

        $response->assertSessionHasErrors('submissions.0.file');
    }

    public function test_update_validates_file_size(): void
    {
        $requirement = KycRequirement::factory()
            ->forLandlord($this->landlord)
            ->nationalId()
            ->required()
            ->create();

        // File over 10MB limit
        $file = UploadedFile::fake()->image('huge.jpg')->size(15000);

        $response = $this->actingAs($this->tenant)
            ->post(route('tenant.kyc.update'), [
                'submissions' => [
                    [
                        'requirement_id' => $requirement->id,
                        'file' => $file,
                    ],
                ],
            ]);

        $response->assertSessionHasErrors('submissions.0.file');
    }

    public function test_tenant_can_submit_text_value_instead_of_file(): void
    {
        $requirement = KycRequirement::factory()
            ->forLandlord($this->landlord)
            ->create([
                'requirement_type' => 'emergency_contact',
                'label' => 'Emergency Contact',
            ]);

        $response = $this->actingAs($this->tenant)
            ->post(route('tenant.kyc.update'), [
                'submissions' => [
                    [
                        'requirement_id' => $requirement->id,
                        'value' => 'John Doe - 0712345678',
                    ],
                ],
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('tenant_kyc_submissions', [
            'user_id' => $this->tenant->id,
            'requirement_id' => $requirement->id,
            'submission_value' => 'John Doe - 0712345678',
        ]);
    }

    // ===== REVIEW TESTS =====

    public function test_landlord_can_approve_submission(): void
    {
        $requirement = KycRequirement::factory()
            ->forLandlord($this->landlord)
            ->nationalId()
            ->create();

        $submission = TenantKycSubmission::factory()
            ->forTenant($this->tenant)
            ->forLandlord($this->landlord)
            ->forRequirement($requirement)
            ->pending()
            ->create();

        $response = $this->actingAs($this->landlord)
            ->post(route('kyc.review', $submission), [
                'status' => 'approved',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('tenant_kyc_submissions', [
            'id' => $submission->id,
            'status' => 'approved',
            'reviewed_by' => $this->landlord->id,
        ]);
    }

    public function test_landlord_can_reject_submission_with_reason(): void
    {
        $requirement = KycRequirement::factory()
            ->forLandlord($this->landlord)
            ->nationalId()
            ->create();

        $submission = TenantKycSubmission::factory()
            ->forTenant($this->tenant)
            ->forLandlord($this->landlord)
            ->forRequirement($requirement)
            ->pending()
            ->create();

        $response = $this->actingAs($this->landlord)
            ->post(route('kyc.review', $submission), [
                'status' => 'rejected',
                'rejection_reason' => 'Photo is blurry, please resubmit.',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('tenant_kyc_submissions', [
            'id' => $submission->id,
            'status' => 'rejected',
            'rejection_reason' => 'Photo is blurry, please resubmit.',
        ]);
    }

    public function test_rejection_requires_reason(): void
    {
        $requirement = KycRequirement::factory()
            ->forLandlord($this->landlord)
            ->nationalId()
            ->create();

        $submission = TenantKycSubmission::factory()
            ->forTenant($this->tenant)
            ->forLandlord($this->landlord)
            ->forRequirement($requirement)
            ->pending()
            ->create();

        $response = $this->actingAs($this->landlord)
            ->post(route('kyc.review', $submission), [
                'status' => 'rejected',
                // Missing rejection_reason
            ]);

        $response->assertSessionHasErrors('rejection_reason');
    }

    public function test_cannot_review_already_reviewed_submission(): void
    {
        $requirement = KycRequirement::factory()
            ->forLandlord($this->landlord)
            ->nationalId()
            ->create();

        $submission = TenantKycSubmission::factory()
            ->forTenant($this->tenant)
            ->forLandlord($this->landlord)
            ->forRequirement($requirement)
            ->approved()
            ->create([
                'reviewed_by' => $this->landlord->id,
                'reviewed_at' => now(),
            ]);

        $response = $this->actingAs($this->landlord)
            ->post(route('kyc.review', $submission), [
                'status' => 'rejected',
                'rejection_reason' => 'Changed my mind',
            ]);

        $response->assertForbidden();
    }

    public function test_caretaker_can_review_submissions_for_their_landlord(): void
    {
        $caretaker = $this->createCaretakerForLandlord($this->landlord);

        $requirement = KycRequirement::factory()
            ->forLandlord($this->landlord)
            ->nationalId()
            ->create();

        $submission = TenantKycSubmission::factory()
            ->forTenant($this->tenant)
            ->forLandlord($this->landlord)
            ->forRequirement($requirement)
            ->pending()
            ->create();

        $response = $this->actingAs($caretaker)
            ->post(route('kyc.review', $submission), [
                'status' => 'approved',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('tenant_kyc_submissions', [
            'id' => $submission->id,
            'status' => 'approved',
            'reviewed_by' => $caretaker->id,
        ]);
    }

    public function test_landlord_cannot_review_other_landlords_submissions(): void
    {
        /** @var User $otherLandlord */
        $otherLandlord = User::factory()->create(['role' => 'landlord']);

        $requirement = KycRequirement::factory()
            ->forLandlord($this->landlord)
            ->nationalId()
            ->create();

        $submission = TenantKycSubmission::factory()
            ->forTenant($this->tenant)
            ->forLandlord($this->landlord)
            ->forRequirement($requirement)
            ->pending()
            ->create();

        $response = $this->actingAs($otherLandlord)
            ->post(route('kyc.review', $submission), [
                'status' => 'approved',
            ]);

        $response->assertForbidden();
    }

    public function test_tenant_cannot_review_submissions(): void
    {
        $requirement = KycRequirement::factory()
            ->forLandlord($this->landlord)
            ->nationalId()
            ->create();

        $submission = TenantKycSubmission::factory()
            ->forTenant($this->tenant)
            ->forLandlord($this->landlord)
            ->forRequirement($requirement)
            ->pending()
            ->create();

        $response = $this->actingAs($this->tenant)
            ->post(route('kyc.review', $submission), [
                'status' => 'approved',
            ]);

        $response->assertForbidden();
    }

    // ===== PENDING REVIEWS LIST TESTS =====

    public function test_landlord_can_view_pending_reviews(): void
    {
        $requirement = KycRequirement::factory()
            ->forLandlord($this->landlord)
            ->nationalId()
            ->create();

        TenantKycSubmission::factory()
            ->forTenant($this->tenant)
            ->forLandlord($this->landlord)
            ->forRequirement($requirement)
            ->pending()
            ->create();

        $response = $this->actingAs($this->landlord)
            ->get(route('kyc.pending'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Kyc/PendingReviews')
            ->has('submissions.data', 1)
        );
    }

    public function test_pending_reviews_excludes_already_reviewed(): void
    {
        $requirement = KycRequirement::factory()
            ->forLandlord($this->landlord)
            ->nationalId()
            ->create();

        // Pending submission
        TenantKycSubmission::factory()
            ->forTenant($this->tenant)
            ->forLandlord($this->landlord)
            ->forRequirement($requirement)
            ->pending()
            ->create();

        // Approved submission (should not appear)
        $otherUnit = $this->setupData['units'][1];
        ['tenant' => $otherTenant] = $this->createTenantWithActiveLease($this->landlord, $otherUnit);

        TenantKycSubmission::factory()
            ->forTenant($otherTenant)
            ->forLandlord($this->landlord)
            ->forRequirement($requirement)
            ->approved()
            ->create();

        $response = $this->actingAs($this->landlord)
            ->get(route('kyc.pending'));

        $response->assertInertia(fn ($page) => $page
            ->has('submissions.data', 1)
        );
    }

    public function test_caretaker_can_view_pending_reviews_for_their_landlord(): void
    {
        $caretaker = $this->createCaretakerForLandlord($this->landlord);

        $requirement = KycRequirement::factory()
            ->forLandlord($this->landlord)
            ->nationalId()
            ->create();

        TenantKycSubmission::factory()
            ->forTenant($this->tenant)
            ->forLandlord($this->landlord)
            ->forRequirement($requirement)
            ->pending()
            ->create();

        $response = $this->actingAs($caretaker)
            ->get(route('kyc.pending'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('submissions.data', 1)
        );
    }

    // ===== BACKWARD COMPATIBILITY TESTS =====

    public function test_tenant_with_no_requirements_passes_kyc(): void
    {
        // No KYC requirements configured
        $this->assertTrue($this->tenant->hasCompletedKyc());
    }

    public function test_tenant_with_old_kyc_completed_at_still_works(): void
    {
        // Simulate tenant who completed KYC via old system
        $this->tenant->update(['kyc_completed_at' => now()->subMonth()]);

        // No requirements exist = should pass
        $this->assertTrue($this->tenant->fresh()->hasCompletedKyc());
    }

    public function test_existing_tenant_sees_new_requirements_when_added(): void
    {
        // Initially no requirements
        $this->assertTrue($this->tenant->hasCompletedKyc());

        // Landlord adds new requirement
        KycRequirement::factory()
            ->forLandlord($this->landlord)
            ->nationalId()
            ->required()
            ->create();

        // Now tenant needs to complete KYC
        $this->assertFalse($this->tenant->fresh()->hasCompletedKyc());
    }

    public function test_tenant_with_completed_kyc_can_access_dashboard(): void
    {
        $requirement = KycRequirement::factory()
            ->forLandlord($this->landlord)
            ->nationalId()
            ->required()
            ->create();

        // Create approved submission
        TenantKycSubmission::factory()
            ->forTenant($this->tenant)
            ->forLandlord($this->landlord)
            ->forRequirement($requirement)
            ->approved()
            ->create();

        // Tenant should now have completed KYC
        $this->assertTrue($this->tenant->fresh()->hasCompletedKyc());
    }

    public function test_resubmission_resets_status_to_pending(): void
    {
        $requirement = KycRequirement::factory()
            ->forLandlord($this->landlord)
            ->nationalId()
            ->required()
            ->create();

        // Create approved submission
        $submission = TenantKycSubmission::factory()
            ->forTenant($this->tenant)
            ->forLandlord($this->landlord)
            ->forRequirement($requirement)
            ->approved()
            ->create();

        // Resubmitting should reset status to pending
        $file = UploadedFile::fake()->image('updated.jpg');
        $response = $this->actingAs($this->tenant)
            ->post(route('tenant.kyc.update'), [
                'submissions' => [
                    [
                        'requirement_id' => $requirement->id,
                        'file' => $file,
                    ],
                ],
            ]);

        $response->assertRedirect();

        // Verify the submission is now pending (resubmission resets status)
        $updatedSubmission = TenantKycSubmission::where('user_id', $this->tenant->id)
            ->where('requirement_id', $requirement->id)
            ->first();

        $this->assertEquals(KycSubmissionStatus::Pending, $updatedSubmission->status);
        $this->assertFalse($this->tenant->fresh()->hasCompletedKyc());
    }
}
