<?php

namespace Database\Factories;

use App\Models\AttributionTouchpoint;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AttributionTouchpoint>
 */
class AttributionTouchpointFactory extends Factory
{
    protected $model = AttributionTouchpoint::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'channel' => fake()->randomElement([
                AttributionTouchpoint::CHANNEL_REFERRAL,
                AttributionTouchpoint::CHANNEL_ORGANIC_SEARCH,
                AttributionTouchpoint::CHANNEL_PAID_SEARCH,
                AttributionTouchpoint::CHANNEL_SOCIAL,
                AttributionTouchpoint::CHANNEL_EMAIL,
                AttributionTouchpoint::CHANNEL_DIRECT,
                AttributionTouchpoint::CHANNEL_INVITATION,
            ]),
            'medium' => fake()->optional()->word(),
            'campaign' => fake()->optional()->slug(2),
            'touched_at' => now(),
        ];
    }
}
