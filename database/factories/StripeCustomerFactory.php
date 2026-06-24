<?php

namespace Database\Factories;

use App\Models\StripeCustomer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StripeCustomer>
 */
class StripeCustomerFactory extends Factory
{
    protected $model = StripeCustomer::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'stripe_customer_id' => 'cus_'.fake()->unique()->bothify('??############'),
            'default_payment_method_id' => null,
        ];
    }
}
