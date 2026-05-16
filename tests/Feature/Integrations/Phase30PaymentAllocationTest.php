<?php

declare(strict_types=1);

namespace Tests\Feature\Integrations;

use App\Events\PaymentAllocated;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentPlan;
use App\Models\PaymentPlanInstallment;
use App\Models\User;
use App\Services\Finance\PaymentAllocationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

class Phase30PaymentAllocationTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    private User $landlord;

    private $tenant;

    private $lease;

    protected function setUp(): void
    {
        parent::setUp();
        $setup = $this->createLandlordWithFullSetup();
        $this->landlord = $setup['landlord'];
        ['tenant' => $this->tenant, 'lease' => $this->lease] = $this->createTenantWithActiveLease(
            $this->landlord,
            $setup['units']->first(),
        );
    }

    public function test_allocate_applies_payment_to_oldest_installment_first(): void
    {
        $invoice = $this->createInvoiceForLease($this->lease, 'sent');
        $plan = $this->approvedPlanFor($invoice, [10_000_00, 10_000_00]);

        $payment = $this->paymentFor($invoice, 12_000.00);

        $service = app(PaymentAllocationService::class);
        $applied = $service->allocate($payment);

        $this->assertCount(2, $applied);
        $this->assertSame(10_000_00, $applied[0]['applied_cents']);
        $this->assertSame(2_000_00, $applied[1]['applied_cents']);

        $first = PaymentPlanInstallment::find($applied[0]['installment_id']);
        $second = PaymentPlanInstallment::find($applied[1]['installment_id']);
        $this->assertSame(PaymentPlanInstallment::STATUS_PAID, $first->status);
        $this->assertSame(PaymentPlanInstallment::STATUS_PENDING, $second->status);
        $this->assertSame(2_000_00, $second->paid_amount_cents);
    }

    public function test_full_pay_flips_plan_to_completed(): void
    {
        $invoice = $this->createInvoiceForLease($this->lease, 'sent');
        $plan = $this->approvedPlanFor($invoice, [5_000_00, 5_000_00]);
        $payment = $this->paymentFor($invoice, 10_000.00);

        $service = app(PaymentAllocationService::class);
        $service->allocate($payment);

        $plan->refresh();
        $this->assertSame(PaymentPlan::STATUS_COMPLETED, $plan->status);
    }

    public function test_allocate_skips_when_no_active_plan(): void
    {
        $invoice = $this->createInvoiceForLease($this->lease, 'sent');
        $payment = $this->paymentFor($invoice, 1_000.00);

        $service = app(PaymentAllocationService::class);
        $this->assertSame([], $service->allocate($payment));
    }

    public function test_payment_allocated_event_dispatches_with_breakdown(): void
    {
        Event::fake([PaymentAllocated::class]);

        $invoice = $this->createInvoiceForLease($this->lease, 'sent');
        $plan = $this->approvedPlanFor($invoice, [3_000_00]);
        $payment = $this->paymentFor($invoice, 3_000.00);

        app(PaymentAllocationService::class)->allocate($payment);

        Event::assertDispatched(PaymentAllocated::class, function (PaymentAllocated $e) use ($plan, $payment) {
            return $e->plan->id === $plan->id
                && $e->payment->id === $payment->id
                && count($e->applied) === 1
                && $e->applied[0]['applied_cents'] === 3_000_00;
        });
    }

    public function test_drift_audit_emits_status_drift_when_fully_paid_but_approved(): void
    {
        $invoice = $this->createInvoiceForLease($this->lease, 'sent');
        $plan = $this->approvedPlanFor($invoice, [2_000_00]);

        $installment = $plan->installments->first();
        $installment->paid_amount_cents = $installment->amount_cents;
        $installment->status = PaymentPlanInstallment::STATUS_PAID;
        $installment->save();

        $this->artisan('payment-plan-allocations:audit')
            ->expectsOutputToContain('status=1')
            ->assertSuccessful();
    }

    private function approvedPlanFor(Invoice $invoice, array $amountsCents): PaymentPlan
    {
        $plan = PaymentPlan::create([
            'landlord_id' => $this->landlord->id,
            'tenant_id' => $this->tenant->id,
            'invoice_id' => $invoice->id,
            'total_amount_cents' => array_sum($amountsCents),
            'status' => PaymentPlan::STATUS_APPROVED,
            'approved_at' => now(),
            'approved_by_user_id' => $this->landlord->id,
        ]);

        foreach ($amountsCents as $i => $cents) {
            PaymentPlanInstallment::create([
                'payment_plan_id' => $plan->id,
                'due_date' => now()->addMonths($i)->toDateString(),
                'amount_cents' => $cents,
                'status' => PaymentPlanInstallment::STATUS_PENDING,
            ]);
        }

        return $plan->refresh();
    }

    private function paymentFor(Invoice $invoice, float $amount): Payment
    {
        return Payment::create([
            'invoice_id' => $invoice->id,
            'landlord_id' => $this->landlord->id,
            'lease_id' => $this->lease->id,
            'amount' => $amount,
            'payment_method' => 'mpesa',
            'payment_date' => now()->toDateString(),
            'reference' => 'PHASE30-TEST-'.uniqid(),
        ]);
    }
}
