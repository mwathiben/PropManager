<?php

namespace Tests\Feature;

use App\Models\IntaSendTransaction;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentConfiguration;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * IntaSend Idempotency Tests
 *
 * Tests for database-level unique constraint and idempotent insert pattern.
 * These tests verify that duplicate IntaSend webhooks are handled correctly
 * at both database and application level.
 *
 * @see docs/adr/006-payment-idempotency-pattern.md
 */
class IntaSendIdempotencyTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    private User $landlord;

    private Invoice $invoice;

    private PaymentConfiguration $paymentConfig;

    private string $webhookChallenge = 'test_challenge_secret';

    protected function setUp(): void
    {
        parent::setUp();

        $setupData = $this->createLandlordWithFullSetup();
        $this->landlord = $setupData['landlord'];

        $unit = $setupData['units']->first();
        ['lease' => $lease] = $this->createTenantWithActiveLease($this->landlord, $unit);
        $this->invoice = $this->createInvoiceForLease($lease, 'sent');

        $this->paymentConfig = PaymentConfiguration::create([
            'landlord_id' => $this->landlord->id,
            'intasend_enabled' => true,
            'intasend_publishable_key' => 'ISPubKey_test_12345',
            'intasend_secret_key' => 'ISSecretKey_test_12345',
            'intasend_webhook_challenge' => $this->webhookChallenge,
            'intasend_environment' => 'sandbox',
            'accepted_payment_methods' => ['mobile_money'],
        ]);

        Mail::fake();
        Event::fake();
    }

    /**
     * Test 1: Database UNIQUE constraint rejects duplicate intasend_reference
     *
     * This test verifies that the database-level unique constraint on
     * intasend_reference prevents duplicate payments. The constraint
     * should throw a QueryException with MySQL error code 1062.
     *
     * EXPECTED: FAIL initially (no unique constraint exists)
     * EXPECTED: PASS after migration adds unique constraint
     */
    public function test_duplicate_intasend_reference_throws_query_exception(): void
    {
        $reference = 'ITS-'.time().'-'.uniqid();

        Payment::create([
            'invoice_id' => $this->invoice->id,
            'lease_id' => $this->invoice->lease_id,
            'amount' => 15000,
            'payment_method' => 'mobile_money',
            'payment_date' => now(),
            'intasend_reference' => $reference,
            'landlord_id' => $this->landlord->id,
        ]);

        $this->expectException(QueryException::class);

        Payment::create([
            'invoice_id' => $this->invoice->id,
            'lease_id' => $this->invoice->lease_id,
            'amount' => 15000,
            'payment_method' => 'mobile_money',
            'payment_date' => now(),
            'intasend_reference' => $reference,
            'landlord_id' => $this->landlord->id,
        ]);
    }

    /**
     * Test 2: Duplicate webhook returns 200 OK without creating duplicate payment
     *
     * Verifies idempotent behavior: when the unique constraint catches a duplicate,
     * the controller should handle it gracefully and return 200 (not throw error).
     */
    public function test_duplicate_intasend_webhook_returns_200_without_creating_duplicate_payment(): void
    {
        $transaction = IntaSendTransaction::factory()->forInvoice($this->invoice)->create([
            'amount' => 15000,
            'state' => IntaSendTransaction::STATE_PENDING,
        ]);

        $payload = $this->createWebhookPayload($transaction, 'COMPLETE');

        $response1 = $this->postJson('/api/webhooks/intasend/mpesa', $payload);
        $response1->assertOk();
        $this->assertEquals(1, Payment::where('intasend_reference', $transaction->api_ref)->count());

        $response2 = $this->postJson('/api/webhooks/intasend/mpesa', $payload);
        $response2->assertOk();
        $this->assertEquals(1, Payment::where('intasend_reference', $transaction->api_ref)->count());
    }

    /**
     * Test 3: 50 concurrent webhooks create exactly 1 payment
     *
     * Stress test for race conditions. Uses parallel processes to simulate
     * truly concurrent identical payment insert attempts - only 1 should succeed.
     * Falls back to sequential test if pcntl extension is not available.
     */
    public function test_50_concurrent_intasend_webhooks_create_exactly_one_payment(): void
    {
        $reference = 'ITS-'.time().'-'.uniqid();
        $concurrentCalls = 50;

        if (function_exists('pcntl_fork')) {
            $this->runConcurrentForkTest($reference, $concurrentCalls);
        } else {
            $this->runParallelConnectionTest($reference, $concurrentCalls);
        }

        $this->assertEquals(1, Payment::where('intasend_reference', $reference)->count());
    }

    /**
     * Run concurrent test using pcntl_fork for true parallelism.
     */
    private function runConcurrentForkTest(string $reference, int $concurrentCalls): void
    {
        $pids = [];
        $successFile = tempnam(sys_get_temp_dir(), 'intasend_test_');
        file_put_contents($successFile, '0');

        for ($i = 0; $i < $concurrentCalls; $i++) {
            $pid = pcntl_fork();

            if ($pid === -1) {
                $this->fail('Could not fork process');
            } elseif ($pid === 0) {
                try {
                    DB::reconnect();
                    DB::transaction(function () use ($reference) {
                        Payment::create([
                            'invoice_id' => $this->invoice->id,
                            'lease_id' => $this->invoice->lease_id,
                            'amount' => 15000,
                            'payment_method' => 'mobile_money',
                            'payment_date' => now(),
                            'intasend_reference' => $reference,
                            'landlord_id' => $this->landlord->id,
                        ]);
                    });
                    $fp = fopen($successFile, 'c+');
                    flock($fp, LOCK_EX);
                    $count = (int) fread($fp, 10);
                    fseek($fp, 0);
                    fwrite($fp, (string) ($count + 1));
                    flock($fp, LOCK_UN);
                    fclose($fp);
                    exit(0);
                } catch (QueryException $e) {
                    if ($e->errorInfo[1] === 1062) {
                        exit(0);
                    }
                    exit(1);
                }
            } else {
                $pids[] = $pid;
            }
        }

        foreach ($pids as $pid) {
            pcntl_waitpid($pid, $status);
        }

        $successCount = (int) file_get_contents($successFile);
        unlink($successFile);
        $this->assertEquals(1, $successCount, 'Exactly one process should successfully create the payment');
    }

    /**
     * Fallback: Run parallel test using rapid sequential attempts.
     */
    private function runParallelConnectionTest(string $reference, int $concurrentCalls): void
    {
        $successCount = 0;

        for ($i = 0; $i < $concurrentCalls; $i++) {
            try {
                DB::transaction(function () use ($reference, &$successCount) {
                    Payment::create([
                        'invoice_id' => $this->invoice->id,
                        'lease_id' => $this->invoice->lease_id,
                        'amount' => 15000,
                        'payment_method' => 'mobile_money',
                        'payment_date' => now(),
                        'intasend_reference' => $reference,
                        'landlord_id' => $this->landlord->id,
                    ]);
                    $successCount++;
                });
            } catch (QueryException $e) {
                if ($e->errorInfo[1] === 1062) {
                    continue;
                }
                throw $e;
            }
        }

        $this->assertEquals(1, $successCount, 'Exactly one attempt should succeed');
    }

    /**
     * Test 4: processCompletePayment handles QueryException(1062) gracefully
     *
     * This test verifies that after the QueryException handler is added,
     * the controller returns 200 OK when a duplicate is caught by the constraint.
     */
    public function test_process_complete_payment_handles_duplicate_reference(): void
    {
        $transaction = IntaSendTransaction::factory()->forInvoice($this->invoice)->create([
            'amount' => 15000,
            'state' => IntaSendTransaction::STATE_PENDING,
        ]);

        $payload = $this->createWebhookPayload($transaction, 'COMPLETE');

        $response1 = $this->postJson('/api/webhooks/intasend/mpesa', $payload);
        $response1->assertOk();

        $paymentCount1 = Payment::where('intasend_reference', $transaction->api_ref)->count();

        $transaction2 = IntaSendTransaction::factory()->forInvoice($this->invoice)->create([
            'api_ref' => $transaction->api_ref,
            'amount' => 15000,
            'state' => IntaSendTransaction::STATE_PENDING,
        ]);

        $payload2 = $this->createWebhookPayload($transaction2, 'COMPLETE');

        $response2 = $this->postJson('/api/webhooks/intasend/mpesa', $payload2);
        $response2->assertOk();

        $paymentCount2 = Payment::where('intasend_reference', $transaction->api_ref)->count();

        $this->assertEquals(1, $paymentCount1);
        $this->assertEquals(1, $paymentCount2);
    }

    /**
     * Test 5: Multiple payments with NULL intasend_reference allowed
     *
     * Verifies that the unique constraint only applies to non-null values,
     * allowing multiple payments without intasend_reference (e.g., cash, bank).
     */
    public function test_multiple_payments_with_null_intasend_reference_allowed(): void
    {
        Payment::create([
            'invoice_id' => $this->invoice->id,
            'lease_id' => $this->invoice->lease_id,
            'amount' => 5000,
            'payment_method' => 'cash',
            'payment_date' => now(),
            'intasend_reference' => null,
            'landlord_id' => $this->landlord->id,
        ]);

        $payment2 = Payment::create([
            'invoice_id' => $this->invoice->id,
            'lease_id' => $this->invoice->lease_id,
            'amount' => 10000,
            'payment_method' => 'bank_transfer',
            'payment_date' => now(),
            'intasend_reference' => null,
            'landlord_id' => $this->landlord->id,
        ]);

        $this->assertTrue($payment2->exists);
        $this->assertEquals(2, Payment::whereNull('intasend_reference')
            ->where('invoice_id', $this->invoice->id)
            ->count());
    }

    /**
     * Test 6: Duplicate webhook does not modify original payment data
     *
     * Ensures that when a duplicate webhook is received, the original payment
     * record remains unchanged.
     */
    public function test_duplicate_intasend_webhook_does_not_modify_original_payment(): void
    {
        $reference = 'ITS-'.time().'-'.uniqid();

        $originalPayment = Payment::create([
            'invoice_id' => $this->invoice->id,
            'lease_id' => $this->invoice->lease_id,
            'amount' => 15000,
            'payment_method' => 'mobile_money',
            'payment_date' => now(),
            'intasend_reference' => $reference,
            'notes' => 'Original payment',
            'landlord_id' => $this->landlord->id,
        ]);

        $originalCreatedAt = $originalPayment->created_at;
        $originalNotes = $originalPayment->notes;

        try {
            Payment::create([
                'invoice_id' => $this->invoice->id,
                'lease_id' => $this->invoice->lease_id,
                'amount' => 20000,
                'payment_method' => 'mobile_money',
                'payment_date' => now(),
                'intasend_reference' => $reference,
                'notes' => 'Duplicate attempt',
                'landlord_id' => $this->landlord->id,
            ]);
        } catch (QueryException $e) {
            // Expected - unique constraint violation
        }

        $originalPayment->refresh();

        $this->assertEquals(15000, $originalPayment->amount);
        $this->assertEquals($originalNotes, $originalPayment->notes);
        $this->assertEquals($originalCreatedAt->toDateTimeString(), $originalPayment->created_at->toDateTimeString());
    }

    protected function createWebhookPayload(IntaSendTransaction $transaction, string $state = 'COMPLETE'): array
    {
        return [
            'invoice_id' => $transaction->intasend_invoice_id,
            'state' => $state,
            'provider' => 'M-PESA',
            'charges' => '0.00',
            'net_amount' => (string) $transaction->amount,
            'currency' => 'KES',
            'value' => (string) $transaction->amount,
            'account' => $transaction->phone_number,
            'api_ref' => $transaction->api_ref,
            'mpesa_reference' => 'QKL'.rand(100000000, 999999999),
            'failed_reason' => null,
            'challenge' => $this->webhookChallenge,
        ];
    }
}
