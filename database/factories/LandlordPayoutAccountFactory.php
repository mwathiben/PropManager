<?php

namespace Database\Factories;

use App\Models\LandlordPayoutAccount;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class LandlordPayoutAccountFactory extends Factory
{
    protected $model = LandlordPayoutAccount::class;

    public function definition(): array
    {
        return [
            'landlord_id' => User::factory()->state(['role' => 'landlord']),
            'provider' => 'paystack',
            'subaccount_code' => 'ACCT_'.strtoupper(fake()->unique()->bothify('??????????')),
            'account_type' => 'bank',
            'account_number' => fake()->numerify('##########'),
            'account_name' => fake()->name(),
            'bank_code' => fake()->numerify('###'),
            'bank_name' => fake()->randomElement(['KCB', 'Equity', 'Cooperative', 'Stanbic', 'NCBA']),
            'mobile_number' => null,
            'business_name' => fake()->optional(0.5)->company(),
            'settlement_bank' => fake()->randomElement(['KCB', 'Equity', 'Cooperative']),
            'percentage_charge' => fake()->randomElement([1.5, 2.0, 2.5]),
            'flat_charge' => fake()->randomElement([0, 50, 100]),
            'verification_status' => 'pending',
            'rejection_reason' => null,
            'is_active' => true,
            'is_primary' => true,
            'verified_at' => null,
            'metadata' => null,
        ];
    }

    public function paystack(): static
    {
        return $this->state([
            'provider' => 'paystack',
            'subaccount_code' => 'ACCT_'.strtoupper(fake()->unique()->bothify('??????????')),
        ]);
    }

    public function flutterwave(): static
    {
        return $this->state([
            'provider' => 'flutterwave',
            'subaccount_code' => 'RS_'.strtoupper(fake()->unique()->bothify('????????????')),
        ]);
    }

    public function bank(): static
    {
        return $this->state([
            'account_type' => 'bank',
            'mobile_number' => null,
            'bank_code' => fake()->numerify('###'),
            'bank_name' => fake()->randomElement(['KCB', 'Equity', 'Cooperative']),
        ]);
    }

    public function mobileMoney(): static
    {
        return $this->state([
            'account_type' => 'mobile_money',
            'bank_code' => null,
            'bank_name' => null,
            'mobile_number' => '254'.fake()->numerify('#########'),
        ]);
    }

    public function verified(): static
    {
        return $this->state([
            'verification_status' => 'verified',
            'verified_at' => now(),
            'is_active' => true,
        ]);
    }

    public function pending(): static
    {
        return $this->state([
            'verification_status' => 'pending',
            'verified_at' => null,
        ]);
    }

    public function rejected(?string $reason = null): static
    {
        return $this->state([
            'verification_status' => 'rejected',
            'is_active' => false,
            'rejection_reason' => $reason ?? 'Invalid account details',
        ]);
    }

    public function suspended(): static
    {
        return $this->state([
            'verification_status' => 'suspended',
            'is_active' => false,
        ]);
    }

    public function primary(): static
    {
        return $this->state(['is_primary' => true]);
    }

    public function secondary(): static
    {
        return $this->state(['is_primary' => false]);
    }

    public function forLandlord(User $landlord): static
    {
        return $this->state(['landlord_id' => $landlord->id]);
    }
}
