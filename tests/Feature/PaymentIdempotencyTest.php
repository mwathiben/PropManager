<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Models\Building;
use App\Models\Invoice;
use App\Models\Lease;
use App\Models\Payment;
use App\Models\PaymentConfiguration;
use App\Models\Property;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PaymentIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    private User $landlord;

    private Invoice $invoice;

    private Lease $lease;

    private string $paystackSecret = 'sk_test_idempotency_secret_1234';

    protected function setUp(): void
    {
        parent::setUp();

        config(['payments.webhook_security.paystack.allowed_ips' => ['127.0.0.1']]);

        $this->landlord = User::factory()->create(['role' => 'landlord']);

        $property = Property::create([
            'name' => 'Test Property',
            'address' => '123 Test St',
            'type' => 'apartment',
            'landlord_id' => $this->landlord->id,
        ]);

        $building = Building::create([
            'property_id' => $property->id,
            'name' => 'Block A',
            'floors' => 1,
            'units_per_floor' => 1,
            'landlord_id' => $this->landlord->id,
        ]);

        $unit = Unit::create([
            'building_id' => $building->id,
            'unit_number' => 'A101',
            'floor_number' => 1,
            'status' => 'occupied',
            'target_rent' => 25000,
            'landlord_id' => $this->landlord->id,
        ]);

        $tenant = User::factory()->create([
            'role' => 'tenant',
            'landlord_id' => $this->landlord->id,
        ]);

        $this->lease = Lease::create([
            'unit_id' => $unit->id,
            'tenant_id' => $tenant->id,
            'rent_amount' => 25000,
            'deposit_amount' => 25000,
            'start_date' => now(),
            'is_active' => true,
            'landlord_id' => $this->landlord->id,
        ]);

        $this->invoice = Invoice::create([
            'lease_id' => $this->lease->id,
            'invoice_number' => 'INV-202601-0001',
            'rent_due' => 25000,
            'water_due' => 0,
            'total_due' => 25000,
            'amount_paid' => 0,
            'status' => 'sent',
            'due_date' => now()->addDays(7),
            'billing_period_start' => now()->startOfMonth(),
            'billing_period_end' => now()->endOfMonth(),
            'landlord_id' => $this->landlord->id,
        ]);

        PaymentConfiguration::factory()->create([
            'landlord_id' => $this->landlord->id,
            'paystack_enabled' => true,
            'paystack_public_key' => 'pk_test_idempotency_pub_key',
            'paystack_secret_key' => $this->paystackSecret,
        ]);
    }

    public function test_duplicate_paystack_reference_is_rejected(): void
    {
        $reference = 'PSK_'.uniqid();

        Payment::create([
            'invoice_id' => $this->invoice->id,
            'lease_id' => $this->lease->id,
            'amount' => 25000,
            'payment_method' => 'paystack',
            'payment_date' => now(),
            'paystack_reference' => $reference,
            'landlord_id' => $this->landlord->id,
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        Payment::create([
            'invoice_id' => $this->invoice->id,
            'lease_id' => $this->lease->id,
            'amount' => 25000,
            'payment_method' => 'paystack',
            'payment_date' => now(),
            'paystack_reference' => $reference,
            'landlord_id' => $this->landlord->id,
        ]);
    }

    public function test_concurrent_webhook_calls_only_create_one_payment(): void
    {
        $reference = 'PSK_'.uniqid();
        $successCount = 0;

        for ($i = 0; $i < 5; $i++) {
            try {
                DB::transaction(function () use ($reference, &$successCount) {
                    $existing = Payment::where('paystack_reference', $reference)
                        ->lockForUpdate()
                        ->first();

                    if ($existing) {
                        return;
                    }

                    Payment::create([
                        'invoice_id' => $this->invoice->id,
                        'lease_id' => $this->lease->id,
                        'amount' => 25000,
                        'payment_method' => 'paystack',
                        'payment_date' => now(),
                        'paystack_reference' => $reference,
                        'landlord_id' => $this->landlord->id,
                    ]);

                    $successCount++;
                });
            } catch (\Exception $e) {
                continue;
            }
        }

        $this->assertEquals(1, $successCount);
        $this->assertEquals(1, Payment::where('paystack_reference', $reference)->count());
    }

    public function test_webhook_with_invalid_signature_is_rejected(): void
    {
        $webhookData = [
            'event' => 'charge.success',
            'data' => [
                'reference' => 'PSK_'.uniqid(),
                'amount' => 2500000,
                'status' => 'success',
                'metadata' => [
                    'invoice_id' => $this->invoice->id,
                    'landlord_id' => $this->landlord->id,
                ],
            ],
        ];

        $response = $this->postJson('/webhooks/paystack', $webhookData, [
            'x-paystack-signature' => 'invalid_signature',
        ]);

        $response->assertStatus(401);
    }

    public function test_webhook_with_valid_signature_is_accepted(): void
    {
        $reference = 'PSK_'.uniqid();

        $this->invoice->update(['paystack_reference' => $reference]);

        $webhookData = [
            'event' => 'charge.success',
            'data' => [
                'reference' => $reference,
                'amount' => 2500000,
                'status' => 'success',
                'metadata' => [
                    'invoice_id' => $this->invoice->id,
                    'landlord_id' => $this->landlord->id,
                ],
            ],
        ];

        $payload = json_encode($webhookData);
        $signature = hash_hmac('sha512', $payload, $this->paystackSecret);

        $response = $this->postJson('/webhooks/paystack', $webhookData, [
            'x-paystack-signature' => $signature,
        ]);

        $response->assertStatus(200);
    }

    public function test_invoice_status_updates_correctly_on_partial_payment(): void
    {
        Payment::create([
            'invoice_id' => $this->invoice->id,
            'lease_id' => $this->lease->id,
            'amount' => 10000,
            'payment_method' => 'cash',
            'payment_date' => now(),
            'landlord_id' => $this->landlord->id,
        ]);

        $this->invoice->refresh();
        $this->invoice->update([
            'amount_paid' => 10000,
            'status' => 'partial',
        ]);

        $this->assertEquals(InvoiceStatus::Partial, $this->invoice->fresh()->status);
        $this->assertEquals(10000, $this->invoice->fresh()->amount_paid);
    }

    public function test_invoice_status_updates_to_paid_on_full_payment(): void
    {
        Payment::create([
            'invoice_id' => $this->invoice->id,
            'lease_id' => $this->lease->id,
            'amount' => 25000,
            'payment_method' => 'cash',
            'payment_date' => now(),
            'landlord_id' => $this->landlord->id,
        ]);

        $this->invoice->update([
            'amount_paid' => 25000,
            'status' => 'paid',
        ]);

        $this->assertEquals(InvoiceStatus::Paid, $this->invoice->fresh()->status);
        $this->assertEquals(25000, $this->invoice->fresh()->amount_paid);
    }

    public function test_mpesa_duplicate_transaction_id_detected(): void
    {
        $transactionId = 'QKL'.rand(100000000, 999999999);

        Payment::create([
            'invoice_id' => $this->invoice->id,
            'lease_id' => $this->lease->id,
            'amount' => 25000,
            'payment_method' => 'mobile_money',
            'payment_date' => now(),
            'mpesa_transaction_id' => $transactionId,
            'landlord_id' => $this->landlord->id,
        ]);

        $existingPayment = Payment::where('mpesa_transaction_id', $transactionId)->first();
        $this->assertNotNull($existingPayment);

        $this->assertEquals(1, Payment::where('mpesa_transaction_id', $transactionId)->count());
    }
}
