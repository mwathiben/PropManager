<?php

namespace Tests\Feature\Controllers;

use App\Models\InvoiceSetting;
use App\Models\InvoiceTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceTemplateControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $landlord;

    protected User $otherLandlord;

    protected function setUp(): void
    {
        parent::setUp();
        $this->landlord = User::factory()->create(['role' => 'landlord']);
        $this->otherLandlord = User::factory()->create(['role' => 'landlord']);

        InvoiceSetting::create([
            'landlord_id' => $this->landlord->id,
            'business_name' => 'Test Business',
            'invoice_prefix' => 'INV',
            'invoice_next_number' => 1,
        ]);
    }

    public function test_landlord_can_view_template_index(): void
    {
        InvoiceTemplate::create([
            'landlord_id' => $this->landlord->id,
            'name' => 'Test Template',
            'design' => 'classic',
            'is_default' => true,
        ]);

        $response = $this->actingAs($this->landlord)
            ->get(route('invoice-templates.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('InvoiceTemplates/Index')
            ->has('templates', 1)
            ->has('designOptions')
        );
    }

    public function test_landlord_can_view_create_form(): void
    {
        $response = $this->actingAs($this->landlord)
            ->get(route('invoice-templates.create'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('InvoiceTemplates/Edit')
            ->where('template', null)
            ->has('designOptions')
            ->has('sampleInvoice')
        );
    }

    public function test_landlord_can_create_template(): void
    {
        $response = $this->actingAs($this->landlord)
            ->post(route('invoice-templates.store'), [
                'name' => 'My Template',
                'design' => 'modern',
                'is_default' => true,
                'show_logo' => true,
                'show_tax_number' => true,
                'show_tenant_id' => false,
                'show_unit_details' => true,
                'show_lease_reference' => true,
                'show_due_date' => true,
                'show_late_warning' => true,
                'show_bank_details' => true,
                'show_footer' => true,
                'show_qr_code' => false,
                'show_payment_instructions' => true,
                'show_arrears_breakdown' => true,
                'show_water_details' => true,
                'primary_color' => '#4F46E5',
                'secondary_color' => '#6366F1',
            ]);

        $response->assertRedirect(route('invoice-templates.index'));

        $this->assertDatabaseHas('invoice_templates', [
            'landlord_id' => $this->landlord->id,
            'name' => 'My Template',
            'design' => 'modern',
            'is_default' => true,
        ]);
    }

    public function test_landlord_can_view_edit_form(): void
    {
        $template = InvoiceTemplate::create([
            'landlord_id' => $this->landlord->id,
            'name' => 'Test Template',
            'design' => 'classic',
            'is_default' => true,
        ]);

        $response = $this->actingAs($this->landlord)
            ->get(route('invoice-templates.edit', $template));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('InvoiceTemplates/Edit')
            ->where('template.id', $template->id)
            ->has('designOptions')
            ->has('sampleInvoice')
        );
    }

    public function test_landlord_can_update_template(): void
    {
        $template = InvoiceTemplate::create([
            'landlord_id' => $this->landlord->id,
            'name' => 'Test Template',
            'design' => 'classic',
            'is_default' => true,
        ]);

        $response = $this->actingAs($this->landlord)
            ->put(route('invoice-templates.update', $template), [
                'name' => 'Updated Template',
                'design' => 'professional',
                'is_default' => true,
                'show_logo' => false,
                'show_tax_number' => true,
                'show_tenant_id' => true,
                'show_unit_details' => true,
                'show_lease_reference' => true,
                'show_due_date' => true,
                'show_late_warning' => false,
                'show_bank_details' => true,
                'show_footer' => true,
                'show_qr_code' => true,
                'show_payment_instructions' => true,
                'show_arrears_breakdown' => true,
                'show_water_details' => true,
            ]);

        $response->assertRedirect(route('invoice-templates.index'));

        $this->assertDatabaseHas('invoice_templates', [
            'id' => $template->id,
            'name' => 'Updated Template',
            'design' => 'professional',
            'show_logo' => false,
            'show_qr_code' => true,
        ]);
    }

    public function test_landlord_can_delete_non_default_template(): void
    {
        $defaultTemplate = InvoiceTemplate::create([
            'landlord_id' => $this->landlord->id,
            'name' => 'Default Template',
            'design' => 'classic',
            'is_default' => true,
        ]);

        $otherTemplate = InvoiceTemplate::create([
            'landlord_id' => $this->landlord->id,
            'name' => 'Other Template',
            'design' => 'modern',
            'is_default' => false,
        ]);

        $response = $this->actingAs($this->landlord)
            ->delete(route('invoice-templates.destroy', $otherTemplate));

        $response->assertRedirect(route('invoice-templates.index'));

        $this->assertDatabaseMissing('invoice_templates', [
            'id' => $otherTemplate->id,
        ]);
    }

    public function test_landlord_cannot_delete_default_template(): void
    {
        $template = InvoiceTemplate::create([
            'landlord_id' => $this->landlord->id,
            'name' => 'Default Template',
            'design' => 'classic',
            'is_default' => true,
        ]);

        $response = $this->actingAs($this->landlord)
            ->delete(route('invoice-templates.destroy', $template));

        $response->assertRedirect();
        $response->assertSessionHas('error');

        $this->assertDatabaseHas('invoice_templates', [
            'id' => $template->id,
        ]);
    }

    public function test_landlord_can_set_default_template(): void
    {
        $template1 = InvoiceTemplate::create([
            'landlord_id' => $this->landlord->id,
            'name' => 'Template 1',
            'design' => 'classic',
            'is_default' => true,
        ]);

        $template2 = InvoiceTemplate::create([
            'landlord_id' => $this->landlord->id,
            'name' => 'Template 2',
            'design' => 'modern',
            'is_default' => false,
        ]);

        $response = $this->actingAs($this->landlord)
            ->post(route('invoice-templates.set-default', $template2));

        $response->assertRedirect();

        $this->assertDatabaseHas('invoice_templates', [
            'id' => $template1->id,
            'is_default' => false,
        ]);

        $this->assertDatabaseHas('invoice_templates', [
            'id' => $template2->id,
            'is_default' => true,
        ]);
    }

    public function test_landlord_cannot_access_other_landlord_template(): void
    {
        $template = InvoiceTemplate::create([
            'landlord_id' => $this->otherLandlord->id,
            'name' => 'Other Template',
            'design' => 'classic',
            'is_default' => true,
        ]);

        $response = $this->actingAs($this->landlord)
            ->get(route('invoice-templates.edit', $template));

        $response->assertForbidden();
    }

    public function test_landlord_cannot_update_other_landlord_template(): void
    {
        $template = InvoiceTemplate::create([
            'landlord_id' => $this->otherLandlord->id,
            'name' => 'Other Template',
            'design' => 'classic',
            'is_default' => true,
        ]);

        $response = $this->actingAs($this->landlord)
            ->put(route('invoice-templates.update', $template), [
                'name' => 'Hacked Template',
                'design' => 'modern',
                'is_default' => true,
                'show_logo' => true,
                'show_tax_number' => true,
                'show_tenant_id' => true,
                'show_unit_details' => true,
                'show_lease_reference' => true,
                'show_due_date' => true,
                'show_late_warning' => true,
                'show_bank_details' => true,
                'show_footer' => true,
                'show_qr_code' => true,
                'show_payment_instructions' => true,
                'show_arrears_breakdown' => true,
                'show_water_details' => true,
            ]);

        $response->assertForbidden();

        $this->assertDatabaseHas('invoice_templates', [
            'id' => $template->id,
            'name' => 'Other Template',
        ]);
    }

    public function test_landlord_cannot_delete_other_landlord_template(): void
    {
        $template = InvoiceTemplate::create([
            'landlord_id' => $this->otherLandlord->id,
            'name' => 'Other Template',
            'design' => 'classic',
            'is_default' => false,
        ]);

        $response = $this->actingAs($this->landlord)
            ->delete(route('invoice-templates.destroy', $template));

        $response->assertForbidden();

        $this->assertDatabaseHas('invoice_templates', [
            'id' => $template->id,
        ]);
    }

    public function test_tenant_cannot_access_template_index(): void
    {
        $tenant = User::factory()->create([
            'role' => 'tenant',
            'landlord_id' => $this->landlord->id,
        ]);

        $response = $this->actingAs($tenant)
            ->get(route('invoice-templates.index'));

        $response->assertForbidden();
    }

    public function test_template_validates_design_option(): void
    {
        $response = $this->actingAs($this->landlord)
            ->post(route('invoice-templates.store'), [
                'name' => 'Invalid Template',
                'design' => 'invalid_design',
                'is_default' => true,
                'show_logo' => true,
                'show_tax_number' => true,
                'show_tenant_id' => true,
                'show_unit_details' => true,
                'show_lease_reference' => true,
                'show_due_date' => true,
                'show_late_warning' => true,
                'show_bank_details' => true,
                'show_footer' => true,
                'show_qr_code' => true,
                'show_payment_instructions' => true,
                'show_arrears_breakdown' => true,
                'show_water_details' => true,
            ]);

        $response->assertSessionHasErrors('design');
    }

    public function test_template_requires_name(): void
    {
        $response = $this->actingAs($this->landlord)
            ->post(route('invoice-templates.store'), [
                'name' => '',
                'design' => 'classic',
                'is_default' => true,
                'show_logo' => true,
                'show_tax_number' => true,
                'show_tenant_id' => true,
                'show_unit_details' => true,
                'show_lease_reference' => true,
                'show_due_date' => true,
                'show_late_warning' => true,
                'show_bank_details' => true,
                'show_footer' => true,
                'show_qr_code' => true,
                'show_payment_instructions' => true,
                'show_arrears_breakdown' => true,
                'show_water_details' => true,
            ]);

        $response->assertSessionHasErrors('name');
    }
}
