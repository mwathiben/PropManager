<?php

namespace Database\Factories;

use App\Models\TenantInvitation;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TenantInvitationFactory extends Factory
{
    protected $model = TenantInvitation::class;

    public function definition(): array
    {
        $landlord = User::factory()->state(['role' => 'landlord']);
        $rentAmount = fake()->numberBetween(15000, 50000);

        return [
            'landlord_id' => $landlord,
            'initiated_by' => fn (array $attrs) => $attrs['landlord_id'],
            'unit_id' => Unit::factory(),
            'email' => fake()->unique()->safeEmail(),
            'existing_user_id' => null,
            'token' => TenantInvitation::generateToken(),
            'rent_amount' => $rentAmount,
            'service_charge' => fake()->boolean(30) ? fake()->numberBetween(500, 2000) : 0,
            'deposit_amount' => $rentAmount,
            'start_date' => now()->addDays(fake()->numberBetween(7, 30)),
            'end_date' => null,
            'tenant_name' => fake()->name(),
            'tenant_phone' => fake()->phoneNumber(),
            'tenant_id_number' => fake()->optional(0.5)->numerify('########'),
            'notification_channels' => ['email'],
            'email_sent_at' => null,
            'sms_sent_at' => null,
            'whatsapp_sent_at' => null,
            'status' => 'pending',
            'accepted_at' => null,
            'expires_at' => now()->addDays(7),
            'viewed_at' => null,
        ];
    }

    public function pending(): static
    {
        return $this->state([
            'status' => 'pending',
            'accepted_at' => null,
            'expires_at' => now()->addDays(7),
        ]);
    }

    public function accepted(): static
    {
        return $this->state([
            'status' => 'accepted',
            'accepted_at' => now(),
        ]);
    }

    public function declined(): static
    {
        return $this->state([
            'status' => 'declined',
        ]);
    }

    public function expired(): static
    {
        return $this->state([
            'status' => 'pending',
            'expires_at' => now()->subDay(),
        ]);
    }

    public function viewed(): static
    {
        return $this->state([
            'viewed_at' => now(),
        ]);
    }

    public function forExistingUser(User $user): static
    {
        return $this->state([
            'existing_user_id' => $user->id,
            'email' => $user->email,
            'tenant_name' => $user->name,
        ]);
    }

    public function withEmailSent(): static
    {
        return $this->state([
            'email_sent_at' => now(),
        ]);
    }

    public function withSmsSent(): static
    {
        return $this->state([
            'sms_sent_at' => now(),
            'notification_channels' => ['email', 'sms'],
        ]);
    }

    public function withWhatsAppSent(): static
    {
        return $this->state([
            'whatsapp_sent_at' => now(),
            'notification_channels' => ['email', 'whatsapp'],
        ]);
    }

    public function withAllChannels(): static
    {
        return $this->state([
            'notification_channels' => ['email', 'sms', 'whatsapp'],
            'email_sent_at' => now(),
            'sms_sent_at' => now(),
            'whatsapp_sent_at' => now(),
        ]);
    }

    public function forLandlord(User $landlord): static
    {
        return $this->state([
            'landlord_id' => $landlord->id,
            'initiated_by' => $landlord->id,
        ]);
    }

    public function forUnit(Unit $unit): static
    {
        return $this->state([
            'unit_id' => $unit->id,
            'landlord_id' => $unit->landlord_id,
        ]);
    }

    public function initiatedBy(User $user): static
    {
        return $this->state([
            'initiated_by' => $user->id,
        ]);
    }

    public function withLeaseTerm(int $months): static
    {
        return $this->state(function (array $attrs) use ($months) {
            $startDate = $attrs['start_date'] ?? now()->addDays(7);

            return [
                'start_date' => $startDate,
                'end_date' => $startDate->copy()->addMonths($months),
            ];
        });
    }
}
