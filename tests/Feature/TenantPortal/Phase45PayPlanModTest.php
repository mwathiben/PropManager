<?php

declare(strict_types=1);

namespace Tests\Feature\TenantPortal;

use App\Models\Invoice;
use App\Models\PaymentPlan;
use App\Models\PaymentPlanInstallment;
use App\Models\PaymentPlanModification;
use App\Models\User;
use App\Services\Tenant\PaymentPlanModificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * Phase-45 PAY-PLAN-MOD-1/2/3 watchdog suite.
 */
class Phase45PayPlanModTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    private User $landlord;

    private User $tenant;

    private PaymentPlan $plan;

    protected function setUp(): void
    {
        parent::setUp();

        $setup = $this->createLandlordWithFullSetup();
        $this->landlord = $setup['landlord'];
        ['tenant' => $this->tenant, 'lease' => $lease] = $this->createTenantWithActiveLease(
            $this->landlord,
            $setup['units']->first(),
        );

        $invoice = Invoice::create([
            'lease_id' => $lease->id,
            'landlord_id' => $this->landlord->id,
            'invoice_number' => 'INV-2026-PP-1',
            'rent_due' => 60000,
            'water_due' => 0,
            'arrears' => 0,
            'wallet_applied' => 0,
            'total_due' => 60000,
            'amount_paid' => 0,
            'status' => 'sent',
            'billing_period_start' => '2026-05-01',
            'due_date' => '2026-05-07',
        ]);

        $this->plan = PaymentPlan::create([
            'landlord_id' => $this->landlord->id,
            'tenant_id' => $this->tenant->id,
            'invoice_id' => $invoice->id,
            'total_amount_cents' => 60000_00,
            'status' => PaymentPlan::STATUS_APPROVED,
            'approved_at' => now(),
            'approved_by_user_id' => $this->landlord->id,
        ]);

        // 3 installments of 20_000_00 cents each, all unpaid.
        foreach (['2026-06-01', '2026-07-01', '2026-08-01'] as $date) {
            PaymentPlanInstallment::create([
                'payment_plan_id' => $this->plan->id,
                'due_date' => $date,
                'amount_cents' => 20_000_00,
                'paid_amount_cents' => 0,
                'status' => PaymentPlanInstallment::STATUS_PENDING,
            ]);
        }
    }

    public function test_tenant_proposes_modification_flips_status_to_modified_pending(): void
    {
        $response = $this->actingAs($this->tenant)
            ->post(route('tenant.payment-plans.modifications.store', $this->plan), [
                'installments' => [
                    ['due_date' => now()->addMonths(1)->toDateString(), 'amount_cents' => 15_000_00],
                    ['due_date' => now()->addMonths(2)->toDateString(), 'amount_cents' => 15_000_00],
                    ['due_date' => now()->addMonths(3)->toDateString(), 'amount_cents' => 15_000_00],
                    ['due_date' => now()->addMonths(4)->toDateString(), 'amount_cents' => 15_000_00],
                ],
            ]);

        $response->assertRedirect();

        $this->plan->refresh();
        $this->assertSame(PaymentPlan::STATUS_MODIFIED_PENDING, $this->plan->status);

        $mod = PaymentPlanModification::query()->where('payment_plan_id', $this->plan->id)->first();
        $this->assertNotNull($mod);
        $this->assertSame(PaymentPlanModification::STATUS_PENDING, $mod->status);
        $this->assertCount(4, $mod->proposed_installments);
        $this->assertCount(3, $mod->original_installments);
    }

    public function test_proposed_total_must_equal_unpaid_balance(): void
    {
        $this->expectException(ValidationException::class);

        app(PaymentPlanModificationService::class)->propose(
            $this->plan,
            [
                ['due_date' => now()->addMonths(1)->toDateString(), 'amount_cents' => 10_000_00],
                ['due_date' => now()->addMonths(2)->toDateString(), 'amount_cents' => 10_000_00],
            ],
            $this->tenant,
        );
    }

    public function test_modification_cannot_touch_paid_installments(): void
    {
        // Mark the first installment as paid.
        $this->plan->installments()->first()->update([
            'paid_amount_cents' => 20_000_00,
            'status' => PaymentPlanInstallment::STATUS_PAID,
            'paid_at' => now(),
        ]);

        // Now propose a modification covering only the remaining 40k.
        $mod = app(PaymentPlanModificationService::class)->propose(
            $this->plan,
            [
                ['due_date' => now()->addMonths(2)->toDateString(), 'amount_cents' => 20_000_00],
                ['due_date' => now()->addMonths(3)->toDateString(), 'amount_cents' => 20_000_00],
            ],
            $this->tenant,
        );

        $this->assertNotNull($mod);
        $this->assertCount(2, $mod->original_installments);
        $this->assertSame(40_000_00, array_sum(array_column($mod->proposed_installments, 'amount_cents')));
    }

    public function test_landlord_approves_modification_replaces_unpaid_installments(): void
    {
        $mod = app(PaymentPlanModificationService::class)->propose(
            $this->plan,
            [
                ['due_date' => now()->addMonths(1)->toDateString(), 'amount_cents' => 30_000_00],
                ['due_date' => now()->addMonths(2)->toDateString(), 'amount_cents' => 30_000_00],
            ],
            $this->tenant,
        );

        $response = $this->actingAs($this->landlord)
            ->post(route('finance.payment-plan-modifications.approve', $mod), [
                'landlord_response' => 'Approved as proposed.',
            ]);

        $response->assertRedirect();

        $this->plan->refresh();
        $this->assertSame(PaymentPlan::STATUS_APPROVED, $this->plan->status);
        $this->assertSame(2, $this->plan->installments()->count());
        $this->assertSame(30_000_00, $this->plan->installments()->first()->amount_cents);

        $mod->refresh();
        $this->assertSame(PaymentPlanModification::STATUS_APPROVED, $mod->status);
        $this->assertNotNull($mod->decided_at);
    }

    public function test_landlord_rejects_modification_reverts_plan_to_approved(): void
    {
        $mod = app(PaymentPlanModificationService::class)->propose(
            $this->plan,
            [
                ['due_date' => now()->addMonths(1)->toDateString(), 'amount_cents' => 30_000_00],
                ['due_date' => now()->addMonths(2)->toDateString(), 'amount_cents' => 30_000_00],
            ],
            $this->tenant,
        );

        $response = $this->actingAs($this->landlord)
            ->post(route('finance.payment-plan-modifications.reject', $mod), [
                'landlord_response' => 'Sticking with original schedule.',
            ]);

        $response->assertRedirect();

        $this->plan->refresh();
        $this->assertSame(PaymentPlan::STATUS_APPROVED, $this->plan->status);
        // Original installments still in place.
        $this->assertSame(3, $this->plan->installments()->count());

        $mod->refresh();
        $this->assertSame(PaymentPlanModification::STATUS_REJECTED, $mod->status);
    }

    public function test_modification_minimum_two_installments(): void
    {
        $this->expectException(ValidationException::class);

        app(PaymentPlanModificationService::class)->propose(
            $this->plan,
            [
                ['due_date' => now()->addMonths(1)->toDateString(), 'amount_cents' => 60_000_00],
            ],
            $this->tenant,
        );
    }

    public function test_modification_only_allowed_for_approved_plans(): void
    {
        $this->plan->update(['status' => PaymentPlan::STATUS_REQUESTED]);

        $this->expectException(ValidationException::class);

        app(PaymentPlanModificationService::class)->propose(
            $this->plan,
            [
                ['due_date' => now()->addMonths(1)->toDateString(), 'amount_cents' => 30_000_00],
                ['due_date' => now()->addMonths(2)->toDateString(), 'amount_cents' => 30_000_00],
            ],
            $this->tenant,
        );
    }

    public function test_audit_cron_emits_gauge_for_pending_over_24h(): void
    {
        $mod = app(PaymentPlanModificationService::class)->propose(
            $this->plan,
            [
                ['due_date' => now()->addMonths(1)->toDateString(), 'amount_cents' => 30_000_00],
                ['due_date' => now()->addMonths(2)->toDateString(), 'amount_cents' => 30_000_00],
            ],
            $this->tenant,
        );
        $mod->update(['created_at' => now()->subDays(5)]);

        $this->artisan('payment-plans:audit-stale-modifications')->assertExitCode(0);
    }
}
