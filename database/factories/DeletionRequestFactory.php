<?php

namespace Database\Factories;

use App\Models\DeletionRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class DeletionRequestFactory extends Factory
{
    protected $model = DeletionRequest::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'reason' => fake()->randomElement([
                'No longer using the service',
                'Privacy concerns',
                'Switching to a different provider',
                'Account consolidation',
                'Other personal reasons',
            ]),
            'status' => DeletionRequest::STATUS_PENDING,
            'requested_at' => now(),
            'scheduled_deletion_at' => now()->addDays(30),
            'completed_at' => null,
            'cancelled_at' => null,
            'anonymized_email' => null,
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
        ];
    }

    public function pending(): static
    {
        return $this->state([
            'status' => DeletionRequest::STATUS_PENDING,
            'completed_at' => null,
            'cancelled_at' => null,
        ]);
    }

    public function processing(): static
    {
        return $this->state([
            'status' => DeletionRequest::STATUS_PROCESSING,
        ]);
    }

    public function completed(): static
    {
        return $this->state([
            'status' => DeletionRequest::STATUS_COMPLETED,
            'completed_at' => now(),
            'anonymized_email' => 'deleted_'.fake()->uuid().'@anonymized.local',
        ]);
    }

    public function cancelled(): static
    {
        return $this->state([
            'status' => DeletionRequest::STATUS_CANCELLED,
            'cancelled_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state([
            'status' => DeletionRequest::STATUS_FAILED,
        ]);
    }

    public function readyForProcessing(): static
    {
        return $this->state([
            'status' => DeletionRequest::STATUS_PENDING,
            'scheduled_deletion_at' => now()->subDay(),
        ]);
    }

    public function scheduledIn(int $days): static
    {
        return $this->state([
            'scheduled_deletion_at' => now()->addDays($days),
        ]);
    }

    public function forUser(User $user): static
    {
        return $this->state([
            'user_id' => $user->id,
        ]);
    }
}
