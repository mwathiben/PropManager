<?php

namespace Database\Factories;

use App\Models\Building;
use App\Models\CaretakerAssignment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CaretakerAssignment>
 */
class CaretakerAssignmentFactory extends Factory
{
    protected $model = CaretakerAssignment::class;

    public function definition(): array
    {
        return [
            'caretaker_id' => User::factory()->state(['role' => 'caretaker']),
            'building_id' => Building::factory(),
            'status' => CaretakerAssignment::STATUS_PENDING,
            'assigned_at' => now(),
        ];
    }

    public function accepted(): static
    {
        return $this->state([
            'status' => CaretakerAssignment::STATUS_ACCEPTED,
            'decided_at' => now(),
        ]);
    }

    public function declined(): static
    {
        return $this->state([
            'status' => CaretakerAssignment::STATUS_DECLINED,
            'decided_at' => now(),
            'decision_reason' => fake()->sentence(),
        ]);
    }
}
