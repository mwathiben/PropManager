<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Lease;
use App\Models\LeaseCoTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LeaseCoTenant>
 */
class LeaseCoTenantFactory extends Factory
{
    protected $model = LeaseCoTenant::class;

    public function definition(): array
    {
        return [
            'lease_id' => Lease::factory(),
            'landlord_id' => User::factory(),
            'name' => $this->faker->name(),
            'email' => $this->faker->safeEmail(),
            'phone' => $this->faker->numerify('07########'),
            'relationship' => 'spouse',
            'is_responsible_for_rent' => true,
        ];
    }
}
