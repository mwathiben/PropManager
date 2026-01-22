<?php

namespace Database\Factories;

use App\Models\PaymentConfiguration;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PaymentConfigurationFactory extends Factory
{
    protected $model = PaymentConfiguration::class;

    public function definition(): array
    {
        return [
            'landlord_id' => User::factory()->state(['role' => 'landlord']),
            'default_rent' => fake()->numberBetween(15000, 50000),
            'water_billing_type' => 'consumption',
            'flat_water_rate' => null,
            'water_unit_rate' => fake()->randomElement([100, 150, 200]),
            'accepted_payment_methods' => ['cash', 'mobile_money'],
            'bank_name' => null,
            'bank_account_name' => null,
            'bank_account_number' => null,
            'bank_branch' => null,
            'mpesa_account_name' => null,
            'mpesa_shortcode_type' => null,
            'mpesa_shortcode' => null,
            'mpesa_passkey' => null,
            'paystack_enabled' => false,
        ];
    }

    public function withBankDetails(): static
    {
        return $this->state([
            'bank_name' => fake()->randomElement(['KCB', 'Equity', 'Cooperative', 'Stanbic']),
            'bank_account_name' => fake()->name(),
            'bank_account_number' => fake()->numerify('##########'),
            'bank_branch' => fake()->city(),
            'accepted_payment_methods' => ['cash', 'mobile_money', 'bank_transfer'],
        ]);
    }

    public function withMpesa(): static
    {
        return $this->state([
            'mpesa_account_name' => fake()->company(),
            'mpesa_shortcode_type' => fake()->randomElement(['paybill', 'till']),
            'mpesa_shortcode' => fake()->numerify('######'),
            'mpesa_passkey' => fake()->uuid(),
            'accepted_payment_methods' => ['cash', 'mobile_money'],
        ]);
    }

    public function withPaystack(): static
    {
        return $this->state([
            'paystack_enabled' => true,
            'accepted_payment_methods' => ['cash', 'mobile_money', 'paystack'],
        ]);
    }

    public function flatWaterRate(float $rate = 500.0): static
    {
        return $this->state([
            'water_billing_type' => 'flat_rate',
            'flat_water_rate' => $rate,
            'water_unit_rate' => null,
        ]);
    }

    public function noWaterBilling(): static
    {
        return $this->state([
            'water_billing_type' => 'none',
            'flat_water_rate' => null,
            'water_unit_rate' => null,
        ]);
    }

    public function forLandlord(User $landlord): static
    {
        return $this->state(['landlord_id' => $landlord->id]);
    }
}
