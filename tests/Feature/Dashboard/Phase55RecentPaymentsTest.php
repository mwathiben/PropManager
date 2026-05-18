<?php

declare(strict_types=1);

namespace Tests\Feature\Dashboard;

use App\Models\Payment;
use App\Services\DashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * Phase-55 RECENT-PAYMENTS-1/2/3 watchdog.
 *
 * Covers three regressions in the landlord dashboard recent-payments card:
 *  - 1: orderBy('payment_date', 'desc') so back-dated paid invoices surface
 *       even when their created_at trails newer rows
 *  - 2: ->withTrashed() on the lease lookup so payments against ended/soft-
 *       deleted leases keep flowing into the lease-id whereIn
 *  - 3: ->where('is_voided', false) so voided payments are excluded
 */
class Phase55RecentPaymentsTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    public function test_recent_payments_ordered_by_payment_date_not_created_at(): void
    {
        $setup = $this->createLandlordWithFullSetup();
        ['lease' => $lease] = $this->createTenantWithActiveLease(
            $setup['landlord'],
            $setup['units']->first()
        );
        $invoice = $this->createInvoiceForLease($lease);

        // Row A: created NOW but for an OLD payment_date (back-dated entry).
        $a = Payment::create([
            'invoice_id' => $invoice->id,
            'lease_id' => $lease->id,
            'landlord_id' => $lease->landlord_id,
            'amount' => 1000,
            'payment_method' => 'mpesa',
            'reference' => 'PAY-A-'.uniqid(),
            'payment_date' => now()->subDays(10)->toDateString(),
        ]);
        $a->created_at = now();
        $a->save();

        // Row B: created EARLIER but for a RECENT payment_date.
        $b = Payment::create([
            'invoice_id' => $invoice->id,
            'lease_id' => $lease->id,
            'landlord_id' => $lease->landlord_id,
            'amount' => 2000,
            'payment_method' => 'mpesa',
            'reference' => 'PAY-B-'.uniqid(),
            'payment_date' => now()->subDay()->toDateString(),
        ]);
        $b->created_at = now()->subHours(2);
        $b->save();

        $data = app(DashboardService::class)
            ->getLandlordDashboardData($setup['landlord'], new Request);
        $ids = collect($data['recentPayments'])->pluck('id')->all();

        $this->assertSame(
            [$b->id, $a->id],
            $ids,
            'recentPayments must order by payment_date desc, not created_at desc.',
        );
    }

    public function test_recent_payments_includes_payments_for_soft_deleted_leases(): void
    {
        $setup = $this->createLandlordWithFullSetup();
        ['lease' => $lease] = $this->createTenantWithActiveLease(
            $setup['landlord'],
            $setup['units']->first()
        );
        $invoice = $this->createInvoiceForLease($lease);
        $payment = Payment::create([
            'invoice_id' => $invoice->id,
            'lease_id' => $lease->id,
            'landlord_id' => $lease->landlord_id,
            'amount' => 5000,
            'payment_method' => 'mpesa',
            'reference' => 'PAY-SD-'.uniqid(),
            'payment_date' => now()->toDateString(),
        ]);

        $lease->delete();
        $this->assertNotNull($lease->fresh()->deleted_at, 'Lease must be soft-deleted for this assertion.');

        $data = app(DashboardService::class)
            ->getLandlordDashboardData($setup['landlord'], new Request);
        $ids = collect($data['recentPayments'])->pluck('id')->all();

        $this->assertContains(
            $payment->id,
            $ids,
            'Payments against soft-deleted leases must still appear (Lease lookup needs withTrashed).',
        );
    }

    public function test_recent_payments_excludes_voided_rows(): void
    {
        $setup = $this->createLandlordWithFullSetup();
        ['lease' => $lease] = $this->createTenantWithActiveLease(
            $setup['landlord'],
            $setup['units']->first()
        );
        $invoice = $this->createInvoiceForLease($lease);

        $good = Payment::create([
            'invoice_id' => $invoice->id,
            'lease_id' => $lease->id,
            'landlord_id' => $lease->landlord_id,
            'amount' => 7000,
            'payment_method' => 'mpesa',
            'reference' => 'PAY-OK-'.uniqid(),
            'payment_date' => now()->toDateString(),
            'is_voided' => false,
        ]);
        $voided = Payment::create([
            'invoice_id' => $invoice->id,
            'lease_id' => $lease->id,
            'landlord_id' => $lease->landlord_id,
            'amount' => 4000,
            'payment_method' => 'mpesa',
            'reference' => 'PAY-VOID-'.uniqid(),
            'payment_date' => now()->toDateString(),
            'is_voided' => true,
        ]);

        $data = app(DashboardService::class)
            ->getLandlordDashboardData($setup['landlord'], new Request);
        $ids = collect($data['recentPayments'])->pluck('id')->all();

        $this->assertContains($good->id, $ids);
        $this->assertNotContains(
            $voided->id,
            $ids,
            'Voided payments must not appear in the recent-payments card.',
        );
    }
}
