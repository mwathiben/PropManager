<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use App\Models\UserTourState;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserTourState>
 */
class UserTourStateFactory extends Factory
{
    protected $model = UserTourState::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'tour_key' => 'landlord-dashboard',
            'current_step' => 0,
            'status' => UserTourState::STATUS_ACTIVE,
            'started_at' => now(),
            'last_advanced_at' => now(),
        ];
    }

    public function completed(): static
    {
        return $this->state(fn () => [
            'status' => UserTourState::STATUS_COMPLETED,
            'completed_at' => now(),
        ]);
    }

    public function dismissed(): static
    {
        return $this->state(fn () => [
            'status' => UserTourState::STATUS_DISMISSED,
            'dismissed_at' => now(),
        ]);
    }
}
