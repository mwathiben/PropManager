<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers;

use App\Models\Building;
use App\Models\Lease;
use App\Models\Property;
use App\Models\TenantVerification;
use App\Models\Unit;
use App\Models\User;
use App\Models\VerificationItem;
use App\Models\VerificationTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Characterization guard for the verification *template* endpoints
 * (storeTemplate / updateTemplate / destroyTemplate). These methods had no
 * coverage; these tests lock their behaviour before VerificationController is
 * thinned by extracting the write bodies into VerificationService, so the
 * refactor can be proven behaviour-preserving.
 */
class VerificationTemplateControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $landlord;

    private Property $property;

    private Lease $lease;

    protected function setUp(): void
    {
        parent::setUp();

        $this->landlord = User::factory()->create(['role' => 'landlord']);
        $this->property = Property::factory()->create(['landlord_id' => $this->landlord->id]);

        $building = Building::factory()->create([
            'property_id' => $this->property->id,
            'landlord_id' => $this->landlord->id,
        ]);
        $unit = Unit::factory()->create([
            'building_id' => $building->id,
            'landlord_id' => $this->landlord->id,
        ]);
        $tenant = User::factory()->create([
            'role' => 'tenant',
            'landlord_id' => $this->landlord->id,
        ]);
        $this->lease = Lease::factory()->create([
            'unit_id' => $unit->id,
            'tenant_id' => $tenant->id,
            'landlord_id' => $this->landlord->id,
        ]);
    }

    // ==================== storeTemplate ====================

    public function test_landlord_can_store_template_with_items_and_sort_order(): void
    {
        $response = $this->actingAs($this->landlord)
            ->post(route('verifications.templates.store'), [
                'name' => 'Move-in Checklist',
                'property_id' => $this->property->id,
                'is_default' => false,
                'items' => [
                    ['name' => 'National ID', 'document_type' => 'tenant_id', 'is_required' => true],
                    ['name' => 'Payslip', 'document_type' => 'payslip', 'is_required' => false],
                ],
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('verification_templates', [
            'landlord_id' => $this->landlord->id,
            'name' => 'Move-in Checklist',
            'property_id' => $this->property->id,
            'is_default' => false,
        ]);

        $template = VerificationTemplate::where('landlord_id', $this->landlord->id)->firstOrFail();

        $this->assertDatabaseHas('verification_items', [
            'verification_template_id' => $template->id,
            'name' => 'National ID',
            'sort_order' => 0,
        ]);
        $this->assertDatabaseHas('verification_items', [
            'verification_template_id' => $template->id,
            'name' => 'Payslip',
            'sort_order' => 1,
        ]);
    }

    public function test_store_template_as_default_unsets_existing_default(): void
    {
        $existingDefault = VerificationTemplate::factory()->forLandlord($this->landlord)->default()->create();

        $this->actingAs($this->landlord)
            ->post(route('verifications.templates.store'), [
                'name' => 'New Default',
                'is_default' => true,
                'items' => [['name' => 'Doc', 'is_required' => true]],
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('verification_templates', [
            'id' => $existingDefault->id,
            'is_default' => false,
        ]);
        $this->assertDatabaseHas('verification_templates', [
            'name' => 'New Default',
            'is_default' => true,
        ]);
    }

    public function test_tenant_cannot_store_template_priv8(): void
    {
        $tenant = User::factory()->create([
            'role' => 'tenant',
            'landlord_id' => $this->landlord->id,
        ]);

        $this->actingAs($tenant)
            ->post(route('verifications.templates.store'), [
                'name' => 'Sneaky',
                'is_default' => false,
                'items' => [['name' => 'Doc', 'is_required' => true]],
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('verification_templates', ['name' => 'Sneaky']);
    }

    public function test_store_template_rejects_property_of_another_landlord(): void
    {
        $otherLandlord = User::factory()->create(['role' => 'landlord']);
        $otherProperty = Property::factory()->create(['landlord_id' => $otherLandlord->id]);

        $response = $this->actingAs($this->landlord)
            ->post(route('verifications.templates.store'), [
                'name' => 'Cross-tenant property',
                'property_id' => $otherProperty->id,
                'is_default' => false,
                'items' => [['name' => 'Doc', 'is_required' => true]],
            ]);

        $response->assertSessionHasErrors('property_id');
        $this->assertDatabaseMissing('verification_templates', ['name' => 'Cross-tenant property']);
    }

    public function test_guest_cannot_store_template(): void
    {
        $this->post(route('verifications.templates.store'), [
            'name' => 'Guest',
            'is_default' => false,
            'items' => [['name' => 'Doc', 'is_required' => true]],
        ])->assertRedirect(route('login'));

        $this->assertDatabaseMissing('verification_templates', ['name' => 'Guest']);
    }

    // ==================== updateTemplate ====================

    public function test_landlord_can_update_template_and_sync_items(): void
    {
        $template = VerificationTemplate::factory()->forLandlord($this->landlord)->create(['name' => 'Old name']);
        $keep = VerificationItem::factory()->forTemplate($template)->create(['name' => 'Keep me', 'sort_order' => 0]);
        $remove = VerificationItem::factory()->forTemplate($template)->create(['name' => 'Remove me', 'sort_order' => 1]);

        $this->actingAs($this->landlord)
            ->put(route('verifications.templates.update', $template), [
                'name' => 'New name',
                'is_default' => false,
                'items' => [
                    ['id' => $keep->id, 'name' => 'Kept renamed', 'is_required' => true],
                    ['name' => 'Brand new', 'is_required' => false],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('verification_templates', ['id' => $template->id, 'name' => 'New name']);

        // existing item updated by id, sort_order re-indexed
        $this->assertDatabaseHas('verification_items', [
            'id' => $keep->id,
            'name' => 'Kept renamed',
            'sort_order' => 0,
        ]);

        // removed item deleted
        $this->assertDatabaseMissing('verification_items', ['id' => $remove->id]);

        // new item created at next index
        $this->assertDatabaseHas('verification_items', [
            'verification_template_id' => $template->id,
            'name' => 'Brand new',
            'sort_order' => 1,
        ]);
    }

    public function test_update_template_set_default_unsets_siblings(): void
    {
        $sibling = VerificationTemplate::factory()->forLandlord($this->landlord)->default()->create();
        $template = VerificationTemplate::factory()->forLandlord($this->landlord)->notDefault()->create(['name' => 'Promote me']);

        $this->actingAs($this->landlord)
            ->put(route('verifications.templates.update', $template), [
                'name' => 'Promote me',
                'is_default' => true,
                'items' => [['name' => 'Doc', 'is_required' => true]],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('verification_templates', ['id' => $sibling->id, 'is_default' => false]);
        $this->assertDatabaseHas('verification_templates', ['id' => $template->id, 'is_default' => true]);
    }

    public function test_cross_tenant_user_cannot_update_template(): void
    {
        $otherLandlord = User::factory()->create(['role' => 'landlord']);
        $template = VerificationTemplate::factory()->forLandlord($otherLandlord)->create(['name' => 'Theirs']);

        $this->actingAs($this->landlord)
            ->put(route('verifications.templates.update', $template), [
                'name' => 'Hijacked',
                'is_default' => false,
                'items' => [['name' => 'Doc', 'is_required' => true]],
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('verification_templates', ['id' => $template->id, 'name' => 'Theirs']);
    }

    // ==================== destroyTemplate ====================

    public function test_landlord_can_destroy_unused_template(): void
    {
        $template = VerificationTemplate::factory()->forLandlord($this->landlord)->create();
        $item = VerificationItem::factory()->forTemplate($template)->create();

        $this->actingAs($this->landlord)
            ->delete(route('verifications.templates.destroy', $template))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('verification_templates', ['id' => $template->id]);
        $this->assertDatabaseMissing('verification_items', ['id' => $item->id]);
    }

    public function test_destroy_template_in_use_returns_error(): void
    {
        $template = VerificationTemplate::factory()->forLandlord($this->landlord)->create();
        $item = VerificationItem::factory()->forTemplate($template)->create();
        TenantVerification::factory()->forLease($this->lease)->forItem($item)->create();

        $this->actingAs($this->landlord)
            ->delete(route('verifications.templates.destroy', $template))
            ->assertRedirect()
            ->assertSessionHasErrors('template');

        $this->assertDatabaseHas('verification_templates', ['id' => $template->id]);
        $this->assertDatabaseHas('verification_items', ['id' => $item->id]);
    }

    public function test_cross_tenant_user_cannot_destroy_template(): void
    {
        $otherLandlord = User::factory()->create(['role' => 'landlord']);
        $template = VerificationTemplate::factory()->forLandlord($otherLandlord)->create();

        $this->actingAs($this->landlord)
            ->delete(route('verifications.templates.destroy', $template))
            ->assertForbidden();

        $this->assertDatabaseHas('verification_templates', ['id' => $template->id]);
    }
}
