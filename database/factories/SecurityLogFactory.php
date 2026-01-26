<?php

namespace Database\Factories;

use App\Models\SecurityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class SecurityLogFactory extends Factory
{
    protected $model = SecurityLog::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory()->state(['role' => 'landlord']),
            'landlord_id' => fn (array $attrs) => $attrs['user_id'],
            'event_type' => SecurityLog::EVENT_LOGIN,
            'severity' => SecurityLog::SEVERITY_INFO,
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
            'url' => fake()->url(),
            'method' => 'POST',
            'description' => 'User logged in successfully',
            'metadata' => [],
            'session_id' => fake()->uuid(),
            'country' => fake()->country(),
            'city' => fake()->city(),
            'is_suspicious' => false,
        ];
    }

    public function login(): static
    {
        return $this->state([
            'event_type' => SecurityLog::EVENT_LOGIN,
            'severity' => SecurityLog::SEVERITY_INFO,
            'description' => 'User logged in successfully',
        ]);
    }

    public function logout(): static
    {
        return $this->state([
            'event_type' => SecurityLog::EVENT_LOGOUT,
            'severity' => SecurityLog::SEVERITY_INFO,
            'description' => 'User logged out',
        ]);
    }

    public function loginFailed(): static
    {
        return $this->state([
            'event_type' => SecurityLog::EVENT_LOGIN_FAILED,
            'severity' => SecurityLog::SEVERITY_WARNING,
            'description' => 'Login attempt failed - invalid credentials',
            'is_suspicious' => true,
        ]);
    }

    public function passwordChange(): static
    {
        return $this->state([
            'event_type' => SecurityLog::EVENT_PASSWORD_CHANGE,
            'severity' => SecurityLog::SEVERITY_INFO,
            'description' => 'User changed their password',
        ]);
    }

    public function passwordReset(): static
    {
        return $this->state([
            'event_type' => SecurityLog::EVENT_PASSWORD_RESET,
            'severity' => SecurityLog::SEVERITY_INFO,
            'description' => 'Password was reset via email link',
        ]);
    }

    public function twoFactorEnabled(): static
    {
        return $this->state([
            'event_type' => SecurityLog::EVENT_TWO_FACTOR_ENABLED,
            'severity' => SecurityLog::SEVERITY_INFO,
            'description' => 'Two-factor authentication enabled',
        ]);
    }

    public function twoFactorFailed(): static
    {
        return $this->state([
            'event_type' => SecurityLog::EVENT_TWO_FACTOR_FAILED,
            'severity' => SecurityLog::SEVERITY_WARNING,
            'description' => 'Two-factor authentication failed',
            'is_suspicious' => true,
        ]);
    }

    public function dataExport(): static
    {
        return $this->state([
            'event_type' => SecurityLog::EVENT_DATA_EXPORT,
            'severity' => SecurityLog::SEVERITY_INFO,
            'description' => 'User exported personal data',
            'metadata' => ['export_type' => 'full'],
        ]);
    }

    public function dataDelete(): static
    {
        return $this->state([
            'event_type' => SecurityLog::EVENT_DATA_DELETE,
            'severity' => SecurityLog::SEVERITY_WARNING,
            'description' => 'User requested account deletion',
        ]);
    }

    public function sensitiveDataAccess(): static
    {
        return $this->state([
            'event_type' => SecurityLog::EVENT_SENSITIVE_DATA_ACCESS,
            'severity' => SecurityLog::SEVERITY_INFO,
            'description' => 'User accessed sensitive data',
            'metadata' => [
                'data_type' => fake()->randomElement(['national_id', 'bank_details', 'personal_info']),
            ],
        ]);
    }

    public function accountLocked(): static
    {
        return $this->state([
            'event_type' => SecurityLog::EVENT_ACCOUNT_LOCKED,
            'severity' => SecurityLog::SEVERITY_ERROR,
            'description' => 'Account locked due to multiple failed login attempts',
            'is_suspicious' => true,
        ]);
    }

    public function suspicious(): static
    {
        return $this->state([
            'event_type' => SecurityLog::EVENT_SUSPICIOUS_ACTIVITY,
            'severity' => SecurityLog::SEVERITY_CRITICAL,
            'description' => 'Suspicious activity detected',
            'is_suspicious' => true,
        ]);
    }

    public function info(): static
    {
        return $this->state(['severity' => SecurityLog::SEVERITY_INFO]);
    }

    public function warning(): static
    {
        return $this->state(['severity' => SecurityLog::SEVERITY_WARNING]);
    }

    public function error(): static
    {
        return $this->state(['severity' => SecurityLog::SEVERITY_ERROR]);
    }

    public function critical(): static
    {
        return $this->state(['severity' => SecurityLog::SEVERITY_CRITICAL]);
    }

    public function forUser(User $user): static
    {
        return $this->state(['user_id' => $user->id]);
    }

    public function forLandlord(User $landlord): static
    {
        return $this->state(['landlord_id' => $landlord->id]);
    }

    public function fromIp(string $ip): static
    {
        return $this->state(['ip_address' => $ip]);
    }

    public function fromLocation(string $country, ?string $city = null): static
    {
        return $this->state([
            'country' => $country,
            'city' => $city,
        ]);
    }

    public function recent(): static
    {
        return $this->state(fn () => [
            'created_at' => now()->subHours(fake()->numberBetween(1, 24)),
        ]);
    }
}
