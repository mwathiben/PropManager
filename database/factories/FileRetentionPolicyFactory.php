<?php

namespace Database\Factories;

use App\Models\FileRetentionPolicy;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FileRetentionPolicy>
 */
class FileRetentionPolicyFactory extends Factory
{
    protected $model = FileRetentionPolicy::class;

    public function definition(): array
    {
        return [
            'subject' => fake()->randomElement(FileRetentionPolicy::SUBJECTS),
            'retention_days' => fake()->numberBetween(7, 2555),
            'landlord_id' => null,
        ];
    }
}
