<?php

namespace Database\Factories;

use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class SubscriptionFactory extends Factory
{
    protected $model = Subscription::class;

    public function definition(): array
    {
        $billingCycle = fake()->randomElement(['monthly', 'yearly']);
        $periodStart = $billingCycle === 'yearly' ? now()->startOfYear() : now()->startOfMonth();
        $periodEnd = $billingCycle === 'yearly' ? now()->endOfYear() : now()->endOfMonth();

        return [
            'user_id' => User::factory()->state(['role' => 'landlord']),
            'plan_id' => SubscriptionPlan::factory(),
            'status' => 'active',
            'billing_cycle' => $billingCycle,
            'trial_ends_at' => null,
            'current_period_start' => $periodStart,
            'current_period_end' => $periodEnd,
            'cancelled_at' => null,
            'ends_at' => null,
            'paystack_subscription_code' => null,
            'paystack_customer_code' => null,
        ];
    }

    public function active(): static
    {
        return $this->state([
            'status' => 'active',
            'cancelled_at' => null,
            'ends_at' => null,
        ]);
    }

    public function trialing(): static
    {
        return $this->state([
            'status' => 'trialing',
            'trial_ends_at' => now()->addDays(14),
        ]);
    }

    public function trialExpired(): static
    {
        return $this->state([
            'status' => 'trialing',
            'trial_ends_at' => now()->subDays(1),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'ends_at' => now()->addDays(fake()->numberBetween(1, 30)),
        ]);
    }

    public function ended(): static
    {
        return $this->state([
            'status' => 'cancelled',
            'cancelled_at' => now()->subDays(30),
            'ends_at' => now()->subDays(1),
        ]);
    }

    public function pastDue(): static
    {
        return $this->state([
            'status' => 'past_due',
            'current_period_end' => now()->subDays(5),
        ]);
    }

    public function paused(): static
    {
        return $this->state(['status' => 'paused']);
    }

    public function monthly(): static
    {
        return $this->state([
            'billing_cycle' => 'monthly',
            'current_period_start' => now()->startOfMonth(),
            'current_period_end' => now()->endOfMonth(),
        ]);
    }

    public function yearly(): static
    {
        return $this->state([
            'billing_cycle' => 'yearly',
            'current_period_start' => now()->startOfYear(),
            'current_period_end' => now()->endOfYear(),
        ]);
    }

    public function onGracePeriod(): static
    {
        return $this->state([
            'status' => 'cancelled',
            'cancelled_at' => now()->subDays(5),
            'ends_at' => now()->addDays(25),
        ]);
    }

    public function withPaystack(): static
    {
        return $this->state([
            'paystack_subscription_code' => 'SUB_'.strtoupper(fake()->bothify('????????????')),
            'paystack_customer_code' => 'CUS_'.strtoupper(fake()->bothify('????????????')),
        ]);
    }

    public function forUser(User $user): static
    {
        return $this->state(['user_id' => $user->id]);
    }

    public function forPlan(SubscriptionPlan $plan): static
    {
        return $this->state(['plan_id' => $plan->id]);
    }
}
