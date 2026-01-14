<?php

namespace Tests\Feature;

use App\Mail\OverpaymentNotification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

class PostCommitNotificationTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    protected User $landlord;

    protected array $setupData;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->setupData = $this->createLandlordWithFullSetup();
        $this->landlord = $this->setupData['landlord'];
    }

    public function test_overpayment_notification_is_queued_after_successful_payment(): void
    {
        $unit = $this->setupData['units']->first();
        ['lease' => $lease] = $this->createTenantWithActiveLease($this->landlord, $unit);
        $invoice = $this->createInvoiceForLease($lease, 'sent');

        $overpaymentAmount = 5000;
        $totalPayment = $invoice->total_due + $overpaymentAmount;

        $this->actingAs($this->landlord)
            ->post(route('invoices.recordPayment', $invoice), [
                'amount' => $totalPayment,
                'payment_method' => 'cash',
                'reference' => 'TEST-REF-001',
            ]);

        Mail::assertQueued(OverpaymentNotification::class, function ($mail) use ($overpaymentAmount) {
            return $mail->overpaymentAmount === (float) $overpaymentAmount;
        });
    }

    public function test_overpayment_notification_sent_to_landlord(): void
    {
        $unit = $this->setupData['units']->first();
        ['lease' => $lease] = $this->createTenantWithActiveLease($this->landlord, $unit);
        $invoice = $this->createInvoiceForLease($lease, 'sent');

        $totalPayment = $invoice->total_due + 3000;

        $this->actingAs($this->landlord)
            ->post(route('invoices.recordPayment', $invoice), [
                'amount' => $totalPayment,
                'payment_method' => 'bank_transfer',
                'reference' => 'BANK-REF-001',
            ]);

        Mail::assertQueued(OverpaymentNotification::class, function ($mail) {
            return $mail->hasTo($this->landlord->email);
        });
    }

    public function test_overpayment_notification_contains_correct_wallet_balance(): void
    {
        $unit = $this->setupData['units']->first();
        ['lease' => $lease] = $this->createTenantWithActiveLease($this->landlord, $unit);
        $invoice = $this->createInvoiceForLease($lease, 'sent');

        $overpaymentAmount = 7500;
        $totalPayment = $invoice->total_due + $overpaymentAmount;

        $this->actingAs($this->landlord)
            ->post(route('invoices.recordPayment', $invoice), [
                'amount' => $totalPayment,
                'payment_method' => 'cash',
            ]);

        $lease->refresh();

        Mail::assertQueued(OverpaymentNotification::class, function ($mail) use ($overpaymentAmount) {
            return $mail->newWalletBalance === (float) $overpaymentAmount;
        });

        $this->assertEquals($overpaymentAmount, $lease->wallet_balance);
    }

    public function test_no_overpayment_notification_when_exact_payment(): void
    {
        $unit = $this->setupData['units']->first();
        ['lease' => $lease] = $this->createTenantWithActiveLease($this->landlord, $unit);
        $invoice = $this->createInvoiceForLease($lease, 'sent');

        $this->actingAs($this->landlord)
            ->post(route('invoices.recordPayment', $invoice), [
                'amount' => $invoice->total_due,
                'payment_method' => 'cash',
            ]);

        Mail::assertNotQueued(OverpaymentNotification::class);
    }

    public function test_no_overpayment_notification_when_partial_payment(): void
    {
        $unit = $this->setupData['units']->first();
        ['lease' => $lease] = $this->createTenantWithActiveLease($this->landlord, $unit);
        $invoice = $this->createInvoiceForLease($lease, 'sent');

        $partialAmount = $invoice->total_due * 0.5;

        $this->actingAs($this->landlord)
            ->post(route('invoices.recordPayment', $invoice), [
                'amount' => $partialAmount,
                'payment_method' => 'cash',
            ]);

        Mail::assertNotQueued(OverpaymentNotification::class);
    }

    public function test_overpayment_notification_uses_payment_reference_in_subject(): void
    {
        $unit = $this->setupData['units']->first();
        ['lease' => $lease] = $this->createTenantWithActiveLease($this->landlord, $unit);
        $invoice = $this->createInvoiceForLease($lease, 'sent');

        $paymentReference = 'UNIQUE-REF-'.time();
        $totalPayment = $invoice->total_due + 2000;

        $this->actingAs($this->landlord)
            ->post(route('invoices.recordPayment', $invoice), [
                'amount' => $totalPayment,
                'payment_method' => 'cash',
                'reference' => $paymentReference,
            ]);

        Mail::assertQueued(OverpaymentNotification::class, function ($mail) use ($paymentReference) {
            // Verify payment model has reference
            if ($mail->payment->reference !== $paymentReference) {
                return false;
            }

            // Verify the email subject contains the payment reference
            $envelope = $mail->envelope();

            return str_contains($envelope->subject, $paymentReference);
        });
    }

    public function test_overpayment_notification_includes_tenant_info(): void
    {
        $unit = $this->setupData['units']->first();
        ['lease' => $lease, 'tenant' => $tenant] = $this->createTenantWithActiveLease($this->landlord, $unit);
        $invoice = $this->createInvoiceForLease($lease, 'sent');

        $totalPayment = $invoice->total_due + 1000;

        $this->actingAs($this->landlord)
            ->post(route('invoices.recordPayment', $invoice), [
                'amount' => $totalPayment,
                'payment_method' => 'cash',
            ]);

        Mail::assertQueued(OverpaymentNotification::class, function ($mail) use ($tenant) {
            return $mail->tenant->id === $tenant->id;
        });
    }

    public function test_overpayment_notification_includes_lease_info(): void
    {
        $unit = $this->setupData['units']->first();
        ['lease' => $lease] = $this->createTenantWithActiveLease($this->landlord, $unit);
        $invoice = $this->createInvoiceForLease($lease, 'sent');

        $totalPayment = $invoice->total_due + 1500;

        $this->actingAs($this->landlord)
            ->post(route('invoices.recordPayment', $invoice), [
                'amount' => $totalPayment,
                'payment_method' => 'cash',
            ]);

        Mail::assertQueued(OverpaymentNotification::class, function ($mail) use ($lease) {
            return $mail->lease->id === $lease->id;
        });
    }

    public function test_multiple_overpayments_send_multiple_notifications(): void
    {
        $units = $this->setupData['units']->take(2);
        $invoices = [];

        foreach ($units as $unit) {
            ['lease' => $lease] = $this->createTenantWithActiveLease($this->landlord, $unit);
            $invoices[] = $this->createInvoiceForLease($lease, 'sent');
        }

        foreach ($invoices as $invoice) {
            $totalPayment = $invoice->total_due + 1000;
            $this->actingAs($this->landlord)
                ->post(route('invoices.recordPayment', $invoice), [
                    'amount' => $totalPayment,
                    'payment_method' => 'cash',
                ]);
        }

        Mail::assertQueued(OverpaymentNotification::class, 2);
    }
}
