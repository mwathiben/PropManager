<?php

namespace Database\Factories;

use App\Models\InvoiceSetting;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class InvoiceSettingFactory extends Factory
{
    protected $model = InvoiceSetting::class;

    public function definition(): array
    {
        return [
            'landlord_id' => User::factory()->state(['role' => 'landlord']),
            'business_name' => fake()->company(),
            'business_address' => fake()->address(),
            'business_phone' => fake()->phoneNumber(),
            'business_email' => fake()->companyEmail(),
            'logo_path' => null,
            'tax_number' => fake()->optional()->numerify('TAX-######'),
            'bank_name' => fake()->randomElement(['Equity Bank', 'KCB', 'Cooperative Bank', 'NCBA', 'Stanbic']),
            'bank_account_name' => fake()->company(),
            'bank_account_number' => fake()->numerify('##########'),
            'bank_branch' => fake()->city().' Branch',
            'bank_swift_code' => fake()->optional()->regexify('[A-Z]{6}[A-Z0-9]{2}'),
            'invoice_prefix' => 'INV',
            'invoice_next_number' => fake()->numberBetween(1, 100),
            'receipt_prefix' => 'RCT',
            'receipt_next_number' => fake()->numberBetween(1, 100),
            'credit_note_prefix' => 'CN',
            'credit_note_next_number' => fake()->numberBetween(1, 50),
            'default_due_days' => fake()->randomElement([7, 14, 30]),
            'late_penalty_percentage' => fake()->randomFloat(2, 0, 10),
            'grace_period_days' => fake()->randomElement([0, 3, 5, 7]),
            'terms_and_conditions' => fake()->optional()->paragraph(),
            'footer_note' => fake()->optional()->sentence(),
            'auto_generate_enabled' => fake()->boolean(70),
            'auto_generate_day' => fake()->numberBetween(1, 28),
            'auto_send_enabled' => fake()->boolean(50),
            'prorate_first_month' => fake()->boolean(60),
            'include_last_month_rent' => fake()->boolean(30),
            'admin_fee_amount' => fake()->randomFloat(2, 0, 500),
            'key_deposit_amount' => fake()->randomFloat(2, 0, 2000),
            'first_invoice_due_days' => fake()->randomElement([3, 5, 7, 14]),
            'auto_generate_first_invoice' => fake()->boolean(80),
            'auto_email_receipt' => fake()->boolean(70),
            'receipt_show_logo' => true,
            'receipt_show_tenant_details' => true,
            'receipt_show_invoice_details' => true,
            'receipt_show_payment_method' => true,
            'receipt_header_text' => fake()->optional()->sentence(),
            'receipt_footer_text' => fake()->optional()->sentence(),
            'receipt_thank_you_message' => 'Thank you for your payment!',
            'fiscal_year_type' => fake()->randomElement(['calendar', 'custom']),
            'fiscal_year_start_month' => fake()->numberBetween(1, 12),
        ];
    }

    public function withBankDetails(): static
    {
        return $this->state([
            'bank_name' => 'Equity Bank',
            'bank_account_name' => fake()->company(),
            'bank_account_number' => fake()->numerify('##########'),
            'bank_branch' => 'Nairobi Main Branch',
            'bank_swift_code' => 'EABORBI',
        ]);
    }

    public function withoutBankDetails(): static
    {
        return $this->state([
            'bank_name' => null,
            'bank_account_name' => null,
            'bank_account_number' => null,
            'bank_branch' => null,
            'bank_swift_code' => null,
        ]);
    }

    public function autoGenerationEnabled(): static
    {
        return $this->state([
            'auto_generate_enabled' => true,
            'auto_generate_day' => 1,
            'auto_send_enabled' => true,
            'auto_generate_first_invoice' => true,
            'auto_email_receipt' => true,
        ]);
    }

    public function autoGenerationDisabled(): static
    {
        return $this->state([
            'auto_generate_enabled' => false,
            'auto_send_enabled' => false,
            'auto_generate_first_invoice' => false,
            'auto_email_receipt' => false,
        ]);
    }

    public function calendarYear(): static
    {
        return $this->state([
            'fiscal_year_type' => 'calendar',
            'fiscal_year_start_month' => 1,
        ]);
    }

    public function customFiscalYear(int $startMonth = 7): static
    {
        return $this->state([
            'fiscal_year_type' => 'custom',
            'fiscal_year_start_month' => $startMonth,
        ]);
    }

    public function withPenalties(): static
    {
        return $this->state([
            'late_penalty_percentage' => 5.00,
            'grace_period_days' => 3,
        ]);
    }

    public function noPenalties(): static
    {
        return $this->state([
            'late_penalty_percentage' => 0,
            'grace_period_days' => 0,
        ]);
    }

    public function forLandlord(User $landlord): static
    {
        return $this->state(['landlord_id' => $landlord->id]);
    }

    public function minimal(): static
    {
        return $this->state([
            'business_name' => fake()->company(),
            'business_email' => fake()->companyEmail(),
            'invoice_prefix' => 'INV',
            'invoice_next_number' => 1,
            'receipt_prefix' => 'RCT',
            'receipt_next_number' => 1,
            'default_due_days' => 30,
        ]);
    }
}
