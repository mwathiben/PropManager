<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Lease;
use App\Models\LeaseGuarantor;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LeaseGuarantor>
 */
class LeaseGuarantorFactory extends Factory
{
    protected $model = LeaseGuarantor::class;

    public function definition(): array
    {
        return [
            'lease_id' => Lease::factory(),
            'landlord_id' => User::factory(),
            'name' => $this->faker->name(),
            'email' => $this->faker->safeEmail(),
            'phone' => $this->faker->numerify('07########'),
            'relationship' => 'parent',
            'guaranteed_amount' => 100000,
            'status' => LeaseGuarantor::STATUS_ACTIVE,
        ];
    }

    public function released(): static
    {
        return $this->state(fn () => [
            'status' => LeaseGuarantor::STATUS_RELEASED,
            'released_at' => now(),
        ]);
    }
}
