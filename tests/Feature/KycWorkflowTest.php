<?php

namespace Tests\Feature;

use App\Models\Building;
use App\Models\KycRequirement;
use App\Models\Lease;
use App\Models\Property;
use App\Models\TenantKycSubmission;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KycWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected User $landlord;

    protected User $tenant;

    protected Building $building;

    protected Lease $lease;

    protected function setUp(): void
    {
        parent::setUp();

        $this->landlord = User::factory()->create(['role' => 'landlord']);
        $property = Property::create([
            'name' => 'Test Property',
            'address' => '123 Test St',
            'type' => 'apartment',
            'landlord_id' => $this->landlord->id,
        ]);
        $this->building = Building::create([
            'property_id' => $property->id,
            'name' => 'Block A',
            'total_floors' => 1,
            'units_per_floor' => 1,
            'landlord_id' => $this->landlord->id,
            'building_type' => 'residential_apartment',
        ]);
        $unit = Unit::create([
            'building_id' => $this->building->id,
            'unit_number' => 'A101',
            'floor_number' => 1,
            'status' => 'occupied',
            'target_rent' => 25000,
            'landlord_id' => $this->landlord->id,
        ]);
        $this->tenant = User::factory()->create([
            'role' => 'tenant',
            'landlord_id' => $this->landlord->id,
        ]);
        $this->lease = Lease::create([
            'unit_id' => $unit->id,
            'tenant_id' => $this->tenant->id,
            'landlord_id' => $this->landlord->id,
            'rent_amount' => 25000,
            'deposit_amount' => 25000,
            'start_date' => now(),
            'is_active' => true,
            'wallet_balance' => 0,
        ]);
    }

    public function test_non_tenant_always_has_completed_kyc(): void
    {
        $this->assertTrue($this->landlord->hasCompletedKyc());

        $caretaker = User::factory()->create([
            'role' => 'caretaker',
            'landlord_id' => $this->landlord->id,
        ]);
        $this->assertTrue($caretaker->hasCompletedKyc());
    }

    public function test_tenant_with_no_requirements_has_completed_kyc(): void
    {
        $this->assertTrue($this->tenant->hasCompletedKyc());
    }

    public function test_tenant_with_pending_submissions_has_not_completed_kyc(): void
    {
        $requirement = KycRequirement::create([
            'landlord_id' => $this->landlord->id,
            'requirement_type' => 'national_id',
            'label' => 'National ID',
            'is_required' => true,
            'is_active' => true,
        ]);

        TenantKycSubmission::create([
            'user_id' => $this->tenant->id,
            'landlord_id' => $this->landlord->id,
            'requirement_id' => $requirement->id,
            'status' => 'pending',
        ]);

        $this->assertFalse($this->tenant->hasCompletedKyc());
    }

    public function test_tenant_with_all_approved_submissions_has_completed_kyc(): void
    {
        $requirement1 = KycRequirement::create([
            'landlord_id' => $this->landlord->id,
            'requirement_type' => 'national_id',
            'label' => 'National ID',
            'is_required' => true,
            'is_active' => true,
        ]);

        $requirement2 = KycRequirement::create([
            'landlord_id' => $this->landlord->id,
            'requirement_type' => 'selfie',
            'label' => 'Selfie',
            'is_required' => true,
            'is_active' => true,
        ]);

        TenantKycSubmission::create([
            'user_id' => $this->tenant->id,
            'landlord_id' => $this->landlord->id,
            'requirement_id' => $requirement1->id,
            'status' => 'approved',
            'reviewed_by' => $this->landlord->id,
            'reviewed_at' => now(),
        ]);

        TenantKycSubmission::create([
            'user_id' => $this->tenant->id,
            'landlord_id' => $this->landlord->id,
            'requirement_id' => $requirement2->id,
            'status' => 'approved',
            'reviewed_by' => $this->landlord->id,
            'reviewed_at' => now(),
        ]);

        $this->assertTrue($this->tenant->hasCompletedKyc());
    }

    public function test_tenant_with_rejected_submission_has_not_completed_kyc(): void
    {
        $requirement = KycRequirement::create([
            'landlord_id' => $this->landlord->id,
            'requirement_type' => 'national_id',
            'label' => 'National ID',
            'is_required' => true,
            'is_active' => true,
        ]);

        TenantKycSubmission::create([
            'user_id' => $this->tenant->id,
            'landlord_id' => $this->landlord->id,
            'requirement_id' => $requirement->id,
            'status' => 'rejected',
            'rejection_reason' => 'Photo is blurry',
            'reviewed_by' => $this->landlord->id,
            'reviewed_at' => now(),
        ]);

        $this->assertFalse($this->tenant->hasCompletedKyc());
    }

    public function test_optional_requirements_do_not_block_kyc_completion(): void
    {
        $requiredRequirement = KycRequirement::create([
            'landlord_id' => $this->landlord->id,
            'requirement_type' => 'national_id',
            'label' => 'National ID',
            'is_required' => true,
            'is_active' => true,
        ]);

        KycRequirement::create([
            'landlord_id' => $this->landlord->id,
            'requirement_type' => 'reference_letter',
            'label' => 'Reference Letter',
            'is_required' => false,
            'is_active' => true,
        ]);

        TenantKycSubmission::create([
            'user_id' => $this->tenant->id,
            'landlord_id' => $this->landlord->id,
            'requirement_id' => $requiredRequirement->id,
            'status' => 'approved',
            'reviewed_by' => $this->landlord->id,
            'reviewed_at' => now(),
        ]);

        $this->assertTrue($this->tenant->hasCompletedKyc());
    }

    public function test_inactive_requirements_do_not_block_kyc_completion(): void
    {
        KycRequirement::create([
            'landlord_id' => $this->landlord->id,
            'requirement_type' => 'national_id',
            'label' => 'National ID',
            'is_required' => true,
            'is_active' => false,
        ]);

        $this->assertTrue($this->tenant->hasCompletedKyc());
    }

    public function test_building_specific_requirements_are_included(): void
    {
        $buildingRequirement = KycRequirement::create([
            'landlord_id' => $this->landlord->id,
            'building_id' => $this->building->id,
            'requirement_type' => 'parking_permit',
            'label' => 'Parking Permit Application',
            'is_required' => true,
            'is_active' => true,
        ]);

        $this->assertFalse($this->tenant->hasCompletedKyc());

        TenantKycSubmission::create([
            'user_id' => $this->tenant->id,
            'landlord_id' => $this->landlord->id,
            'requirement_id' => $buildingRequirement->id,
            'status' => 'approved',
            'reviewed_by' => $this->landlord->id,
            'reviewed_at' => now(),
        ]);

        $this->assertTrue($this->tenant->fresh()->hasCompletedKyc());
    }

    public function test_other_building_requirements_do_not_affect_tenant(): void
    {
        $otherBuilding = Building::create([
            'property_id' => $this->building->property_id,
            'name' => 'Block B',
            'total_floors' => 1,
            'units_per_floor' => 1,
            'landlord_id' => $this->landlord->id,
            'building_type' => 'residential_apartment',
        ]);

        KycRequirement::create([
            'landlord_id' => $this->landlord->id,
            'building_id' => $otherBuilding->id,
            'requirement_type' => 'parking_permit',
            'label' => 'Parking Permit Application',
            'is_required' => true,
            'is_active' => true,
        ]);

        $this->assertTrue($this->tenant->hasCompletedKyc());
    }

    public function test_platform_default_requirements_apply_when_landlord_has_none(): void
    {
        KycRequirement::create([
            'landlord_id' => null,
            'building_id' => null,
            'requirement_type' => 'selfie',
            'label' => 'Profile Photo',
            'is_required' => true,
            'is_active' => true,
        ]);

        $this->assertFalse($this->tenant->hasCompletedKyc());
    }

    public function test_user_kyc_submissions_relationship(): void
    {
        $requirement = KycRequirement::create([
            'landlord_id' => $this->landlord->id,
            'requirement_type' => 'national_id',
            'label' => 'National ID',
            'is_required' => true,
            'is_active' => true,
        ]);

        TenantKycSubmission::create([
            'user_id' => $this->tenant->id,
            'landlord_id' => $this->landlord->id,
            'requirement_id' => $requirement->id,
            'status' => 'pending',
        ]);

        $this->assertCount(1, $this->tenant->kycSubmissions);
        $this->assertInstanceOf(TenantKycSubmission::class, $this->tenant->kycSubmissions->first());
    }

    public function test_building_kyc_requirements_relationship(): void
    {
        KycRequirement::create([
            'landlord_id' => $this->landlord->id,
            'building_id' => $this->building->id,
            'requirement_type' => 'parking_permit',
            'label' => 'Parking Permit',
            'is_required' => true,
            'is_active' => true,
        ]);

        $this->assertCount(1, $this->building->kycRequirements);
        $this->assertInstanceOf(KycRequirement::class, $this->building->kycRequirements->first());
    }
}
