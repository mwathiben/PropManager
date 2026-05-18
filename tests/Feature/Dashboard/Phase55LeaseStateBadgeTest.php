<?php

declare(strict_types=1);

namespace Tests\Feature\Dashboard;

use App\Services\DashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * Phase-55 LEASE-STATE-BADGE-1/2/3 watchdog. Each recent-payments row carries
 * lease_state ∈ {active, ended, soft_deleted, unknown} computed from the
 * eager-loaded lease (withTrashed).
 */
class Phase55LeaseStateBadgeTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    public function test_active_lease_payment_carries_active_state(): void
    {
        $setup = $this->createLandlordWithFullSetup();
        ['lease' => $lease] = $this->createTenantWithActiveLease(
            $setup['landlord'],
            $setup['units']->first()
        );
        ['payment' => $payment] = $this->createPaymentWithInvoice($lease, 8000);

        $data = app(DashboardService::class)
            ->getLandlordDashboardData($setup['landlord'], new Request);
        $row = collect($data['recentPayments'])->firstWhere('id', $payment->id);

        $this->assertNotNull($row);
        $this->assertSame('active', $row->lease_state);
    }

    public function test_ended_lease_payment_carries_ended_state(): void
    {
        $setup = $this->createLandlordWithFullSetup();
        ['lease' => $lease] = $this->createTenantWithActiveLease(
            $setup['landlord'],
            $setup['units']->first()
        );
        ['payment' => $payment] = $this->createPaymentWithInvoice($lease, 9000);

        $lease->update(['is_active' => false]);

        $data = app(DashboardService::class)
            ->getLandlordDashboardData($setup['landlord'], new Request);
        $row = collect($data['recentPayments'])->firstWhere('id', $payment->id);

        $this->assertNotNull($row);
        $this->assertSame('ended', $row->lease_state);
    }

    public function test_soft_deleted_lease_payment_carries_soft_deleted_state(): void
    {
        $setup = $this->createLandlordWithFullSetup();
        ['lease' => $lease] = $this->createTenantWithActiveLease(
            $setup['landlord'],
            $setup['units']->first()
        );
        ['payment' => $payment] = $this->createPaymentWithInvoice($lease, 7000);

        $lease->delete();

        $data = app(DashboardService::class)
            ->getLandlordDashboardData($setup['landlord'], new Request);
        $row = collect($data['recentPayments'])->firstWhere('id', $payment->id);

        $this->assertNotNull($row, 'Soft-deleted lease payments must still surface via RECENT-PAYMENTS-2.');
        $this->assertSame('soft_deleted', $row->lease_state);
    }
}
