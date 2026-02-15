<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Mail\PaymentVerificationApproved;
use App\Models\Payment;
use App\Models\TenantPaymentVerification;
use App\Services\Payment\InitialPaymentCallbackHandler;
use App\Services\Payment\InitialPaymentResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

class InitialPaymentCallbackHandlerTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    protected InitialPaymentCallbackHandler $handler;

    protected array $setupData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->handler = app(InitialPaymentCallbackHandler::class);
        $this->setupData = $this->createLandlordWithFullSetup();
        Mail::fake();
    }

    private function createVerification(array $leaseData, array $overrides = []): TenantPaymentVerification
    {
        $defaults = [
            'lease_id' => $leaseData['lease']->id,
            'landlord_id' => $this->setupData['landlord']->id,
            'status' => TenantPaymentVerification::STATUS_PENDING_PAYMENT,
            'deposit_required' => 25000,
            'first_rent_required' => 25000,
            'other_charges' => 0,
            'total_required' => 50000,
            'amount_paid' => 0,
        ];

        return TenantPaymentVerification::create(array_merge($defaults, $overrides));
    }

    private function makePaystackData(string $prefix, int $amountKobo, string $channel = 'card'): array
    {
        return [
            'reference' => $prefix.'_'.uniqid(),
            'amount' => $amountKobo,
            'channel' => $channel,
        ];
    }

    public function test_successful_payment_creates_payment_record(): void
    {
        $unit = $this->setupData['units']->first();
        $leaseData = $this->createTenantWithActiveLease($this->setupData['landlord'], $unit);

        $verification = $this->createVerification($leaseData);
        $data = $this->makePaystackData('PSK_SUCCESS', 5000000);

        $result = $this->handler->process($data, ['verification_id' => $verification->id]);

        $this->assertInstanceOf(InitialPaymentResult::class, $result);
        $this->assertTrue($result->isSuccess());
        $this->assertNotNull($result->payment);
        $this->assertDatabaseHas('payments', [
            'lease_id' => $leaseData['lease']->id,
            'landlord_id' => $this->setupData['landlord']->id,
            'amount' => 50000,
            'payment_method' => 'paystack',
            'paystack_reference' => $data['reference'],
        ]);
    }

    public function test_returns_not_found_when_verification_missing(): void
    {
        $data = $this->makePaystackData('PSK_MISSING', 100000);

        $result = $this->handler->process($data, ['verification_id' => 99999]);

        $this->assertEquals(InitialPaymentResult::STATUS_NOT_FOUND, $result->status);
        $this->assertFalse($result->isSuccess());
        $this->assertEquals('tenant.payment-required', $result->redirectRoute());
        $this->assertEquals('error', $result->flashType());
    }

    public function test_returns_already_verified_when_verification_complete(): void
    {
        $unit = $this->setupData['units']->first();
        $leaseData = $this->createTenantWithActiveLease($this->setupData['landlord'], $unit);

        $verification = $this->createVerification($leaseData, [
            'status' => TenantPaymentVerification::STATUS_PAYMENT_VERIFIED,
            'amount_paid' => 50000,
            'verified_at' => now(),
            'verified_by' => $this->setupData['landlord']->id,
        ]);

        $data = $this->makePaystackData('PSK_VERIFIED', 100000);

        $result = $this->handler->process($data, ['verification_id' => $verification->id]);

        $this->assertEquals(InitialPaymentResult::STATUS_ALREADY_VERIFIED, $result->status);
        $this->assertEquals('dashboard', $result->redirectRoute());
        $this->assertEquals('info', $result->flashType());
    }

    public function test_returns_duplicate_when_reference_exists(): void
    {
        $unit = $this->setupData['units']->first();
        $leaseData = $this->createTenantWithActiveLease($this->setupData['landlord'], $unit);

        $verification = $this->createVerification($leaseData);
        $reference = 'PSK_DUP_'.uniqid();

        Payment::create([
            'landlord_id' => $this->setupData['landlord']->id,
            'lease_id' => $leaseData['lease']->id,
            'amount' => 50000,
            'payment_method' => 'paystack',
            'payment_date' => now(),
            'reference' => $reference,
            'paystack_reference' => $reference,
        ]);

        $data = ['reference' => $reference, 'amount' => 5000000, 'channel' => 'card'];

        $result = $this->handler->process($data, ['verification_id' => $verification->id]);

        $this->assertEquals(InitialPaymentResult::STATUS_DUPLICATE, $result->status);
        $this->assertEquals('info', $result->flashType());
    }

    public function test_converts_kobo_to_kes(): void
    {
        $unit = $this->setupData['units']->first();
        $leaseData = $this->createTenantWithActiveLease($this->setupData['landlord'], $unit);

        $verification = $this->createVerification($leaseData, ['total_required' => 100000]);
        $data = $this->makePaystackData('PSK_KOBO', 5000000);

        $result = $this->handler->process($data, ['verification_id' => $verification->id]);

        $this->assertTrue($result->isSuccess());
        $this->assertEquals(50000.0, $result->amount);
        $this->assertDatabaseHas('payments', [
            'paystack_reference' => $data['reference'],
            'amount' => 50000,
        ]);
    }

    public function test_auto_approves_when_fully_paid(): void
    {
        $unit = $this->setupData['units']->first();
        $leaseData = $this->createTenantWithActiveLease($this->setupData['landlord'], $unit);

        $verification = $this->createVerification($leaseData, [
            'total_required' => 50000,
            'amount_paid' => 0,
        ]);

        $data = $this->makePaystackData('PSK_FULL', 5000000);

        $result = $this->handler->process($data, ['verification_id' => $verification->id]);

        $this->assertTrue($result->isSuccess());
        $this->assertTrue($result->isVerified);

        $verification->refresh();
        $this->assertEquals(TenantPaymentVerification::STATUS_PAYMENT_VERIFIED, $verification->status);
        $this->assertNotNull($verification->verified_at);
        $this->assertNull($verification->verified_by);
    }

    public function test_sends_approval_email_on_auto_approval(): void
    {
        $unit = $this->setupData['units']->first();
        $leaseData = $this->createTenantWithActiveLease($this->setupData['landlord'], $unit);

        $verification = $this->createVerification($leaseData, [
            'total_required' => 30000,
            'deposit_required' => 15000,
            'first_rent_required' => 15000,
        ]);

        $data = $this->makePaystackData('PSK_EMAIL', 3000000);

        $this->handler->process($data, ['verification_id' => $verification->id]);

        Mail::assertQueued(PaymentVerificationApproved::class, function ($mail) use ($leaseData) {
            return $mail->hasTo($leaseData['tenant']->email);
        });
    }

    public function test_partial_payment_does_not_auto_approve(): void
    {
        $unit = $this->setupData['units']->first();
        $leaseData = $this->createTenantWithActiveLease($this->setupData['landlord'], $unit);

        $verification = $this->createVerification($leaseData, [
            'total_required' => 100000,
            'deposit_required' => 50000,
            'first_rent_required' => 50000,
        ]);

        $data = $this->makePaystackData('PSK_PARTIAL', 3000000);

        $result = $this->handler->process($data, ['verification_id' => $verification->id]);

        $this->assertTrue($result->isSuccess());
        $this->assertFalse($result->isVerified);

        $verification->refresh();
        $this->assertEquals(TenantPaymentVerification::STATUS_PENDING_PAYMENT, $verification->status);
        $this->assertNull($verification->verified_at);

        Mail::assertNotQueued(PaymentVerificationApproved::class);
    }

    public function test_records_payment_amount_on_verification(): void
    {
        $unit = $this->setupData['units']->first();
        $leaseData = $this->createTenantWithActiveLease($this->setupData['landlord'], $unit);

        $verification = $this->createVerification($leaseData, [
            'total_required' => 80000,
            'amount_paid' => 20000,
        ]);

        $data = $this->makePaystackData('PSK_RECORD', 1500000);

        $this->handler->process($data, ['verification_id' => $verification->id]);

        $verification->refresh();
        $this->assertEquals(35000.00, (float) $verification->amount_paid);
    }

    public function test_returns_error_when_reference_missing(): void
    {
        $unit = $this->setupData['units']->first();
        $leaseData = $this->createTenantWithActiveLease($this->setupData['landlord'], $unit);

        $verification = $this->createVerification($leaseData);

        $data = ['amount' => 5000000];

        $result = $this->handler->process($data, ['verification_id' => $verification->id]);

        $this->assertEquals(InitialPaymentResult::STATUS_ERROR, $result->status);
        $this->assertEquals('error', $result->flashType());
    }

    public function test_creates_receipt_for_payment(): void
    {
        $unit = $this->setupData['units']->first();
        $leaseData = $this->createTenantWithActiveLease($this->setupData['landlord'], $unit);

        $verification = $this->createVerification($leaseData);
        $data = $this->makePaystackData('PSK_RECEIPT', 5000000);

        $result = $this->handler->process($data, ['verification_id' => $verification->id]);

        $this->assertTrue($result->isSuccess());
        $this->assertDatabaseHas('receipts', [
            'payment_id' => $result->payment->id,
            'landlord_id' => $this->setupData['landlord']->id,
            'amount' => 50000,
        ]);
    }

    public function test_success_message_includes_amount(): void
    {
        $result = InitialPaymentResult::success(
            payment: new Payment(['amount' => 50000]),
            verification: new TenantPaymentVerification,
            amount: 50000,
            isVerified: false,
        );

        $this->assertStringContainsString('50,000.00', $result->successMessage());
        $this->assertStringContainsString('KSh', $result->successMessage());
    }
}
