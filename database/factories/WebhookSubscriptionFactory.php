<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\WebhookSubscription;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<WebhookSubscription>
 */
class WebhookSubscriptionFactory extends Factory
{
    protected $model = WebhookSubscription::class;

    public function definition(): array
    {
        return [
            'landlord_id' => User::factory()->state(['role' => 'landlord']),
            'url' => fake()->url(),
            'secret' => Str::random(64),
            'events' => ['payment.received', 'invoice.created'],
            'active' => true,
        ];
    }
}
