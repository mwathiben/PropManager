<?php

namespace Database\Factories;

use App\Models\NpsResponse;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NpsResponse>
 */
class NpsResponseFactory extends Factory
{
    protected $model = NpsResponse::class;

    public function definition(): array
    {
        $score = fake()->numberBetween(0, 10);

        return [
            'user_id' => User::factory()->state(['role' => 'landlord']),
            'score' => $score,
            'category' => NpsResponse::categorise($score),
            'comment' => fake()->optional()->sentence(),
            'responded_at' => now(),
        ];
    }

    /**
     * landlord_id is guarded (stamped by TenantScope when authenticated);
     * in the unauthenticated factory context we set it directly to the
     * surveyed landlord so the row satisfies its NOT NULL constraint.
     */
    public function configure(): static
    {
        return $this->afterMaking(function (NpsResponse $response): void {
            $response->landlord_id ??= $response->user_id;
        });
    }
}
