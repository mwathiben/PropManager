<?php

namespace Database\Factories;

use App\Models\Notification;
use App\Models\TenantMessage;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TenantMessageFactory extends Factory
{
    protected $model = TenantMessage::class;

    public function definition(): array
    {
        $landlord = User::factory()->state(['role' => 'landlord'])->create();
        $user = User::factory()->state(['role' => 'tenant'])->create();

        return [
            'landlord_id' => $landlord->id,
            'user_id' => $user->id,
            'notification_id' => null,
            'ticket_id' => null,
            'twilio_message_sid' => 'SM'.fake()->regexify('[a-f0-9]{32}'),
            'from_number' => '+254'.fake()->numerify('#########'),
            'body' => fake()->sentence(),
            'media_urls' => null,
            'source' => TenantMessage::SOURCE_WHATSAPP,
            'status' => TenantMessage::STATUS_RECEIVED,
            'action_type' => null,
            'metadata' => [],
        ];
    }

    public function whatsapp(): static
    {
        return $this->state(['source' => TenantMessage::SOURCE_WHATSAPP]);
    }

    public function sms(): static
    {
        return $this->state(['source' => TenantMessage::SOURCE_SMS]);
    }

    public function received(): static
    {
        return $this->state(['status' => TenantMessage::STATUS_RECEIVED]);
    }

    public function processed(): static
    {
        return $this->state(['status' => TenantMessage::STATUS_PROCESSED]);
    }

    public function actionTaken(): static
    {
        return $this->state([
            'status' => TenantMessage::STATUS_ACTION_TAKEN,
            'action_type' => fake()->randomElement([
                TenantMessage::ACTION_YES,
                TenantMessage::ACTION_NO,
                TenantMessage::ACTION_HELP,
            ]),
        ]);
    }

    public function ignored(): static
    {
        return $this->state(['status' => TenantMessage::STATUS_IGNORED]);
    }

    public function yesResponse(): static
    {
        return $this->state([
            'body' => 'Yes',
            'status' => TenantMessage::STATUS_ACTION_TAKEN,
            'action_type' => TenantMessage::ACTION_YES,
        ]);
    }

    public function noResponse(): static
    {
        return $this->state([
            'body' => 'No',
            'status' => TenantMessage::STATUS_ACTION_TAKEN,
            'action_type' => TenantMessage::ACTION_NO,
        ]);
    }

    public function helpRequest(): static
    {
        return $this->state([
            'body' => 'Help',
            'status' => TenantMessage::STATUS_ACTION_TAKEN,
            'action_type' => TenantMessage::ACTION_HELP,
        ]);
    }

    public function issueReport(): static
    {
        return $this->state([
            'body' => 'Issue: '.fake()->sentence(),
            'status' => TenantMessage::STATUS_ACTION_TAKEN,
            'action_type' => TenantMessage::ACTION_ISSUE,
        ]);
    }

    public function paymentInquiry(): static
    {
        return $this->state([
            'body' => 'Payment '.fake()->sentence(3),
            'status' => TenantMessage::STATUS_ACTION_TAKEN,
            'action_type' => TenantMessage::ACTION_PAYMENT,
        ]);
    }

    public function withMedia(): static
    {
        return $this->state([
            'media_urls' => [
                'https://example.com/media/'.fake()->uuid().'.jpg',
            ],
        ]);
    }

    public function forNotification(Notification $notification): static
    {
        return $this->state([
            'notification_id' => $notification->id,
            'landlord_id' => $notification->landlord_id,
            'user_id' => $notification->recipient_id,
        ]);
    }

    public function forTicket(Ticket $ticket): static
    {
        return $this->state([
            'ticket_id' => $ticket->id,
            'landlord_id' => $ticket->landlord_id,
        ]);
    }

    public function forUser(User $user): static
    {
        return $this->state(['user_id' => $user->id]);
    }

    public function forLandlord(User $landlord): static
    {
        return $this->state(['landlord_id' => $landlord->id]);
    }
}
