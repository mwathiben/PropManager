<?php

namespace Database\Factories;

use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PushSubscriptionFactory extends Factory
{
    protected $model = PushSubscription::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/'.fake()->regexify('[A-Za-z0-9_-]{100,140}'),
            'public_key' => fake()->regexify('[A-Za-z0-9_-]{87}'),
            'auth_token' => fake()->regexify('[A-Za-z0-9_-]{22}'),
            'content_encoding' => 'aesgcm',
            'user_agent' => fake()->randomElement([
                'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36',
                'Mozilla/5.0 (iPhone; CPU iPhone OS 16_0 like Mac OS X) AppleWebKit/605.1.15',
                'Mozilla/5.0 (Linux; Android 13) AppleWebKit/537.36',
            ]),
            'expires_at' => null,
        ];
    }

    public function active(): static
    {
        return $this->state([
            'expires_at' => now()->addMonths(6),
        ]);
    }

    public function expired(): static
    {
        return $this->state([
            'expires_at' => now()->subDays(fake()->numberBetween(1, 30)),
        ]);
    }

    public function neverExpires(): static
    {
        return $this->state(['expires_at' => null]);
    }

    public function expiringIn(int $days): static
    {
        return $this->state([
            'expires_at' => now()->addDays($days),
        ]);
    }

    public function chrome(): static
    {
        return $this->state([
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/'.fake()->regexify('[A-Za-z0-9_-]{100,140}'),
            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        ]);
    }

    public function firefox(): static
    {
        return $this->state([
            'endpoint' => 'https://updates.push.services.mozilla.com/wpush/v2/'.fake()->regexify('[A-Za-z0-9_-]{100,140}'),
            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:120.0) Gecko/20100101 Firefox/120.0',
        ]);
    }

    public function safari(): static
    {
        return $this->state([
            'endpoint' => 'https://web.push.apple.com/'.fake()->regexify('[A-Za-z0-9_-]{100,140}'),
            'user_agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Safari/605.1.15',
        ]);
    }

    public function mobile(): static
    {
        return $this->state([
            'user_agent' => fake()->randomElement([
                'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1',
                'Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Mobile Safari/537.36',
            ]),
        ]);
    }

    public function forUser(User $user): static
    {
        return $this->state(['user_id' => $user->id]);
    }
}
