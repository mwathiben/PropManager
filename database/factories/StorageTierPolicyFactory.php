<?php

namespace Database\Factories;

use App\Models\StorageTierPolicy;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StorageTierPolicy>
 */
class StorageTierPolicyFactory extends Factory
{
    protected $model = StorageTierPolicy::class;

    public function definition(): array
    {
        return [
            'disk_name' => fake()->randomElement(['s3', 'local', 'private']),
            'path_prefix' => fake()->slug(2),
            'max_age_days' => fake()->numberBetween(30, 3650),
            'target_tier' => fake()->randomElement(StorageTierPolicy::TIERS),
            'is_active' => true,
        ];
    }
}
