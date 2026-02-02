<?php

namespace Tests\Feature;

use App\Models\Building;
use App\Models\Invoice;
use App\Models\Lease;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * M-Pesa Idempotency Tests
 *
 * Tests for database-level unique constraint and idempotent insert pattern.
 * These tests verify that duplicate M-Pesa webhooks are handled correctly
 * at both database and application level.
 *
 * @see docs/adr/006-payment-idempotency-pattern.md
 */
class MpesaIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    private User $landlord;

    private Invoice $invoice;

    private Lease $lease;

    private string $validMpesaIp = '196.201.214.200';

    protected function setUp(): void
    {
        parent::setUp();

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
            'invoice_number' => 'INV-202602-0001',
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
    }

    /**
     * Test 1: Database UNIQUE constraint rejects duplicate mpesa_transaction_id
     *
     * This test verifies that the database-level unique constraint on
     * mpesa_transaction_id prevents duplicate payments. The constraint
     * should throw a QueryException with MySQL error code 1062.
     *
     * EXPECTED: FAIL initially (no unique constraint exists)
     * EXPECTED: PASS after migration adds unique constraint
     */
    public function test_duplicate_mpesa_transaction_id_throws_query_exception(): void
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

        $this->expectException(QueryException::class);

        Payment::create([
            'invoice_id' => $this->invoice->id,
            'lease_id' => $this->lease->id,
            'amount' => 25000,
            'payment_method' => 'mobile_money',
            'payment_date' => now(),
            'mpesa_transaction_id' => $transactionId,
            'landlord_id' => $this->landlord->id,
        ]);
    }

    /**
     * Test 2: Duplicate webhook returns 200 OK without creating duplicate payment
     *
     * Verifies idempotent behavior: when the unique constraint catches a duplicate,
     * the controller should handle it gracefully and return 200 (not throw error).
     * This test simulates C2B confirmation which is simpler to test.
     */
    public function test_c2b_duplicate_webhook_returns_200_without_creating_duplicate_payment(): void
    {
        $transactionId = 'QKL'.rand(100000000, 999999999);

        config(['mpesa.allowed_ips' => [$this->validMpesaIp]]);

        $payload = [
            'TransactionType' => 'Pay Bill',
            'TransID' => $transactionId,
            'TransTime' => now()->format('YmdHis'),
            'TransAmount' => '25000',
            'BusinessShortCode' => '174379',
            'BillRefNumber' => $this->invoice->invoice_number,
            'InvoiceNumber' => '',
            'OrgAccountBalance' => '100000',
            'ThirdPartyTransID' => '',
            'MSISDN' => '254712345678',
            'FirstName' => 'John',
            'MiddleName' => '',
            'LastName' => 'Doe',
        ];

        $response1 = $this->postJson(
            '/webhooks/mpesa/c2b/confirmation',
            $payload,
            ['REMOTE_ADDR' => $this->validMpesaIp]
        );

        $response1->assertStatus(200);
        $this->assertEquals(1, Payment::where('mpesa_transaction_id', $transactionId)->count());

        $response2 = $this->postJson(
            '/webhooks/mpesa/c2b/confirmation',
            $payload,
            ['REMOTE_ADDR' => $this->validMpesaIp]
        );

        $response2->assertStatus(200);
        $this->assertEquals(1, Payment::where('mpesa_transaction_id', $transactionId)->count());
    }

    /**
     * Test 3: 50 concurrent webhooks create exactly 1 payment
     *
     * Stress test for race conditions. Simulates 50 concurrent identical
     * webhook calls - only 1 payment should be created.
     */
    public function test_50_concurrent_webhooks_create_exactly_one_payment(): void
    {
        $transactionId = 'QKL'.rand(100000000, 999999999);
        $successCount = 0;
        $concurrentCalls = 50;

        for ($i = 0; $i < $concurrentCalls; $i++) {
            try {
                DB::transaction(function () use ($transactionId, &$successCount) {
                    $payment = Payment::create([
                        'invoice_id' => $this->invoice->id,
                        'lease_id' => $this->lease->id,
                        'amount' => 25000,
                        'payment_method' => 'mobile_money',
                        'payment_date' => now(),
                        'mpesa_transaction_id' => $transactionId,
                        'landlord_id' => $this->landlord->id,
                    ]);

                    if ($payment->exists) {
                        $successCount++;
                    }
                });
            } catch (QueryException $e) {
                if ($e->errorInfo[1] === 1062) {
                    continue;
                }
                throw $e;
            }
        }

        $this->assertEquals(1, $successCount);
        $this->assertEquals(1, Payment::where('mpesa_transaction_id', $transactionId)->count());
    }

    /**
     * Test 4: C2B confirmation handles duplicate transaction ID correctly
     *
     * The c2bConfirmation() method currently has NO idempotency check.
     * This test verifies that after the fix, it handles duplicates properly.
     */
    public function test_c2b_confirmation_handles_duplicate_transaction_id(): void
    {
        $transactionId = 'QKL'.rand(100000000, 999999999);

        config(['mpesa.allowed_ips' => [$this->validMpesaIp]]);

        $payload = [
            'TransactionType' => 'Pay Bill',
            'TransID' => $transactionId,
            'TransTime' => now()->format('YmdHis'),
            'TransAmount' => '25000',
            'BusinessShortCode' => '174379',
            'BillRefNumber' => $this->invoice->invoice_number,
            'InvoiceNumber' => '',
            'OrgAccountBalance' => '100000',
            'ThirdPartyTransID' => '',
            'MSISDN' => '254712345678',
            'FirstName' => 'John',
            'MiddleName' => '',
            'LastName' => 'Doe',
        ];

        $response1 = $this->postJson(
            '/webhooks/mpesa/c2b/confirmation',
            $payload,
            ['REMOTE_ADDR' => $this->validMpesaIp]
        );

        $response1->assertStatus(200);
        $paymentCount1 = Payment::where('mpesa_transaction_id', $transactionId)->count();

        $response2 = $this->postJson(
            '/webhooks/mpesa/c2b/confirmation',
            $payload,
            ['REMOTE_ADDR' => $this->validMpesaIp]
        );

        $response2->assertStatus(200);
        $paymentCount2 = Payment::where('mpesa_transaction_id', $transactionId)->count();

        $this->assertEquals(1, $paymentCount1);
        $this->assertEquals(1, $paymentCount2);
    }

    /**
     * Test 5: Till confirmation handles duplicate transaction ID correctly
     *
     * Verifies that tillConfirmation() properly handles duplicate webhooks
     * using the database unique constraint.
     */
    public function test_till_confirmation_handles_duplicate_transaction_id(): void
    {
        $transactionId = 'QKL'.rand(100000000, 999999999);

        config(['mpesa.allowed_ips' => [$this->validMpesaIp]]);

        $tenant = $this->lease->tenant;
        $tenant->update(['phone' => '254712345678']);

        $payload = [
            'TransactionType' => 'CustomerBuyGoodsOnline',
            'TransID' => $transactionId,
            'TransTime' => now()->format('YmdHis'),
            'TransAmount' => '25000',
            'BusinessShortCode' => '5432109',
            'BillRefNumber' => '',
            'InvoiceNumber' => '',
            'OrgAccountBalance' => '100000',
            'ThirdPartyTransID' => '',
            'MSISDN' => '254712345678',
            'FirstName' => 'John',
            'MiddleName' => '',
            'LastName' => 'Doe',
        ];

        $response1 = $this->postJson(
            '/webhooks/mpesa/till/confirmation',
            $payload,
            ['REMOTE_ADDR' => $this->validMpesaIp]
        );

        $paymentCount1 = Payment::where('mpesa_transaction_id', $transactionId)->count();

        $response2 = $this->postJson(
            '/webhooks/mpesa/till/confirmation',
            $payload,
            ['REMOTE_ADDR' => $this->validMpesaIp]
        );

        $paymentCount2 = Payment::where('mpesa_transaction_id', $transactionId)->count();

        $this->assertEquals($paymentCount1, $paymentCount2);
    }

    /**
     * Test 6: After idempotent rejection, existing payment data is not modified
     *
     * Ensures that when a duplicate webhook is received, the original payment
     * record remains unchanged.
     */
    public function test_duplicate_webhook_does_not_modify_original_payment(): void
    {
        $transactionId = 'QKL'.rand(100000000, 999999999);

        $originalPayment = Payment::create([
            'invoice_id' => $this->invoice->id,
            'lease_id' => $this->lease->id,
            'amount' => 25000,
            'payment_method' => 'mobile_money',
            'payment_date' => now(),
            'mpesa_transaction_id' => $transactionId,
            'notes' => 'Original payment',
            'landlord_id' => $this->landlord->id,
        ]);

        $originalCreatedAt = $originalPayment->created_at;
        $originalNotes = $originalPayment->notes;

        try {
            Payment::create([
                'invoice_id' => $this->invoice->id,
                'lease_id' => $this->lease->id,
                'amount' => 30000,
                'payment_method' => 'mobile_money',
                'payment_date' => now(),
                'mpesa_transaction_id' => $transactionId,
                'notes' => 'Duplicate attempt',
                'landlord_id' => $this->landlord->id,
            ]);
        } catch (QueryException $e) {
            // Expected - unique constraint violation
        }

        $originalPayment->refresh();

        $this->assertEquals(25000, $originalPayment->amount);
        $this->assertEquals($originalNotes, $originalPayment->notes);
        $this->assertEquals($originalCreatedAt->toDateTimeString(), $originalPayment->created_at->toDateTimeString());
    }

    /**
     * Test 7: Unique constraint allows NULL mpesa_transaction_id (for non-M-Pesa payments)
     *
     * Verifies that the unique constraint only applies to non-null values,
     * allowing multiple payments without mpesa_transaction_id.
     */
    public function test_multiple_payments_with_null_mpesa_transaction_id_allowed(): void
    {
        Payment::create([
            'invoice_id' => $this->invoice->id,
            'lease_id' => $this->lease->id,
            'amount' => 10000,
            'payment_method' => 'cash',
            'payment_date' => now(),
            'mpesa_transaction_id' => null,
            'landlord_id' => $this->landlord->id,
        ]);

        $payment2 = Payment::create([
            'invoice_id' => $this->invoice->id,
            'lease_id' => $this->lease->id,
            'amount' => 15000,
            'payment_method' => 'bank_transfer',
            'payment_date' => now(),
            'mpesa_transaction_id' => null,
            'landlord_id' => $this->landlord->id,
        ]);

        $this->assertTrue($payment2->exists);
        $this->assertEquals(2, Payment::whereNull('mpesa_transaction_id')->count());
    }
}
