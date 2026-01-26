<?php

namespace Tests\Unit\Models;

use App\Models\Building;
use App\Models\KycRequirement;
use App\Models\Property;
use App\Models\TenantKycSubmission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KycRequirementTest extends TestCase
{
    use RefreshDatabase;

    protected User $landlord;

    protected Building $building;

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
    }

    public function test_can_create_kyc_requirement(): void
    {
        $requirement = KycRequirement::create([
            'landlord_id' => $this->landlord->id,
            'requirement_type' => 'national_id',
            'label' => 'National ID',
            'description' => 'Upload your national ID',
            'is_required' => true,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->assertDatabaseHas('kyc_requirements', [
            'id' => $requirement->id,
            'requirement_type' => 'national_id',
        ]);
    }

    public function test_belongs_to_landlord(): void
    {
        $requirement = KycRequirement::create([
            'landlord_id' => $this->landlord->id,
            'requirement_type' => 'selfie',
            'label' => 'Profile Photo',
            'is_required' => true,
            'is_active' => true,
        ]);

        $this->assertInstanceOf(User::class, $requirement->landlord);
        $this->assertEquals($this->landlord->id, $requirement->landlord->id);
    }

    public function test_belongs_to_building(): void
    {
        $requirement = KycRequirement::create([
            'landlord_id' => $this->landlord->id,
            'building_id' => $this->building->id,
            'requirement_type' => 'signed_lease',
            'label' => 'Signed Lease',
            'is_required' => true,
            'is_active' => true,
        ]);

        $this->assertInstanceOf(Building::class, $requirement->building);
        $this->assertEquals($this->building->id, $requirement->building->id);
    }

    public function test_has_many_submissions(): void
    {
        $requirement = KycRequirement::create([
            'landlord_id' => $this->landlord->id,
            'requirement_type' => 'national_id',
            'label' => 'National ID',
            'is_required' => true,
            'is_active' => true,
        ]);

        $tenant = User::factory()->create([
            'role' => 'tenant',
            'landlord_id' => $this->landlord->id,
        ]);

        TenantKycSubmission::create([
            'user_id' => $tenant->id,
            'landlord_id' => $this->landlord->id,
            'requirement_id' => $requirement->id,
            'status' => 'pending',
        ]);

        $this->assertCount(1, $requirement->submissions);
    }

    public function test_scope_active_filters_active_requirements(): void
    {
        KycRequirement::create([
            'landlord_id' => $this->landlord->id,
            'requirement_type' => 'active_req',
            'label' => 'Active Requirement',
            'is_active' => true,
            'is_required' => true,
        ]);

        KycRequirement::create([
            'landlord_id' => $this->landlord->id,
            'requirement_type' => 'inactive_req',
            'label' => 'Inactive Requirement',
            'is_active' => false,
            'is_required' => true,
        ]);

        $activeRequirements = KycRequirement::active()->get();

        $this->assertCount(1, $activeRequirements);
        $this->assertEquals('active_req', $activeRequirements->first()->requirement_type);
    }

    public function test_scope_required_filters_required_requirements(): void
    {
        KycRequirement::create([
            'landlord_id' => $this->landlord->id,
            'requirement_type' => 'required_req',
            'label' => 'Required Requirement',
            'is_required' => true,
            'is_active' => true,
        ]);

        KycRequirement::create([
            'landlord_id' => $this->landlord->id,
            'requirement_type' => 'optional_req',
            'label' => 'Optional Requirement',
            'is_required' => false,
            'is_active' => true,
        ]);

        $requiredRequirements = KycRequirement::required()->get();

        $this->assertCount(1, $requiredRequirements);
        $this->assertEquals('required_req', $requiredRequirements->first()->requirement_type);
    }

    public function test_scope_global_filters_platform_defaults(): void
    {
        KycRequirement::create([
            'landlord_id' => null,
            'building_id' => null,
            'requirement_type' => 'global_req',
            'label' => 'Global Requirement',
            'is_required' => true,
            'is_active' => true,
        ]);

        KycRequirement::create([
            'landlord_id' => $this->landlord->id,
            'requirement_type' => 'landlord_req',
            'label' => 'Landlord Requirement',
            'is_required' => true,
            'is_active' => true,
        ]);

        $globalRequirements = KycRequirement::global()->get();

        $this->assertCount(1, $globalRequirements);
        $this->assertEquals('global_req', $globalRequirements->first()->requirement_type);
    }

    public function test_scope_for_building_includes_building_specific_and_global(): void
    {
        KycRequirement::create([
            'landlord_id' => $this->landlord->id,
            'building_id' => null,
            'requirement_type' => 'landlord_level',
            'label' => 'Landlord Level',
            'is_required' => true,
            'is_active' => true,
        ]);

        KycRequirement::create([
            'landlord_id' => $this->landlord->id,
            'building_id' => $this->building->id,
            'requirement_type' => 'building_specific',
            'label' => 'Building Specific',
            'is_required' => true,
            'is_active' => true,
        ]);

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
            'requirement_type' => 'other_building',
            'label' => 'Other Building',
            'is_required' => true,
            'is_active' => true,
        ]);

        $requirements = KycRequirement::forBuilding($this->building->id)->get();

        $this->assertCount(2, $requirements);
        $types = $requirements->pluck('requirement_type')->toArray();
        $this->assertContains('landlord_level', $types);
        $this->assertContains('building_specific', $types);
        $this->assertNotContains('other_building', $types);
    }

    public function test_soft_deletes_are_applied(): void
    {
        $requirement = KycRequirement::create([
            'landlord_id' => $this->landlord->id,
            'requirement_type' => 'to_delete',
            'label' => 'To Delete',
            'is_required' => true,
            'is_active' => true,
        ]);

        $requirement->delete();

        $this->assertSoftDeleted('kyc_requirements', ['id' => $requirement->id]);
        $this->assertNull(KycRequirement::find($requirement->id));
        $this->assertNotNull(KycRequirement::withTrashed()->find($requirement->id));
    }
}
