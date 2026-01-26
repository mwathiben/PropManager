<?php

namespace Database\Factories;

use App\Models\Consent;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ConsentFactory extends Factory
{
    protected $model = Consent::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'consent_type' => fake()->randomElement([
                Consent::TYPE_TERMS,
                Consent::TYPE_PRIVACY,
                Consent::TYPE_MARKETING,
                Consent::TYPE_DATA_PROCESSING,
                Consent::TYPE_THIRD_PARTY_SHARING,
            ]),
            'version' => '1.0.0',
            'is_granted' => true,
            'granted_at' => now(),
            'withdrawn_at' => null,
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
            'metadata' => null,
        ];
    }

    public function granted(): static
    {
        return $this->state([
            'is_granted' => true,
            'granted_at' => now(),
            'withdrawn_at' => null,
        ]);
    }

    public function withdrawn(): static
    {
        return $this->state([
            'is_granted' => false,
            'withdrawn_at' => now(),
        ]);
    }

    public function terms(): static
    {
        return $this->state([
            'consent_type' => Consent::TYPE_TERMS,
        ]);
    }

    public function privacy(): static
    {
        return $this->state([
            'consent_type' => Consent::TYPE_PRIVACY,
        ]);
    }

    public function marketing(): static
    {
        return $this->state([
            'consent_type' => Consent::TYPE_MARKETING,
        ]);
    }

    public function dataProcessing(): static
    {
        return $this->state([
            'consent_type' => Consent::TYPE_DATA_PROCESSING,
        ]);
    }

    public function thirdPartySharing(): static
    {
        return $this->state([
            'consent_type' => Consent::TYPE_THIRD_PARTY_SHARING,
        ]);
    }

    public function version(string $version): static
    {
        return $this->state([
            'version' => $version,
        ]);
    }

    public function withMetadata(array $metadata): static
    {
        return $this->state([
            'metadata' => $metadata,
        ]);
    }

    public function forUser(User $user): static
    {
        return $this->state([
            'user_id' => $user->id,
        ]);
    }
}
