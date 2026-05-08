<?php

namespace Database\Factories;

use App\Models\NotificationDefaults;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class NotificationDefaultsFactory extends Factory
{
    protected $model = NotificationDefaults::class;

    public function definition(): array
    {
        return array_merge(
            ['landlord_id' => User::factory()->state(['role' => 'landlord'])],
            NotificationDefaults::getDefaultAttributes()
        );
    }

    public function withQuietHours(): static
    {
        return $this->state([
            'quiet_hours_enabled' => true,
            'quiet_hours_start' => '22:00',
            'quiet_hours_end' => '08:00',
            'quiet_hours_queue_notifications' => true,
        ]);
    }

    public function noQuietHours(): static
    {
        return $this->state([
            'quiet_hours_enabled' => false,
            'quiet_hours_queue_notifications' => false,
        ]);
    }

    public function highVolume(): static
    {
        return $this->state([
            'max_retries' => 5,
            'retry_delay_minutes' => 2,
            'daily_limit_per_tenant' => 50,
            'hourly_limit_per_tenant' => 10,
        ]);
    }

    public function lowVolume(): static
    {
        return $this->state([
            'max_retries' => 2,
            'retry_delay_minutes' => 10,
            'daily_limit_per_tenant' => 10,
            'hourly_limit_per_tenant' => 3,
        ]);
    }

    public function allChannels(): static
    {
        return $this->state([
            'default_channels' => ['email', 'sms', 'whatsapp', 'push', 'in_app'],
        ]);
    }

    public function emailOnly(): static
    {
        return $this->state([
            'default_channels' => ['email'],
        ]);
    }

    public function allTypesEnabled(): static
    {
        return $this->state([
            'type_settings' => NotificationDefaults::DEFAULT_TYPE_SETTINGS,
        ]);
    }

    public function minimalTypes(): static
    {
        return $this->state([
            'type_settings' => [
                'rent_reminder' => true,
                'arrears_notice' => true,
                'invoice' => true,
                'receipt' => true,
                'rent_hike' => false,
                'lease_expiry' => false,
                'lease_renewal' => false,
                'maintenance_notice' => false,
                'general' => false,
                'eviction_notice' => true,
                'caretaker_invitation' => true,
                'tenant_invitation' => true,
            ],
        ]);
    }

    public function withSender(string $name, ?string $email = null): static
    {
        return $this->state([
            'sender_name' => $name,
            'reply_to_email' => $email,
        ]);
    }

    public function withArchiveDays(int $days): static
    {
        return $this->state(['archive_days' => $days]);
    }

    public function trackingDisabled(): static
    {
        return $this->state(['track_read_status' => false]);
    }

    public function forLandlord(User $landlord): static
    {
        return $this->state(['landlord_id' => $landlord->id]);
    }
}
