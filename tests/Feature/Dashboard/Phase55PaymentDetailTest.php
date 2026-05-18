<?php

declare(strict_types=1);

namespace Tests\Feature\Dashboard;

use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * Phase-55 PAYMENT-DETAIL-1/2/3 watchdog.
 *
 * Verifies that:
 *  - landlord can view their own payment detail page
 *  - cross-tenant landlord cannot view another landlord's payment
 *  - the rendered Inertia payload contains the expected shape
 */
class Phase55PaymentDetailTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    public function test_landlord_views_own_payment_detail(): void
    {
        $setup = $this->createLandlordWithFullSetup();
        ['lease' => $lease] = $this->createTenantWithActiveLease(
            $setup['landlord'],
            $setup['units']->first()
        );
        ['payment' => $payment] = $this->createPaymentWithInvoice($lease, 12000);

        $this->actingAs($setup['landlord'])
            ->get(route('payments.detail.show', $payment))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Payments/Detail')
                ->where('payment.id', $payment->id)
                ->where('payment.amount', 12000)
                ->where('payment.is_voided', false)
                ->has('invoice')
                ->has('lease')
                ->where('lease.state', 'active'));
    }

    public function test_lease_state_surfaces_soft_deleted(): void
    {
        $setup = $this->createLandlordWithFullSetup();
        ['lease' => $lease] = $this->createTenantWithActiveLease(
            $setup['landlord'],
            $setup['units']->first()
        );
        ['payment' => $payment] = $this->createPaymentWithInvoice($lease, 4000);
        $lease->delete();

        $this->actingAs($setup['landlord'])
            ->get(route('payments.detail.show', $payment))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('lease.state', 'soft_deleted'));
    }

    public function test_cross_tenant_landlord_cannot_view_payment(): void
    {
        $setupA = $this->createLandlordWithFullSetup();
        ['lease' => $leaseA] = $this->createTenantWithActiveLease(
            $setupA['landlord'],
            $setupA['units']->first()
        );
        ['payment' => $paymentA] = $this->createPaymentWithInvoice($leaseA, 1000);

        $landlordB = User::factory()->create(['role' => 'landlord']);

        $response = $this->actingAs($landlordB)
            ->get(route('payments.detail.show', $paymentA));

        $this->assertContains(
            $response->status(),
            [403, 404],
            'Cross-tenant landlord must be blocked from viewing another landlord\'s payment.',
        );
    }

    public function test_tenant_can_view_own_payment(): void
    {
        $setup = $this->createLandlordWithFullSetup();
        ['tenant' => $tenant, 'lease' => $lease] = $this->createTenantWithActiveLease(
            $setup['landlord'],
            $setup['units']->first()
        );
        ['payment' => $payment] = $this->createPaymentWithInvoice($lease, 6000);

        // Defensively ensure Payment::lease isn't trapped by SoftDeletes scope
        // for the policy check (tenants legitimately view payments against
        // their active lease).
        $this->actingAs($tenant)
            ->get(route('payments.detail.show', $payment))
            ->assertOk();
    }

    public function test_voided_payment_renders_void_reason(): void
    {
        $setup = $this->createLandlordWithFullSetup();
        ['lease' => $lease] = $this->createTenantWithActiveLease(
            $setup['landlord'],
            $setup['units']->first()
        );
        ['payment' => $payment] = $this->createPaymentWithInvoice($lease, 9000);
        $payment->update([
            'is_voided' => true,
            'voided_at' => now(),
            'void_reason' => 'Duplicate entry',
        ]);

        $this->actingAs($setup['landlord'])
            ->get(route('payments.detail.show', $payment))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('payment.is_voided', true)
                ->where('payment.void_reason', 'Duplicate entry'));
    }
}
