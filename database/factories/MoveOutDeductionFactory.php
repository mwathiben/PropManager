<?php

namespace Database\Factories;

use App\Models\MoveOut;
use App\Models\MoveOutDeduction;
use Illuminate\Database\Eloquent\Factories\Factory;

class MoveOutDeductionFactory extends Factory
{
    protected $model = MoveOutDeduction::class;

    public function definition(): array
    {
        return [
            'move_out_id' => MoveOut::factory(),
            'description' => fake()->randomElement([
                'Wall damage repair',
                'Broken window replacement',
                'Missing fixtures',
                'Deep cleaning required',
                'Key replacement',
                'Paint restoration',
                'Floor damage repair',
                'Appliance repair',
                'Door repair',
                'Plumbing repairs',
            ]),
            'amount' => fake()->numberBetween(500, 10000),
            'notes' => fake()->optional(0.5)->sentence(),
            'photo_path' => null,
        ];
    }

    public function withPhoto(): static
    {
        return $this->state([
            'photo_path' => 'deductions/'.fake()->uuid().'.jpg',
        ]);
    }

    public function minor(): static
    {
        return $this->state([
            'amount' => fake()->numberBetween(100, 1500),
            'description' => fake()->randomElement([
                'Minor scratch repair',
                'Light fixture replacement',
                'Key replacement',
                'Small paint touch-up',
            ]),
        ]);
    }

    public function major(): static
    {
        return $this->state([
            'amount' => fake()->numberBetween(5000, 25000),
            'description' => fake()->randomElement([
                'Complete room repaint',
                'Window replacement',
                'Major appliance replacement',
                'Extensive floor repair',
            ]),
        ]);
    }

    public function cleaning(): static
    {
        return $this->state([
            'description' => 'Deep cleaning required',
            'amount' => fake()->numberBetween(1000, 3000),
        ]);
    }

    public function damage(): static
    {
        return $this->state([
            'description' => fake()->randomElement([
                'Wall damage repair',
                'Floor damage repair',
                'Broken window replacement',
            ]),
            'amount' => fake()->numberBetween(2000, 15000),
        ]);
    }

    public function forMoveOut(MoveOut $moveOut): static
    {
        return $this->state([
            'move_out_id' => $moveOut->id,
        ]);
    }
}
