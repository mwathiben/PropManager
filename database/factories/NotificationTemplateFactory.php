<?php

namespace Database\Factories;

use App\Models\NotificationTemplate;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class NotificationTemplateFactory extends Factory
{
    protected $model = NotificationTemplate::class;

    public function definition(): array
    {
        $landlord = User::factory()->state(['role' => 'landlord'])->create();
        $type = fake()->randomElement([
            'rent_reminder',
            'arrears_notice',
            'invoice',
            'receipt',
            'general',
        ]);
        $name = ucfirst(str_replace('_', ' ', $type)).' Template';

        return [
            'landlord_id' => $landlord->id,
            'name' => $name,
            'slug' => Str::slug($name),
            'type' => $type,
            'subject' => $this->getDefaultSubject($type),
            'body' => $this->getDefaultBody($type),
            'available_placeholders' => array_keys(NotificationTemplate::getAllPlaceholders($type)),
            'is_default' => false,
            'is_active' => true,
        ];
    }

    private function getDefaultSubject(string $type): string
    {
        return match ($type) {
            'rent_reminder' => 'Rent Reminder for {{unit_number}}',
            'arrears_notice' => 'Outstanding Balance Notice',
            'invoice' => 'Invoice {{invoice_number}} - {{property_name}}',
            'receipt' => 'Payment Receipt {{receipt_number}}',
            'rent_hike' => 'Rent Adjustment Notice',
            'lease_expiry' => 'Lease Expiry Reminder',
            'lease_renewal' => 'Lease Renewal Offer',
            'eviction_notice' => 'Important: Eviction Notice',
            default => 'Notification from {{landlord_name}}',
        };
    }

    private function getDefaultBody(string $type): string
    {
        return match ($type) {
            'rent_reminder' => "Dear {{tenant_name}},\n\nThis is a reminder that your rent of {{rent_amount}} for unit {{unit_number}} is due on {{due_date}}.\n\nPlease ensure payment is made on time.\n\nBest regards,\n{{landlord_name}}",
            'arrears_notice' => "Dear {{tenant_name}},\n\nYou have an outstanding balance of {{arrears_amount}} which is {{days_overdue}} days overdue.\n\nPlease settle this balance as soon as possible.\n\nBest regards,\n{{landlord_name}}",
            'invoice' => "Dear {{tenant_name}},\n\nPlease find attached invoice {{invoice_number}} for {{total_amount}}.\n\nDue date: {{due_date}}\n\nView invoice: {{invoice_url}}\n\nBest regards,\n{{landlord_name}}",
            'receipt' => "Dear {{tenant_name}},\n\nThank you for your payment of {{payment_amount}} on {{payment_date}}.\n\nReceipt: {{receipt_number}}\nPayment method: {{payment_method}}\n\nBest regards,\n{{landlord_name}}",
            default => "Dear {{tenant_name}},\n\nThis is a notification from {{landlord_name}}.\n\nBest regards,\n{{landlord_name}}",
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

    public function default(): static
    {
        return $this->state(['is_default' => true]);
    }

    public function rentReminder(): static
    {
        return $this->state([
            'type' => 'rent_reminder',
            'name' => 'Rent Reminder Template',
            'slug' => 'rent-reminder-template',
            'subject' => $this->getDefaultSubject('rent_reminder'),
            'body' => $this->getDefaultBody('rent_reminder'),
            'available_placeholders' => array_keys(NotificationTemplate::getAllPlaceholders('rent_reminder')),
        ]);
    }

    public function arrearsNotice(): static
    {
        return $this->state([
            'type' => 'arrears_notice',
            'name' => 'Arrears Notice Template',
            'slug' => 'arrears-notice-template',
            'subject' => $this->getDefaultSubject('arrears_notice'),
            'body' => $this->getDefaultBody('arrears_notice'),
            'available_placeholders' => array_keys(NotificationTemplate::getAllPlaceholders('arrears_notice')),
        ]);
    }

    public function invoice(): static
    {
        return $this->state([
            'type' => 'invoice',
            'name' => 'Invoice Template',
            'slug' => 'invoice-template',
            'subject' => $this->getDefaultSubject('invoice'),
            'body' => $this->getDefaultBody('invoice'),
            'available_placeholders' => array_keys(NotificationTemplate::getAllPlaceholders('invoice')),
        ]);
    }

    public function receipt(): static
    {
        return $this->state([
            'type' => 'receipt',
            'name' => 'Receipt Template',
            'slug' => 'receipt-template',
            'subject' => $this->getDefaultSubject('receipt'),
            'body' => $this->getDefaultBody('receipt'),
            'available_placeholders' => array_keys(NotificationTemplate::getAllPlaceholders('receipt')),
        ]);
    }

    public function leaseExpiry(): static
    {
        return $this->state([
            'type' => 'lease_expiry',
            'name' => 'Lease Expiry Template',
            'slug' => 'lease-expiry-template',
            'subject' => $this->getDefaultSubject('lease_expiry'),
            'body' => $this->getDefaultBody('lease_expiry'),
            'available_placeholders' => array_keys(NotificationTemplate::getAllPlaceholders('lease_expiry')),
        ]);
    }

    public function forLandlord(User $landlord): static
    {
        return $this->state(['landlord_id' => $landlord->id]);
    }
}
