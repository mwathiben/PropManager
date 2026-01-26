<?php

namespace Database\Factories;

use App\Models\InvoiceTemplate;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class InvoiceTemplateFactory extends Factory
{
    protected $model = InvoiceTemplate::class;

    public function definition(): array
    {
        return [
            'landlord_id' => User::factory()->state(['role' => 'landlord']),
            'name' => fake()->randomElement(['Standard', 'Professional', 'Detailed', 'Compact']).' Template',
            'design' => InvoiceTemplate::DESIGN_CLASSIC,
            'is_default' => false,
            'show_logo' => true,
            'show_tax_number' => false,
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
            'custom_header' => null,
            'custom_footer' => null,
            'primary_color' => '#059669',
            'secondary_color' => '#6b7280',
        ];
    }

    public function default(): static
    {
        return $this->state(['is_default' => true]);
    }

    public function notDefault(): static
    {
        return $this->state(['is_default' => false]);
    }

    public function classic(): static
    {
        return $this->state(['design' => InvoiceTemplate::DESIGN_CLASSIC]);
    }

    public function modern(): static
    {
        return $this->state(['design' => InvoiceTemplate::DESIGN_MODERN]);
    }

    public function minimal(): static
    {
        return $this->state(['design' => InvoiceTemplate::DESIGN_MINIMAL]);
    }

    public function professional(): static
    {
        return $this->state(['design' => InvoiceTemplate::DESIGN_PROFESSIONAL]);
    }

    public function withAllElements(): static
    {
        return $this->state([
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
    }

    public function minimal_elements(): static
    {
        return $this->state([
            'show_logo' => true,
            'show_tax_number' => false,
            'show_tenant_id' => false,
            'show_unit_details' => false,
            'show_lease_reference' => false,
            'show_due_date' => true,
            'show_late_warning' => false,
            'show_bank_details' => false,
            'show_footer' => false,
            'show_qr_code' => false,
            'show_payment_instructions' => false,
            'show_arrears_breakdown' => false,
            'show_water_details' => false,
        ]);
    }

    public function withCustomContent(): static
    {
        return $this->state([
            'custom_header' => fake()->sentence(),
            'custom_footer' => 'Thank you for your business. '.fake()->sentence(),
        ]);
    }

    public function withColors(string $primary, string $secondary): static
    {
        return $this->state([
            'primary_color' => $primary,
            'secondary_color' => $secondary,
        ]);
    }

    public function forLandlord(User $landlord): static
    {
        return $this->state(['landlord_id' => $landlord->id]);
    }
}
