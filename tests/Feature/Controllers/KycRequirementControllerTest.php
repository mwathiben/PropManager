<?php

namespace Tests\Feature\Controllers;

use App\Models\Building;
use App\Models\KycRequirement;
use App\Models\Property;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KycRequirementControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $landlord;

    private User $otherLandlord;

    private User $caretaker;

    private User $tenant;

    private Building $building;

    protected function setUp(): void
    {
        parent::setUp();

        $this->landlord = User::factory()->create(['role' => 'landlord']);
        $this->otherLandlord = User::factory()->create(['role' => 'landlord']);
        $this->caretaker = User::factory()->create([
            'role' => 'caretaker',
            'landlord_id' => $this->landlord->id,
        ]);
        $this->tenant = User::factory()->create([
            'role' => 'tenant',
            'landlord_id' => $this->landlord->id,
        ]);

        $property = Property::factory()->create(['landlord_id' => $this->landlord->id]);
        $this->building = Building::factory()->create([
            'property_id' => $property->id,
            'landlord_id' => $this->landlord->id,
        ]);
    }

    public function test_landlord_can_view_kyc_requirements_index_page(): void
    {
        $response = $this->actingAs($this->landlord)
            ->get(route('settings.kyc.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Settings/KycRequirements')
            ->has('requirements')
            ->has('buildings')
        );
    }

    public function test_index_shows_platform_defaults_and_landlord_requirements(): void
    {
        // Create platform defaults
        KycRequirement::factory()->platformDefault()->create([
            'requirement_type' => 'selfie',
            'label' => 'Profile Photo',
        ]);

        // Create landlord's own requirements
        KycRequirement::factory()
            ->forLandlord($this->landlord)
            ->create([
                'requirement_type' => 'custom_doc',
                'label' => 'Custom Document',
            ]);

        // Create another landlord's requirement (should not be visible)
        KycRequirement::factory()
            ->forLandlord($this->otherLandlord)
            ->create(['requirement_type' => 'other_doc']);

        $response = $this->actingAs($this->landlord)
            ->get(route('settings.kyc.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('requirements.data', 2)
        );
    }

    public function test_caretaker_cannot_access_kyc_requirements_page(): void
    {
        $response = $this->actingAs($this->caretaker)
            ->get(route('settings.kyc.index'));

        $response->assertForbidden();
    }

    public function test_tenant_cannot_access_kyc_requirements_page(): void
    {
        $response = $this->actingAs($this->tenant)
            ->get(route('settings.kyc.index'));

        $response->assertForbidden();
    }

    public function test_landlord_can_create_requirement(): void
    {
        $response = $this->actingAs($this->landlord)
            ->post(route('kyc-requirements.store'), [
                'requirement_type' => 'proof_of_income',
                'label' => 'Proof of Income',
                'description' => 'Payslip or bank statement',
                'is_required' => true,
                'is_active' => true,
                'sort_order' => 10,
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('kyc_requirements', [
            'landlord_id' => $this->landlord->id,
            'building_id' => null,
            'requirement_type' => 'proof_of_income',
            'label' => 'Proof of Income',
        ]);
    }

    public function test_landlord_can_create_building_specific_requirement(): void
    {
        $response = $this->actingAs($this->landlord)
            ->post(route('kyc-requirements.store'), [
                'requirement_type' => 'building_rules_ack',
                'label' => 'Building Rules Acknowledgment',
                'description' => 'Signed building rules document',
                'building_id' => $this->building->id,
                'is_required' => true,
                'is_active' => true,
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('kyc_requirements', [
            'landlord_id' => $this->landlord->id,
            'building_id' => $this->building->id,
            'requirement_type' => 'building_rules_ack',
        ]);
    }

    public function test_landlord_cannot_create_duplicate_requirement_type_for_same_building(): void
    {
        // Create existing requirement
        KycRequirement::factory()
            ->forLandlord($this->landlord)
            ->create([
                'requirement_type' => 'proof_of_income',
                'building_id' => null,
            ]);

        // Try to create duplicate
        $response = $this->actingAs($this->landlord)
            ->post(route('kyc-requirements.store'), [
                'requirement_type' => 'proof_of_income',
                'label' => 'Another Income Proof',
                'building_id' => null,
                'is_required' => true,
                'is_active' => true,
            ]);

        $response->assertSessionHasErrors('requirement_type');
    }

    public function test_landlord_can_update_own_requirement(): void
    {
        $requirement = KycRequirement::factory()
            ->forLandlord($this->landlord)
            ->create([
                'requirement_type' => 'custom_doc',
                'label' => 'Original Label',
                'is_required' => true,
            ]);

        $response = $this->actingAs($this->landlord)
            ->put(route('kyc-requirements.update', $requirement), [
                'label' => 'Updated Label',
                'is_required' => false,
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('kyc_requirements', [
            'id' => $requirement->id,
            'label' => 'Updated Label',
            'is_required' => false,
        ]);
    }

    public function test_landlord_cannot_update_platform_default(): void
    {
        $platformDefault = KycRequirement::factory()
            ->platformDefault()
            ->create([
                'requirement_type' => 'selfie',
                'label' => 'Profile Photo',
            ]);

        $response = $this->actingAs($this->landlord)
            ->put(route('kyc-requirements.update', $platformDefault), [
                'label' => 'Hacked Label',
            ]);

        $response->assertForbidden();

        $this->assertDatabaseHas('kyc_requirements', [
            'id' => $platformDefault->id,
            'label' => 'Profile Photo',
        ]);
    }

    public function test_landlord_cannot_update_other_landlord_requirement(): void
    {
        $otherRequirement = KycRequirement::factory()
            ->forLandlord($this->otherLandlord)
            ->create([
                'requirement_type' => 'other_doc',
                'label' => 'Other Doc',
            ]);

        $response = $this->actingAs($this->landlord)
            ->put(route('kyc-requirements.update', $otherRequirement), [
                'label' => 'Stolen Label',
            ]);

        $response->assertForbidden();
    }

    public function test_landlord_can_delete_own_requirement(): void
    {
        $requirement = KycRequirement::factory()
            ->forLandlord($this->landlord)
            ->create(['requirement_type' => 'custom_doc']);

        $response = $this->actingAs($this->landlord)
            ->delete(route('kyc-requirements.destroy', $requirement));

        $response->assertRedirect();

        $this->assertSoftDeleted('kyc_requirements', [
            'id' => $requirement->id,
        ]);
    }

    public function test_landlord_cannot_delete_platform_default(): void
    {
        $platformDefault = KycRequirement::factory()
            ->platformDefault()
            ->create(['requirement_type' => 'selfie']);

        $response = $this->actingAs($this->landlord)
            ->delete(route('kyc-requirements.destroy', $platformDefault));

        $response->assertForbidden();

        $this->assertDatabaseHas('kyc_requirements', [
            'id' => $platformDefault->id,
            'deleted_at' => null,
        ]);
    }

    public function test_validation_errors_returned_for_invalid_data(): void
    {
        $response = $this->actingAs($this->landlord)
            ->post(route('kyc-requirements.store'), [
                'requirement_type' => '',
                'label' => '',
            ]);

        $response->assertSessionHasErrors(['requirement_type', 'label']);
    }

    public function test_caretaker_cannot_create_requirement(): void
    {
        $response = $this->actingAs($this->caretaker)
            ->post(route('kyc-requirements.store'), [
                'requirement_type' => 'caretaker_test',
                'label' => 'Caretaker Test',
            ]);

        $response->assertForbidden();

        $this->assertDatabaseMissing('kyc_requirements', [
            'requirement_type' => 'caretaker_test',
        ]);
    }

    public function test_toggle_required_endpoint(): void
    {
        $requirement = KycRequirement::factory()
            ->forLandlord($this->landlord)
            ->create([
                'requirement_type' => 'toggle_test',
                'is_required' => true,
            ]);

        $response = $this->actingAs($this->landlord)
            ->put(route('kyc-requirements.update', $requirement), [
                'is_required' => false,
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('kyc_requirements', [
            'id' => $requirement->id,
            'is_required' => false,
        ]);
    }

    public function test_toggle_active_endpoint(): void
    {
        $requirement = KycRequirement::factory()
            ->forLandlord($this->landlord)
            ->create([
                'requirement_type' => 'toggle_active_test',
                'is_active' => true,
            ]);

        $response = $this->actingAs($this->landlord)
            ->put(route('kyc-requirements.update', $requirement), [
                'is_active' => false,
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('kyc_requirements', [
            'id' => $requirement->id,
            'is_active' => false,
        ]);
    }

    public function test_negative_sort_order_rejected(): void
    {
        $response = $this->actingAs($this->landlord)
            ->post(route('kyc-requirements.store'), [
                'requirement_type' => 'negative_sort',
                'label' => 'Negative Sort Test',
                'is_required' => true,
                'sort_order' => -1,
            ]);

        $response->assertSessionHasErrors('sort_order');
    }

    public function test_partial_update_preserves_other_fields(): void
    {
        $requirement = KycRequirement::factory()
            ->forLandlord($this->landlord)
            ->create([
                'requirement_type' => 'preserve_test',
                'label' => 'Original Label',
                'description' => 'Original Description',
                'is_required' => true,
                'is_active' => true,
            ]);

        $response = $this->actingAs($this->landlord)
            ->put(route('kyc-requirements.update', $requirement), [
                'is_required' => false,
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('kyc_requirements', [
            'id' => $requirement->id,
            'label' => 'Original Label',
            'description' => 'Original Description',
            'is_required' => false,
            'is_active' => true,
        ]);
    }

    public function test_caretaker_cannot_view_landlords_kyc_requirements(): void
    {
        KycRequirement::factory()
            ->forLandlord($this->landlord)
            ->create([
                'requirement_type' => 'landlord_custom',
                'label' => 'Landlord Custom',
            ]);

        // Caretakers should be able to view their landlord's KYC requirements
        // (viewAny policy allows landlords, but caretakers still cannot as per
        // current policy. This test documents current behavior.)
        $response = $this->actingAs($this->caretaker)
            ->get(route('settings.kyc.index'));

        // Current policy forbids caretakers from viewing
        $response->assertForbidden();
    }
}
