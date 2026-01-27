<?php

namespace Tests\Traits;

use App\Models\Building;
use App\Models\Invitation;
use App\Models\Invoice;
use App\Models\Lease;
use App\Models\NotificationPreference;
use App\Models\Payment;
use App\Models\PaymentLink;
use App\Models\Property;
use App\Models\TenantMessage;
use App\Models\Ticket;
use App\Models\Unit;
use App\Models\User;
use App\Models\WaterReading;

trait CreatesTestData
{
    protected function createLandlordWithFullSetup(): array
    {
        $landlord = User::factory()->create(['role' => 'landlord']);

        $property = Property::create([
            'name' => 'Test Property',
            'address' => '123 Test St',
            'type' => 'apartment',
            'landlord_id' => $landlord->id,
        ]);

        $building = Building::create([
            'property_id' => $property->id,
            'name' => 'Block A',
            'total_floors' => 2,
            'units_per_floor' => 4,
            'landlord_id' => $landlord->id,
            'building_type' => 'residential_apartment',
        ]);

        $units = collect();
        for ($floor = 1; $floor <= 2; $floor++) {
            for ($num = 1; $num <= 4; $num++) {
                $units->push(Unit::create([
                    'building_id' => $building->id,
                    'unit_number' => "A{$floor}0{$num}",
                    'floor_number' => $floor,
                    'status' => 'vacant',
                    'target_rent' => 25000,
                    'landlord_id' => $landlord->id,
                ]));
            }
        }

        return compact('landlord', 'property', 'building', 'units');
    }

    protected function createTenantWithActiveLease(User $landlord, Unit $unit): array
    {
        $tenant = User::factory()->create([
            'role' => 'tenant',
            'landlord_id' => $landlord->id,
        ]);

        $lease = Lease::create([
            'unit_id' => $unit->id,
            'tenant_id' => $tenant->id,
            'landlord_id' => $landlord->id,
            'rent_amount' => $unit->target_rent,
            'deposit_amount' => $unit->target_rent,
            'start_date' => now(),
            'is_active' => true,
            'wallet_balance' => 0,
        ]);

        $unit->update(['status' => 'occupied']);

        return compact('tenant', 'lease');
    }

    protected function createInvoiceForLease(Lease $lease, string $status = 'sent'): Invoice
    {
        $invoiceNumber = 'INV-'.date('Ym').'-'.str_pad(random_int(1, 9999), 4, '0', STR_PAD_LEFT);

        return Invoice::create([
            'lease_id' => $lease->id,
            'landlord_id' => $lease->landlord_id,
            'invoice_number' => $invoiceNumber,
            'rent_due' => $lease->rent_amount,
            'water_due' => 0,
            'arrears' => 0,
            'wallet_applied' => 0,
            'total_due' => $lease->rent_amount,
            'amount_paid' => $status === 'paid' ? $lease->rent_amount : 0,
            'status' => $status,
            'due_date' => now()->addDays(7),
            'billing_period_start' => now()->startOfMonth(),
        ]);
    }

    protected function createWaterReadingForUnit(Unit $unit, float $consumption = 10): WaterReading
    {
        $previousReading = 1000;
        $currentReading = $previousReading + $consumption;

        return WaterReading::create([
            'unit_id' => $unit->id,
            'landlord_id' => $unit->landlord_id,
            'reading_date' => now(),
            'previous_reading' => $previousReading,
            'current_reading' => $currentReading,
            'consumption' => $consumption,
            'cost' => $consumption * 150,
            'status' => 'pending',
            'is_invoiced' => false,
        ]);
    }

    protected function createCaretakerForLandlord(User $landlord, ?Building $building = null): User
    {
        $caretaker = User::factory()->create([
            'role' => 'caretaker',
            'landlord_id' => $landlord->id,
        ]);

        if ($building) {
            $building->update(['caretaker_id' => $caretaker->id]);
        }

        return $caretaker;
    }

    protected function createPaymentWithInvoice(Lease $lease, float $amount = 5000): array
    {
        $invoice = $this->createInvoiceForLease($lease, 'partial');
        $payment = Payment::create([
            'invoice_id' => $invoice->id,
            'lease_id' => $lease->id,
            'landlord_id' => $lease->landlord_id,
            'amount' => $amount,
            'payment_method' => 'mpesa',
            'reference' => 'PAY-'.uniqid(),
            'payment_date' => now(),
        ]);

        return compact('payment', 'invoice');
    }

    protected function createTenantMessage(User $landlord, User $tenant, array $attributes = []): TenantMessage
    {
        return TenantMessage::create(array_merge([
            'landlord_id' => $landlord->id,
            'user_id' => $tenant->id,
            'twilio_message_sid' => 'SM'.bin2hex(random_bytes(16)),
            'from_number' => $tenant->mobile_number ?? '+254712345678',
            'body' => 'Test message from tenant',
            'source' => TenantMessage::SOURCE_WHATSAPP,
            'status' => TenantMessage::STATUS_RECEIVED,
        ], $attributes));
    }

    protected function createNotificationPreference(User $user, User $landlord, array $attributes = []): NotificationPreference
    {
        return NotificationPreference::create(array_merge([
            'user_id' => $user->id,
            'landlord_id' => $landlord->id,
            'email_enabled' => true,
            'whatsapp_enabled' => true,
            'whatsapp_number' => '+254712345678',
            'sms_enabled' => false,
            'push_enabled' => false,
            'quiet_hours_enabled' => false,
        ], $attributes));
    }

    protected function createPaymentLink(Invoice $invoice, array $attributes = []): PaymentLink
    {
        return PaymentLink::create(array_merge([
            'token' => PaymentLink::generateToken(),
            'invoice_id' => $invoice->id,
            'landlord_id' => $invoice->landlord_id,
            'expires_at' => now()->addDays(30),
        ], $attributes));
    }

    protected function createTicketWithStatus(User $landlord, Building $building, User $reporter, string $status = 'open'): Ticket
    {
        return Ticket::create([
            'landlord_id' => $landlord->id,
            'building_id' => $building->id,
            'reporter_id' => $reporter->id,
            'category' => 'issue',
            'subcategory' => 'plumbing',
            'title' => 'Test Ticket',
            'description' => 'Test description',
            'priority' => 'medium',
            'status' => $status,
        ]);
    }

    protected function createInvitation(User $landlord, Property $property, array $attributes = []): Invitation
    {
        return Invitation::create(array_merge([
            'landlord_id' => $landlord->id,
            'property_id' => $property->id,
            'email' => 'caretaker@example.com',
            'token' => bin2hex(random_bytes(32)),
            'expires_at' => now()->addDays(30),
        ], $attributes));
    }
}
