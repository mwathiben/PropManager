<?php

namespace Database\Factories;

use App\Models\CheckoutSession;
use App\Models\CheckoutSessionItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CheckoutSessionItem>
 */
class CheckoutSessionItemFactory extends Factory
{
    protected $model = CheckoutSessionItem::class;

    public function definition(): array
    {
        return [
            'checkout_session_id' => CheckoutSession::factory(),
            'line_type' => fake()->randomElement(CheckoutSessionItem::TYPES),
            'line_id' => fake()->numberBetween(1, 1000),
            'amount_cents' => fake()->numberBetween(10000, 200000),
            'currency' => 'KES',
            'description' => fake()->sentence(3),
            'sort_order' => 0,
        ];
    }
}
