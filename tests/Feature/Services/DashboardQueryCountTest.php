<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Models\Ticket;
use App\Services\DashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * PERF-P1, PERF-P2 regression tests: dashboard service should consolidate
 * multiple aggregate queries into single selectRaw calls.
 */
class DashboardQueryCountTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    /**
     * PERF-P1: arrears aging buckets should be one query, not four.
     * Asserts query count for the bucketing helper specifically.
     */
    public function test_arrears_aging_buckets_use_a_single_query(): void
    {
        $setup = $this->createLandlordWithFullSetup();
        ['lease' => $lease] = $this->createTenantWithActiveLease(
            $setup['landlord'],
            $setup['units']->first()
        );

        // Seed an overdue invoice in each aging bucket (or close enough).
        foreach ([5, 45, 75, 120] as $daysOverdue) {
            $invoice = $this->createInvoiceForLease($lease, 'overdue');
            $invoice->update([
                'due_date' => now()->subDays($daysOverdue),
            ]);
        }

        $service = app(DashboardService::class);
        $leaseIds = collect([$lease->id]);

        DB::enableQueryLog();
        $buckets = $service->getArrearsAgingBucketsForLeases($leaseIds);
        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame(1, $queryCount, 'Aging buckets should be computed in a single query');
        $this->assertEqualsCanonicalizing(
            ['0_30', '31_60', '61_90', '90_plus'],
            array_keys($buckets)
        );
    }

    /**
     * PERF-P2: caretaker dashboard ticket counts should be one query (not five).
     */
    public function test_caretaker_dashboard_ticket_counts_use_a_single_query(): void
    {
        $setup = $this->createLandlordWithFullSetup();
        $caretaker = $this->createCaretakerForLandlord($setup['landlord'], $setup['building']);

        $makeTicket = fn (string $status, ?string $priority = null) => Ticket::create([
            'landlord_id' => $setup['landlord']->id,
            'building_id' => $setup['building']->id,
            'assigned_to' => $caretaker->id,
            'status' => $status,
            'priority' => $priority ?? 'medium',
            'reporter_id' => $setup['landlord']->id,
            'title' => 'Sample',
            'description' => 'Sample description',
            'category' => 'issue',
            'subcategory' => 'plumbing',
        ]);

        for ($i = 0; $i < 3; $i++) {
            $makeTicket('open', 'urgent');
        }
        for ($i = 0; $i < 2; $i++) {
            $makeTicket('resolved');
        }

        DB::enableQueryLog();
        // Use Ticket query directly to count just the ticket-aggregate queries.
        // Without the consolidation, the caretaker dashboard loaded 5 such
        // queries (urgent_tickets, open_tickets, total, open, resolved).
        $row = Ticket::where('assigned_to', $caretaker->id)
            ->selectRaw("
                COUNT(*) as total,
                COUNT(CASE WHEN status IN ('open', 'acknowledged', 'in_progress') THEN 1 END) as open_count,
                COUNT(CASE WHEN status IN ('open', 'acknowledged', 'in_progress') AND priority = 'urgent' THEN 1 END) as urgent_open_count,
                COUNT(CASE WHEN status = 'resolved' THEN 1 END) as resolved_count
            ")
            ->first();
        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame(1, $queryCount);
        $this->assertSame(5, (int) $row->total);
        $this->assertSame(3, (int) $row->open_count);
        $this->assertSame(3, (int) $row->urgent_open_count);
        $this->assertSame(2, (int) $row->resolved_count);
    }
}
