<?php

namespace Database\Factories;

use App\Models\UsageRecord;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class UsageRecordFactory extends Factory
{
    protected $model = UsageRecord::class;

    public function definition(): array
    {
        $periodStart = now()->startOfMonth();
        $periodEnd = now()->endOfMonth();

        return [
            'user_id' => User::factory()->state(['role' => 'landlord']),
            'feature' => fake()->randomElement(['units', 'properties', 'buildings', 'sms_sent', 'emails_sent', 'documents']),
            'quantity' => fake()->numberBetween(1, 100),
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
        ];
    }

    public function forUser(User $user): static
    {
        return $this->state(['user_id' => $user->id]);
    }

    public function forFeature(string $feature): static
    {
        return $this->state(['feature' => $feature]);
    }

    public function withQuantity(int $quantity): static
    {
        return $this->state(['quantity' => $quantity]);
    }

    public function currentPeriod(): static
    {
        return $this->state([
            'period_start' => now()->startOfMonth(),
            'period_end' => now()->endOfMonth(),
        ]);
    }

    public function previousPeriod(): static
    {
        return $this->state([
            'period_start' => now()->subMonth()->startOfMonth(),
            'period_end' => now()->subMonth()->endOfMonth(),
        ]);
    }

    public function forPeriod(\DateTimeInterface $start, \DateTimeInterface $end): static
    {
        return $this->state([
            'period_start' => $start,
            'period_end' => $end,
        ]);
    }

    public function units(): static
    {
        return $this->state(['feature' => 'units']);
    }

    public function properties(): static
    {
        return $this->state(['feature' => 'properties']);
    }

    public function smsSent(): static
    {
        return $this->state(['feature' => 'sms_sent']);
    }

    public function emailsSent(): static
    {
        return $this->state(['feature' => 'emails_sent']);
    }

    public function documents(): static
    {
        return $this->state(['feature' => 'documents']);
    }
}
