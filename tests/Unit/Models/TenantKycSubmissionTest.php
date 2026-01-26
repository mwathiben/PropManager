<?php

namespace Tests\Unit\Models;

use App\Enums\KycSubmissionStatus;
use App\Models\Document;
use App\Models\KycRequirement;
use App\Models\TenantKycSubmission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantKycSubmissionTest extends TestCase
{
    use RefreshDatabase;

    protected User $landlord;

    protected User $tenant;

    protected KycRequirement $requirement;

    protected function setUp(): void
    {
        parent::setUp();

        $this->landlord = User::factory()->create(['role' => 'landlord']);
        $this->tenant = User::factory()->create([
            'role' => 'tenant',
            'landlord_id' => $this->landlord->id,
        ]);
        $this->requirement = KycRequirement::create([
            'landlord_id' => $this->landlord->id,
            'requirement_type' => 'national_id',
            'label' => 'National ID',
            'is_required' => true,
            'is_active' => true,
        ]);
    }

    public function test_can_create_submission(): void
    {
        $submission = TenantKycSubmission::create([
            'user_id' => $this->tenant->id,
            'landlord_id' => $this->landlord->id,
            'requirement_id' => $this->requirement->id,
            'status' => 'pending',
            'submitted_at' => now(),
        ]);

        $this->assertDatabaseHas('tenant_kyc_submissions', [
            'id' => $submission->id,
            'user_id' => $this->tenant->id,
        ]);
    }

    public function test_status_is_cast_to_enum(): void
    {
        $submission = TenantKycSubmission::create([
            'user_id' => $this->tenant->id,
            'landlord_id' => $this->landlord->id,
            'requirement_id' => $this->requirement->id,
            'status' => 'pending',
        ]);

        $this->assertInstanceOf(KycSubmissionStatus::class, $submission->status);
        $this->assertEquals(KycSubmissionStatus::Pending, $submission->status);
    }

    public function test_belongs_to_tenant(): void
    {
        $submission = TenantKycSubmission::create([
            'user_id' => $this->tenant->id,
            'landlord_id' => $this->landlord->id,
            'requirement_id' => $this->requirement->id,
            'status' => 'pending',
        ]);

        $this->assertInstanceOf(User::class, $submission->tenant);
        $this->assertEquals($this->tenant->id, $submission->tenant->id);
    }

    public function test_belongs_to_requirement(): void
    {
        $submission = TenantKycSubmission::create([
            'user_id' => $this->tenant->id,
            'landlord_id' => $this->landlord->id,
            'requirement_id' => $this->requirement->id,
            'status' => 'pending',
        ]);

        $this->assertInstanceOf(KycRequirement::class, $submission->requirement);
        $this->assertEquals($this->requirement->id, $submission->requirement->id);
    }

    public function test_belongs_to_document(): void
    {
        $document = Document::create([
            'landlord_id' => $this->landlord->id,
            'documentable_id' => $this->tenant->id,
            'documentable_type' => User::class,
            'title' => 'Test Document',
            'file_name' => 'test.pdf',
            'file_path' => 'documents/test.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 1024,
            'document_type' => 'tenant_id',
            'uploaded_by' => $this->tenant->id,
        ]);

        $submission = TenantKycSubmission::create([
            'user_id' => $this->tenant->id,
            'landlord_id' => $this->landlord->id,
            'requirement_id' => $this->requirement->id,
            'document_id' => $document->id,
            'status' => 'pending',
        ]);

        $this->assertInstanceOf(Document::class, $submission->document);
        $this->assertEquals($document->id, $submission->document->id);
    }

    public function test_belongs_to_reviewer(): void
    {
        $submission = TenantKycSubmission::create([
            'user_id' => $this->tenant->id,
            'landlord_id' => $this->landlord->id,
            'requirement_id' => $this->requirement->id,
            'status' => 'approved',
            'reviewed_by' => $this->landlord->id,
            'reviewed_at' => now(),
        ]);

        $this->assertInstanceOf(User::class, $submission->reviewer);
        $this->assertEquals($this->landlord->id, $submission->reviewer->id);
    }

    public function test_scope_pending_filters_pending_submissions(): void
    {
        TenantKycSubmission::create([
            'user_id' => $this->tenant->id,
            'landlord_id' => $this->landlord->id,
            'requirement_id' => $this->requirement->id,
            'status' => 'pending',
        ]);

        $anotherRequirement = KycRequirement::create([
            'landlord_id' => $this->landlord->id,
            'requirement_type' => 'selfie',
            'label' => 'Selfie',
            'is_required' => true,
            'is_active' => true,
        ]);

        TenantKycSubmission::create([
            'user_id' => $this->tenant->id,
            'landlord_id' => $this->landlord->id,
            'requirement_id' => $anotherRequirement->id,
            'status' => 'approved',
            'reviewed_by' => $this->landlord->id,
            'reviewed_at' => now(),
        ]);

        $pendingSubmissions = TenantKycSubmission::pending()->get();

        $this->assertCount(1, $pendingSubmissions);
        $this->assertEquals(KycSubmissionStatus::Pending, $pendingSubmissions->first()->status);
    }

    public function test_scope_approved_filters_approved_submissions(): void
    {
        TenantKycSubmission::create([
            'user_id' => $this->tenant->id,
            'landlord_id' => $this->landlord->id,
            'requirement_id' => $this->requirement->id,
            'status' => 'approved',
            'reviewed_by' => $this->landlord->id,
            'reviewed_at' => now(),
        ]);

        $approvedSubmissions = TenantKycSubmission::approved()->get();

        $this->assertCount(1, $approvedSubmissions);
        $this->assertEquals(KycSubmissionStatus::Approved, $approvedSubmissions->first()->status);
    }

    public function test_scope_rejected_filters_rejected_submissions(): void
    {
        TenantKycSubmission::create([
            'user_id' => $this->tenant->id,
            'landlord_id' => $this->landlord->id,
            'requirement_id' => $this->requirement->id,
            'status' => 'rejected',
            'rejection_reason' => 'Photo is blurry',
            'reviewed_by' => $this->landlord->id,
            'reviewed_at' => now(),
        ]);

        $rejectedSubmissions = TenantKycSubmission::rejected()->get();

        $this->assertCount(1, $rejectedSubmissions);
        $this->assertEquals(KycSubmissionStatus::Rejected, $rejectedSubmissions->first()->status);
    }

    public function test_timestamps_are_cast_to_datetime(): void
    {
        $submission = TenantKycSubmission::create([
            'user_id' => $this->tenant->id,
            'landlord_id' => $this->landlord->id,
            'requirement_id' => $this->requirement->id,
            'status' => 'approved',
            'reviewed_by' => $this->landlord->id,
            'reviewed_at' => '2026-01-26 10:00:00',
            'submitted_at' => '2026-01-25 09:00:00',
        ]);

        $this->assertInstanceOf(\Carbon\Carbon::class, $submission->reviewed_at);
        $this->assertInstanceOf(\Carbon\Carbon::class, $submission->submitted_at);
    }
}
