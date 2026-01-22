<?php

namespace Database\Factories;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class NotificationFactory extends Factory
{
    protected $model = Notification::class;

    public function definition(): array
    {
        $landlord = User::factory()->state(['role' => 'landlord'])->create();
        $recipient = User::factory()->state(['role' => 'tenant'])->create();
        $type = fake()->randomElement([
            Notification::TYPE_RENT_REMINDER,
            Notification::TYPE_ARREARS_NOTICE,
            Notification::TYPE_INVOICE,
            Notification::TYPE_RECEIPT,
            Notification::TYPE_GENERAL,
        ]);

        return [
            'landlord_id' => $landlord->id,
            'recipient_id' => $recipient->id,
            'type' => $type,
            'urgency' => Notification::getUrgencyForType($type),
            'channel' => Notification::CHANNEL_EMAIL,
            'fallback_channel' => null,
            'subject' => fake()->sentence(4),
            'message' => fake()->paragraph(),
            'data' => [],
            'status' => 'pending',
            'external_id' => null,
            'error_message' => null,
            'delivery_reason_code' => null,
            'retry_count' => 0,
            'sent_at' => null,
            'delivered_at' => null,
            'read_at' => null,
            'fallback_sent_at' => null,
            'timeout_at' => null,
            'primary_attempt_at' => null,
            'scheduled_for' => null,
            'quiet_hours_suppressed' => false,
        ];
    }

    public function pending(): static
    {
        return $this->state(['status' => 'pending']);
    }

    public function sent(): static
    {
        return $this->state([
            'status' => 'sent',
            'sent_at' => now(),
            'external_id' => 'MSG-'.strtoupper(fake()->bothify('????????')),
        ]);
    }

    public function delivered(): static
    {
        return $this->state([
            'status' => 'delivered',
            'sent_at' => now()->subMinutes(5),
            'delivered_at' => now(),
            'external_id' => 'MSG-'.strtoupper(fake()->bothify('????????')),
        ]);
    }

    public function read(): static
    {
        return $this->state([
            'status' => 'read',
            'sent_at' => now()->subHours(1),
            'delivered_at' => now()->subMinutes(30),
            'read_at' => now(),
            'external_id' => 'MSG-'.strtoupper(fake()->bothify('????????')),
        ]);
    }

    public function failed(): static
    {
        return $this->state([
            'status' => 'failed',
            'error_message' => fake()->randomElement([
                'Recipient not found',
                'Connection timeout',
                'Invalid phone number',
                'Message rejected',
            ]),
        ]);
    }

    public function email(): static
    {
        return $this->state(['channel' => Notification::CHANNEL_EMAIL]);
    }

    public function sms(): static
    {
        return $this->state(['channel' => Notification::CHANNEL_SMS]);
    }

    public function whatsapp(): static
    {
        return $this->state([
            'channel' => Notification::CHANNEL_WHATSAPP,
            'timeout_at' => now()->addMinutes(60),
        ]);
    }

    public function push(): static
    {
        return $this->state(['channel' => Notification::CHANNEL_PUSH]);
    }

    public function inApp(): static
    {
        return $this->state(['channel' => Notification::CHANNEL_IN_APP]);
    }

    public function rentReminder(): static
    {
        return $this->state([
            'type' => Notification::TYPE_RENT_REMINDER,
            'urgency' => Notification::URGENCY_IMPORTANT,
            'subject' => 'Rent Reminder',
            'data' => [
                'rent_amount' => fake()->numberBetween(10000, 50000),
                'due_date' => now()->addDays(3)->toDateString(),
            ],
        ]);
    }

    public function arrearsNotice(): static
    {
        return $this->state([
            'type' => Notification::TYPE_ARREARS_NOTICE,
            'urgency' => Notification::URGENCY_URGENT,
            'subject' => 'Outstanding Balance Notice',
            'data' => [
                'arrears_amount' => fake()->numberBetween(5000, 100000),
                'days_overdue' => fake()->numberBetween(1, 30),
            ],
        ]);
    }

    public function invoice(): static
    {
        return $this->state([
            'type' => Notification::TYPE_INVOICE,
            'urgency' => Notification::URGENCY_IMPORTANT,
            'subject' => 'New Invoice',
            'data' => [
                'invoice_number' => 'INV-'.date('Ym').'-'.str_pad(fake()->numberBetween(1, 9999), 4, '0', STR_PAD_LEFT),
                'total_amount' => fake()->numberBetween(10000, 50000),
            ],
        ]);
    }

    public function receipt(): static
    {
        return $this->state([
            'type' => Notification::TYPE_RECEIPT,
            'urgency' => Notification::URGENCY_INFORMATIONAL,
            'subject' => 'Payment Receipt',
            'data' => [
                'receipt_number' => 'RCP-'.date('Ym').'-'.str_pad(fake()->numberBetween(1, 9999), 4, '0', STR_PAD_LEFT),
                'payment_amount' => fake()->numberBetween(10000, 50000),
            ],
        ]);
    }

    public function leaseExpiry(): static
    {
        return $this->state([
            'type' => Notification::TYPE_LEASE_EXPIRY,
            'urgency' => Notification::URGENCY_URGENT,
            'subject' => 'Lease Expiry Notice',
            'data' => [
                'expiry_date' => now()->addDays(30)->toDateString(),
                'days_until_expiry' => 30,
            ],
        ]);
    }

    public function caretakerInvitation(): static
    {
        return $this->state([
            'type' => Notification::TYPE_CARETAKER_INVITATION,
            'urgency' => Notification::URGENCY_IMPORTANT,
            'subject' => 'Caretaker Invitation',
            'data' => [
                'invitation_id' => fake()->numberBetween(1, 100),
                'property_name' => fake()->company().' Apartments',
            ],
        ]);
    }

    public function tenantInvitation(): static
    {
        return $this->state([
            'type' => Notification::TYPE_TENANT_INVITATION,
            'urgency' => Notification::URGENCY_IMPORTANT,
            'subject' => 'Tenant Invitation',
            'data' => [
                'invitation_id' => fake()->numberBetween(1, 100),
                'unit_number' => strtoupper(fake()->randomLetter()).fake()->numberBetween(101, 999),
            ],
        ]);
    }

    public function scheduled(): static
    {
        return $this->state([
            'scheduled_for' => now()->addHours(fake()->numberBetween(1, 24)),
        ]);
    }

    public function suppressedByQuietHours(): static
    {
        return $this->state([
            'quiet_hours_suppressed' => true,
            'scheduled_for' => now()->addHours(8),
        ]);
    }

    public function forLandlord(User $landlord): static
    {
        return $this->state(['landlord_id' => $landlord->id]);
    }

    public function forRecipient(User $recipient): static
    {
        return $this->state(['recipient_id' => $recipient->id]);
    }

    public function withFallback(string $fallbackChannel): static
    {
        return $this->state([
            'fallback_channel' => $fallbackChannel,
            'fallback_sent_at' => now(),
        ]);
    }
}
