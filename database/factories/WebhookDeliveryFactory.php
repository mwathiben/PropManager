<?php

namespace Database\Factories;

use App\Models\WebhookDelivery;
use App\Models\WebhookSubscription;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WebhookDelivery>
 */
class WebhookDeliveryFactory extends Factory
{
    protected $model = WebhookDelivery::class;

    public function definition(): array
    {
        return [
            'webhook_subscription_id' => WebhookSubscription::factory(),
            'event_type' => fake()->randomElement(['payment.received', 'invoice.created', 'lease.created']),
            'payload' => ['id' => fake()->uuid(), 'event' => 'test'],
            'attempt' => 1,
            'dead_lettered' => false,
        ];
    }

    public function delivered(): static
    {
        return $this->state([
            'http_status' => 200,
            'dispatched_at' => now(),
            'completed_at' => now(),
        ]);
    }
}
