<?php

declare(strict_types=1);

namespace Tests\Feature\Workflow;

use App\Events\DepositRefundApproved;
use App\Events\DepositRefundPaid;
use App\Events\DepositRefundRejected;
use App\Events\PaymentPlanApproved;
use App\Events\PaymentPlanRejected;
use App\Models\DepositRefundRequest;
use App\Models\PaymentPlan;
use App\Models\PaymentPlanInstallment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

class Phase29PayApproveTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    private User $landlord;

    private User $tenant;

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

    public function test_landlord_approves_payment_plan_and_fires_event(): void
    {
        Event::fake([PaymentPlanApproved::class]);
        $plan = $this->requestedPlan();

        $this->actingAs($this->landlord)
            ->post(route('finance.payment-plans.approve', ['plan' => $plan->id]))
            ->assertRedirect();

        $plan->refresh();
        $this->assertSame(PaymentPlan::STATUS_APPROVED, $plan->status);
        $this->assertNotNull($plan->approved_at);
        $this->assertSame($this->landlord->id, $plan->approved_by_user_id);
        Event::assertDispatched(PaymentPlanApproved::class);
    }

    public function test_landlord_rejects_payment_plan_with_reason(): void
    {
        Event::fake([PaymentPlanRejected::class]);
        $plan = $this->requestedPlan();

        $this->actingAs($this->landlord)
            ->post(route('finance.payment-plans.reject', ['plan' => $plan->id]), [
                'rejection_reason' => 'plan too short',
            ])
            ->assertRedirect();

        $plan->refresh();
        $this->assertSame(PaymentPlan::STATUS_REJECTED, $plan->status);
        $this->assertSame('plan too short', $plan->rejection_reason);
        Event::assertDispatched(PaymentPlanRejected::class);
    }

    public function test_other_landlord_cannot_approve_payment_plan(): void
    {
        $plan = $this->requestedPlan();
        $other = User::factory()->create(['role' => 'landlord']);

        $this->actingAs($other)
            ->post(route('finance.payment-plans.approve', ['plan' => $plan->id]))
            ->assertForbidden();
    }

    public function test_already_approved_plan_cannot_be_re_approved(): void
    {
        $plan = $this->requestedPlan();
        $plan->update(['status' => PaymentPlan::STATUS_APPROVED, 'approved_at' => now()]);

        $this->actingAs($this->landlord)
            ->post(route('finance.payment-plans.approve', ['plan' => $plan->id]))
            ->assertStatus(422);
    }

    public function test_landlord_approves_deposit_refund_with_final_amount(): void
    {
        Event::fake([DepositRefundApproved::class]);
        $refund = $this->submittedRefund();

        $this->actingAs($this->landlord)
            ->post(route('finance.deposit-refunds.approve', ['refund' => $refund->id]), [
                'final_amount_cents' => 2_000_000,
            ])
            ->assertRedirect();

        $refund->refresh();
        $this->assertSame(DepositRefundRequest::STATUS_APPROVED, $refund->status);
        $this->assertSame(2_000_000, $refund->final_amount_cents);
        Event::assertDispatched(DepositRefundApproved::class);
    }

    public function test_landlord_rejects_deposit_refund(): void
    {
        Event::fake([DepositRefundRejected::class]);
        $refund = $this->submittedRefund();

        $this->actingAs($this->landlord)
            ->post(route('finance.deposit-refunds.reject', ['refund' => $refund->id]), [
                'rejection_reason' => 'unit not yet inspected',
            ])
            ->assertRedirect();

        $refund->refresh();
        $this->assertSame(DepositRefundRequest::STATUS_REJECTED, $refund->status);
        $this->assertSame('unit not yet inspected', $refund->rejection_reason);
        Event::assertDispatched(DepositRefundRejected::class);
    }

    public function test_mark_paid_requires_approved_status(): void
    {
        $refund = $this->submittedRefund();

        $this->actingAs($this->landlord)
            ->post(route('finance.deposit-refunds.mark-paid', ['refund' => $refund->id]), [
                'payment_reference' => 'MPESA-XYZ',
            ])
            ->assertStatus(422);
    }

    public function test_mark_paid_advances_status_and_fires_event(): void
    {
        Event::fake([DepositRefundPaid::class]);
        $refund = $this->submittedRefund();
        $refund->update([
            'status' => DepositRefundRequest::STATUS_APPROVED,
            'final_amount_cents' => 2_500_000,
            'reviewed_at' => now(),
        ]);

        $this->actingAs($this->landlord)
            ->post(route('finance.deposit-refunds.mark-paid', ['refund' => $refund->id]), [
                'payment_reference' => 'MPESA-XYZ',
            ])
            ->assertRedirect();

        $refund->refresh();
        $this->assertSame(DepositRefundRequest::STATUS_PAID, $refund->status);
        $this->assertSame('MPESA-XYZ', $refund->payment_reference);
        $this->assertNotNull($refund->paid_at);
        Event::assertDispatched(DepositRefundPaid::class);
    }

    public function test_other_landlord_cannot_process_deposit_refund(): void
    {
        $refund = $this->submittedRefund();
        $other = User::factory()->create(['role' => 'landlord']);

        $this->actingAs($other)
            ->post(route('finance.deposit-refunds.approve', ['refund' => $refund->id]), [
                'final_amount_cents' => 1_000_000,
            ])
            ->assertForbidden();
    }

    private function requestedPlan(): PaymentPlan
    {
        $invoice = $this->createInvoiceForLease($this->lease, 'sent');
        $plan = PaymentPlan::create([
            'landlord_id' => $this->landlord->id,
            'tenant_id' => $this->tenant->id,
            'invoice_id' => $invoice->id,
            'total_amount_cents' => 2_500_000,
            'status' => PaymentPlan::STATUS_REQUESTED,
        ]);
        PaymentPlanInstallment::create([
            'payment_plan_id' => $plan->id,
            'due_date' => now()->addMonth()->toDateString(),
            'amount_cents' => 2_500_000,
            'status' => PaymentPlanInstallment::STATUS_PENDING,
        ]);

        return $plan;
    }

    private function submittedRefund(): DepositRefundRequest
    {
        return DepositRefundRequest::create([
            'landlord_id' => $this->landlord->id,
            'tenant_id' => $this->tenant->id,
            'lease_id' => $this->lease->id,
            'requested_amount_cents' => 2_500_000,
            'payment_method' => 'mpesa',
            'payment_details' => ['phone' => '+254712345678'],
            'status' => DepositRefundRequest::STATUS_SUBMITTED,
            'submitted_at' => now(),
        ]);
    }
}
