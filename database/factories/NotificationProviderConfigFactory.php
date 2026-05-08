<?php

namespace Database\Factories;

use App\Models\NotificationProviderConfig;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class NotificationProviderConfigFactory extends Factory
{
    protected $model = NotificationProviderConfig::class;

    public function definition(): array
    {
        return [
            'landlord_id' => User::factory()->state(['role' => 'landlord']),
            'provider_type' => NotificationProviderConfig::TYPE_EMAIL,
            'provider_name' => 'smtp',
            'credentials' => null,
            'is_enabled' => false,
            'is_verified' => false,
            'settings' => [],
        ];
    }

    public function email(): static
    {
        return $this->state([
            'provider_type' => NotificationProviderConfig::TYPE_EMAIL,
            'provider_name' => 'smtp',
            'credentials' => [
                'host' => 'smtp.example.com',
                'port' => 587,
                'username' => 'user@example.com',
                'password' => 'redacted-password',
                'encryption' => 'tls',
            ],
        ]);
    }

    public function sms(): static
    {
        return $this->state([
            'provider_type' => NotificationProviderConfig::TYPE_SMS,
            'provider_name' => 'africas_talking',
            'credentials' => [
                'api_key' => 'at_redacted_key_'.fake()->bothify('????????'),
                'username' => 'sandbox',
            ],
        ]);
    }

    public function smsTwilio(): static
    {
        return $this->state([
            'provider_type' => NotificationProviderConfig::TYPE_SMS,
            'provider_name' => 'twilio',
            'credentials' => [
                'account_sid' => 'AC'.fake()->bothify('????????????????????????????????'),
                'auth_token' => fake()->bothify('????????????????????????????????'),
                'phone_number' => '+15551234567',
            ],
        ]);
    }

    public function whatsapp(): static
    {
        return $this->state([
            'provider_type' => NotificationProviderConfig::TYPE_WHATSAPP,
            'provider_name' => 'twilio',
            'credentials' => [
                'account_sid' => 'AC'.fake()->bothify('????????????????????????????????'),
                'auth_token' => fake()->bothify('????????????????????????????????'),
                'whatsapp_number' => '+15551234567',
            ],
        ]);
    }

    public function push(): static
    {
        return $this->state([
            'provider_type' => NotificationProviderConfig::TYPE_PUSH,
            'provider_name' => 'web_push',
            'credentials' => [
                'vapid_public_key' => 'BN'.fake()->regexify('[A-Za-z0-9]{86}'),
                'vapid_private_key' => fake()->regexify('[A-Za-z0-9]{43}'),
            ],
        ]);
    }

    public function enabled(): static
    {
        return $this->state(['is_enabled' => true]);
    }

    public function disabled(): static
    {
        return $this->state(['is_enabled' => false]);
    }

    public function verified(): static
    {
        return $this->state([
            'is_verified' => true,
            'is_enabled' => true,
        ]);
    }

    public function unverified(): static
    {
        return $this->state(['is_verified' => false]);
    }

    public function configured(): static
    {
        return $this->email()->enabled()->verified();
    }

    public function forLandlord(User $landlord): static
    {
        return $this->state(['landlord_id' => $landlord->id]);
    }
}
