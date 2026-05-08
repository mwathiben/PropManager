<?php

namespace Database\Factories;

use App\Models\Notification;
use App\Models\NotificationSchedule;
use App\Models\NotificationTemplate;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class NotificationScheduleFactory extends Factory
{
    protected $model = NotificationSchedule::class;

    public function definition(): array
    {
        $type = fake()->randomElement(['rent_reminder', 'arrears_notice', 'lease_expiry']);

        return [
            'landlord_id' => User::factory()->state(['role' => 'landlord']),
            'name' => ucfirst(str_replace('_', ' ', $type)).' Schedule',
            'type' => $type,
            'trigger' => $this->getTriggerForType($type),
            'days_offset' => fake()->numberBetween(1, 7),
            'send_time' => fake()->randomElement(['08:00', '09:00', '10:00', '14:00', '16:00']),
            'channels' => [Notification::CHANNEL_EMAIL],
            'template_id' => null,
            'is_active' => true,
            'last_run_at' => null,
        ];
    }

    private function getTriggerForType(string $type): string
    {
        return match ($type) {
            'rent_reminder' => 'days_before_due',
            'arrears_notice' => 'days_after_overdue',
            'lease_expiry' => 'days_before_expiry',
            default => 'days_before_due',
        };
    }

    public function active(): static
    {
        return $this->state(['is_active' => true]);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }

    public function daysBeforeDue(int $days = 3): static
    {
        return $this->state([
            'trigger' => 'days_before_due',
            'days_offset' => $days,
        ]);
    }

    public function daysAfterOverdue(int $days = 7): static
    {
        return $this->state([
            'trigger' => 'days_after_overdue',
            'days_offset' => $days,
        ]);
    }

    public function daysBeforeExpiry(int $days = 30): static
    {
        return $this->state([
            'trigger' => 'days_before_expiry',
            'days_offset' => $days,
        ]);
    }

    public function rentReminder(): static
    {
        return $this->state([
            'type' => 'rent_reminder',
            'name' => 'Rent Reminder Schedule',
            'trigger' => 'days_before_due',
            'days_offset' => 3,
        ]);
    }

    public function arrearsNotice(): static
    {
        return $this->state([
            'type' => 'arrears_notice',
            'name' => 'Arrears Notice Schedule',
            'trigger' => 'days_after_overdue',
            'days_offset' => 7,
        ]);
    }

    public function leaseExpiry(): static
    {
        return $this->state([
            'type' => 'lease_expiry',
            'name' => 'Lease Expiry Schedule',
            'trigger' => 'days_before_expiry',
            'days_offset' => 30,
        ]);
    }

    public function withEmailAndSms(): static
    {
        return $this->state([
            'channels' => [Notification::CHANNEL_EMAIL, Notification::CHANNEL_SMS],
        ]);
    }

    public function withAllChannels(): static
    {
        return $this->state([
            'channels' => [
                Notification::CHANNEL_EMAIL,
                Notification::CHANNEL_SMS,
                Notification::CHANNEL_WHATSAPP,
                Notification::CHANNEL_PUSH,
            ],
        ]);
    }

    public function withTemplate(NotificationTemplate $template): static
    {
        return $this->state([
            'template_id' => $template->id,
            'landlord_id' => $template->landlord_id,
        ]);
    }

    public function lastRan(): static
    {
        return $this->state([
            'last_run_at' => now()->subHours(fake()->numberBetween(1, 24)),
        ]);
    }

    public function forLandlord(User $landlord): static
    {
        return $this->state(['landlord_id' => $landlord->id]);
    }
}
