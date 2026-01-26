<?php

namespace Database\Factories;

use App\Models\OnboardingProgress;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class OnboardingProgressFactory extends Factory
{
    protected $model = OnboardingProgress::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory()->state(['role' => 'landlord']),
            'current_step' => 1,
            'total_steps' => 8,
            'step_data' => [],
            'completed_steps' => [],
            'is_complete' => false,
            'started_at' => now(),
            'completed_at' => null,
        ];
    }

    public function notStarted(): static
    {
        return $this->state([
            'current_step' => 1,
            'started_at' => null,
            'completed_steps' => [],
            'step_data' => [],
        ]);
    }

    public function started(): static
    {
        return $this->state([
            'started_at' => now(),
        ]);
    }

    public function inProgress(): static
    {
        $currentStep = fake()->numberBetween(2, 7);

        return $this->state([
            'current_step' => $currentStep,
            'completed_steps' => range(1, $currentStep - 1),
            'started_at' => now()->subDays(fake()->numberBetween(1, 7)),
        ]);
    }

    public function atStep(int $step): static
    {
        $completedSteps = $step > 1 ? range(1, $step - 1) : [];

        return $this->state([
            'current_step' => $step,
            'completed_steps' => $completedSteps,
        ]);
    }

    public function complete(): static
    {
        return $this->state([
            'current_step' => 8,
            'completed_steps' => range(1, 8),
            'is_complete' => true,
            'completed_at' => now(),
        ]);
    }

    public function incomplete(): static
    {
        return $this->state([
            'is_complete' => false,
            'completed_at' => null,
        ]);
    }

    public function withStepData(int $step, array $data): static
    {
        return $this->state(function (array $attributes) use ($step, $data) {
            $stepData = $attributes['step_data'] ?? [];
            $stepData[$step] = $data;

            return ['step_data' => $stepData];
        });
    }

    public function forUser(User $user): static
    {
        return $this->state([
            'user_id' => $user->id,
        ]);
    }
}
