<?php

declare(strict_types=1);

namespace Tests\Feature\TenantPortal;

use App\Models\DepositRefundRequest;
use App\Models\Invoice;
use App\Models\Lease;
use App\Models\PaymentPlan;
use App\Models\PaymentPlanInstallment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * Phase-28 TENANT-PAY-1/3 watchdog suite. PAY-2 (auto-allocation +
 * nightly audit) is intentionally deferred — see docs/runbooks/tenant-portal.md.
 */
class Phase28PayTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    private User $landlord;

    private User $tenant;

    private Lease $lease;

    private Invoice $invoice;

    protected function setUp(): void
    {
        parent::setUp();

        $setup = $this->createLandlordWithFullSetup();
        $this->landlord = $setup['landlord'];
        ['tenant' => $this->tenant, 'lease' => $this->lease] = $this->createTenantWithActiveLease(
            $this->landlord,
            $setup['units']->first(),
        );
        $this->invoice = $this->createInvoiceForLease($this->lease, 'sent');
    }

    public function test_tenant_can_request_payment_plan_for_own_invoice(): void
    {
        $response = $this->actingAs($this->tenant)
            ->post(route('tenant.payment-plans.request'), [
                'invoice_id' => $this->invoice->id,
                'installment_count' => 3,
                'first_due_date' => now()->addDays(7)->toDateString(),
                'reason' => 'temporary income shock',
            ]);

        $response->assertRedirect();
        $plan = PaymentPlan::where('tenant_id', $this->tenant->id)->firstOrFail();
        $this->assertSame(PaymentPlan::STATUS_REQUESTED, $plan->status);
        $this->assertSame(3, $plan->installments()->count());
    }

    public function test_installment_amounts_sum_to_total_with_remainder_on_last(): void
    {
        // total_due 25000 → 2_500_000 cents over 3 installments:
        // base = 833_333, last = 833_333 + 1 cent remainder
        $this->actingAs($this->tenant)
            ->post(route('tenant.payment-plans.request'), [
                'invoice_id' => $this->invoice->id,
                'installment_count' => 3,
                'first_due_date' => now()->addDays(7)->toDateString(),
            ]);

        $plan = PaymentPlan::where('tenant_id', $this->tenant->id)->firstOrFail();
        $amounts = $plan->installments()->pluck('amount_cents')->all();

        $this->assertSame(2_500_000, array_sum($amounts));
        $this->assertSame($amounts[0], $amounts[1], 'first N-1 installments must be equal');
        $this->assertGreaterThanOrEqual($amounts[0], $amounts[2], 'last installment absorbs remainder');
    }

    public function test_cannot_request_second_plan_while_first_is_active(): void
    {
        $payload = [
            'invoice_id' => $this->invoice->id,
            'installment_count' => 2,
            'first_due_date' => now()->addDays(7)->toDateString(),
        ];

        $this->actingAs($this->tenant)
            ->post(route('tenant.payment-plans.request'), $payload)
            ->assertRedirect();

        $this->actingAs($this->tenant)
            ->post(route('tenant.payment-plans.request'), $payload)
            ->assertStatus(422);
    }

    public function test_tenant_cannot_request_plan_for_other_tenants_invoice(): void
    {
        $otherSetup = $this->createLandlordWithFullSetup();
        ['lease' => $otherLease] = $this->createTenantWithActiveLease(
            $otherSetup['landlord'],
            $otherSetup['units']->first(),
        );
        $otherInvoice = $this->createInvoiceForLease($otherLease, 'sent');

        $response = $this->actingAs($this->tenant)
            ->from(route('dashboard'))
            ->post(route('tenant.payment-plans.request'), [
                'invoice_id' => $otherInvoice->id,
                'installment_count' => 3,
                'first_due_date' => now()->addDays(7)->toDateString(),
            ]);

        // Rule::exists with cross-landlord scope yields a 302 redirect
        // back with validation errors on invoice_id. abort_unless on
        // tenant ownership would give 403. Either is correct — what we
        // must guarantee is that NO plan was created.
        $this->assertContains($response->status(), [302, 403, 422]);
        if ($response->status() === 302) {
            $response->assertSessionHasErrors('invoice_id');
        }
        $this->assertSame(0, PaymentPlan::where('invoice_id', $otherInvoice->id)->count());
    }

    public function test_tenant_can_submit_deposit_refund_with_mpesa_details(): void
    {
        $response = $this->actingAs($this->tenant)
            ->post(route('tenant.deposit-refunds.store'), [
                'lease_id' => $this->lease->id,
                'requested_amount_cents' => 2_500_000,
                'payment_method' => 'mpesa',
                'payment_details' => ['phone' => '+254712345678'],
            ]);

        $response->assertRedirect();
        $refund = DepositRefundRequest::where('tenant_id', $this->tenant->id)->firstOrFail();
        $this->assertSame('mpesa', $refund->payment_method);
        $this->assertSame('+254712345678', $refund->payment_details['phone']);
        $this->assertNotNull($refund->submitted_at);
    }

    public function test_deposit_refund_rejects_non_kenyan_mpesa_phone(): void
    {
        $this->actingAs($this->tenant)
            ->from(route('dashboard'))
            ->post(route('tenant.deposit-refunds.store'), [
                'lease_id' => $this->lease->id,
                'requested_amount_cents' => 500_000,
                'payment_method' => 'mpesa',
                'payment_details' => ['phone' => '+1234567890'],
            ])
            ->assertSessionHasErrors('payment_details.phone');
    }

    public function test_cannot_submit_second_refund_while_first_is_active(): void
    {
        $payload = [
            'lease_id' => $this->lease->id,
            'requested_amount_cents' => 1_000_000,
            'payment_method' => 'mpesa',
            'payment_details' => ['phone' => '+254712345678'],
        ];

        $this->actingAs($this->tenant)
            ->post(route('tenant.deposit-refunds.store'), $payload)
            ->assertRedirect();

        $this->actingAs($this->tenant)
            ->post(route('tenant.deposit-refunds.store'), $payload)
            ->assertStatus(422);
    }

    public function test_tenant_cannot_submit_refund_for_other_tenants_lease(): void
    {
        $otherSetup = $this->createLandlordWithFullSetup();
        ['lease' => $otherLease] = $this->createTenantWithActiveLease(
            $otherSetup['landlord'],
            $otherSetup['units']->first(),
        );

        $this->actingAs($this->tenant)
            ->from(route('dashboard'))
            ->post(route('tenant.deposit-refunds.store'), [
                'lease_id' => $otherLease->id,
                'requested_amount_cents' => 500_000,
                'payment_method' => 'mpesa',
                'payment_details' => ['phone' => '+254712345678'],
            ])
            ->assertSessionHasErrors('lease_id');
    }
}
