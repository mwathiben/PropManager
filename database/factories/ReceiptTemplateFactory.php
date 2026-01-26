<?php

namespace Database\Factories;

use App\Models\ReceiptTemplate;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ReceiptTemplateFactory extends Factory
{
    protected $model = ReceiptTemplate::class;

    public function definition(): array
    {
        return [
            'landlord_id' => User::factory()->state(['role' => 'landlord']),
            'name' => fake()->randomElement(['Standard', 'Professional', 'Detailed', 'Compact']).' Receipt',
            'design' => ReceiptTemplate::DESIGN_CLASSIC,
            'is_default' => false,
            'show_logo' => true,
            'show_receipt_number' => true,
            'show_payment_date' => true,
            'show_payment_method' => true,
            'show_transaction_reference' => true,
            'show_amount_breakdown' => true,
            'show_tenant_name' => true,
            'show_tenant_email' => false,
            'show_tenant_phone' => false,
            'show_unit_details' => true,
            'show_building_name' => true,
            'show_invoice_details' => true,
            'show_invoice_breakdown' => true,
            'show_balance_after_payment' => true,
            'show_thank_you_message' => true,
            'show_qr_code' => false,
            'show_footer' => true,
            'custom_header' => null,
            'custom_footer' => null,
            'thank_you_message' => 'Thank you for your payment!',
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
        return $this->state(['design' => ReceiptTemplate::DESIGN_CLASSIC]);
    }

    public function modern(): static
    {
        return $this->state(['design' => ReceiptTemplate::DESIGN_MODERN]);
    }

    public function minimal(): static
    {
        return $this->state(['design' => ReceiptTemplate::DESIGN_MINIMAL]);
    }

    public function professional(): static
    {
        return $this->state(['design' => ReceiptTemplate::DESIGN_PROFESSIONAL]);
    }

    public function withAllElements(): static
    {
        return $this->state([
            'show_logo' => true,
            'show_receipt_number' => true,
            'show_payment_date' => true,
            'show_payment_method' => true,
            'show_transaction_reference' => true,
            'show_amount_breakdown' => true,
            'show_tenant_name' => true,
            'show_tenant_email' => true,
            'show_tenant_phone' => true,
            'show_unit_details' => true,
            'show_building_name' => true,
            'show_invoice_details' => true,
            'show_invoice_breakdown' => true,
            'show_balance_after_payment' => true,
            'show_thank_you_message' => true,
            'show_qr_code' => true,
            'show_footer' => true,
        ]);
    }

    public function minimal_elements(): static
    {
        return $this->state([
            'show_logo' => true,
            'show_receipt_number' => true,
            'show_payment_date' => true,
            'show_payment_method' => false,
            'show_transaction_reference' => false,
            'show_amount_breakdown' => false,
            'show_tenant_name' => true,
            'show_tenant_email' => false,
            'show_tenant_phone' => false,
            'show_unit_details' => false,
            'show_building_name' => false,
            'show_invoice_details' => false,
            'show_invoice_breakdown' => false,
            'show_balance_after_payment' => false,
            'show_thank_you_message' => true,
            'show_qr_code' => false,
            'show_footer' => false,
        ]);
    }

    public function withCustomContent(): static
    {
        return $this->state([
            'custom_header' => fake()->sentence(),
            'custom_footer' => fake()->sentence(),
            'thank_you_message' => 'Thank you for your payment! '.fake()->sentence(),
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
