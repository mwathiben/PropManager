<?php

namespace Database\Factories;

use App\Models\NotificationPreference;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class NotificationPreferenceFactory extends Factory
{
    protected $model = NotificationPreference::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory()->state(['role' => 'tenant']),
            'landlord_id' => User::factory()->state(['role' => 'landlord']),
            'rent_reminder_enabled' => true,
            'arrears_notice_enabled' => true,
            'invoice_enabled' => true,
            'receipt_enabled' => true,
            'rent_hike_enabled' => true,
            'lease_expiry_enabled' => true,
            'lease_renewal_enabled' => true,
            'maintenance_notice_enabled' => true,
            'general_enabled' => true,
            'eviction_notice_enabled' => true,
            'caretaker_invitation_enabled' => true,
            'tenant_invitation_enabled' => true,
            'email_enabled' => true,
            'sms_enabled' => false,
            'whatsapp_enabled' => false,
            'push_enabled' => true,
            'in_app_enabled' => true,
            'rent_reminder_days_before' => 3,
            'preferred_time' => '09:00',
            'whatsapp_number' => null,
            'quiet_hours_enabled' => false,
            'quiet_hours_start' => '22:00',
            'quiet_hours_end' => '08:00',
        ];
    }

    public function allTypesEnabled(): static
    {
        return $this->state([
            'rent_reminder_enabled' => true,
            'arrears_notice_enabled' => true,
            'invoice_enabled' => true,
            'receipt_enabled' => true,
            'rent_hike_enabled' => true,
            'lease_expiry_enabled' => true,
            'lease_renewal_enabled' => true,
            'maintenance_notice_enabled' => true,
            'general_enabled' => true,
            'eviction_notice_enabled' => true,
            'caretaker_invitation_enabled' => true,
            'tenant_invitation_enabled' => true,
        ]);
    }

    public function allTypesDisabled(): static
    {
        return $this->state([
            'rent_reminder_enabled' => false,
            'arrears_notice_enabled' => false,
            'invoice_enabled' => false,
            'receipt_enabled' => false,
            'rent_hike_enabled' => false,
            'lease_expiry_enabled' => false,
            'lease_renewal_enabled' => false,
            'maintenance_notice_enabled' => false,
            'general_enabled' => false,
            'eviction_notice_enabled' => false,
            'caretaker_invitation_enabled' => false,
            'tenant_invitation_enabled' => false,
        ]);
    }

    public function emailOnly(): static
    {
        return $this->state([
            'email_enabled' => true,
            'sms_enabled' => false,
            'whatsapp_enabled' => false,
            'push_enabled' => false,
            'in_app_enabled' => true,
        ]);
    }

    public function smsOnly(): static
    {
        return $this->state([
            'email_enabled' => false,
            'sms_enabled' => true,
            'whatsapp_enabled' => false,
            'push_enabled' => false,
            'in_app_enabled' => true,
        ]);
    }

    public function allChannels(): static
    {
        return $this->state([
            'email_enabled' => true,
            'sms_enabled' => true,
            'whatsapp_enabled' => true,
            'push_enabled' => true,
            'in_app_enabled' => true,
            'whatsapp_number' => '+254712345678',
        ]);
    }

    public function noChannels(): static
    {
        return $this->state([
            'email_enabled' => false,
            'sms_enabled' => false,
            'whatsapp_enabled' => false,
            'push_enabled' => false,
            'in_app_enabled' => true,
        ]);
    }

    public function withQuietHours(): static
    {
        return $this->state([
            'quiet_hours_enabled' => true,
            'quiet_hours_start' => '22:00',
            'quiet_hours_end' => '08:00',
        ]);
    }

    public function withCustomQuietHours(string $start, string $end): static
    {
        return $this->state([
            'quiet_hours_enabled' => true,
            'quiet_hours_start' => $start,
            'quiet_hours_end' => $end,
        ]);
    }

    public function noQuietHours(): static
    {
        return $this->state(['quiet_hours_enabled' => false]);
    }

    public function withWhatsApp(string $number = '+254712345678'): static
    {
        return $this->state([
            'whatsapp_enabled' => true,
            'whatsapp_number' => $number,
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
