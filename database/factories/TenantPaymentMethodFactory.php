<?php

namespace Database\Factories;

use App\Models\TenantPaymentMethod;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TenantPaymentMethod>
 */
class TenantPaymentMethodFactory extends Factory
{
    protected $model = TenantPaymentMethod::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory()->state(['role' => 'tenant']),
            'type' => fake()->randomElement(['mpesa', 'bank', 'card']),
            'details_encrypted' => ['account' => fake()->bothify('##########'), 'label' => fake()->word()],
            'is_default' => false,
        ];
    }

    public function default(): static
    {
        return $this->state(['is_default' => true]);
    }
}
