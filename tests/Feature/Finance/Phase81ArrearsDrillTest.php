<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Models\Invoice;
use App\Models\Lease;
use App\Models\User;
use App\Services\FinanceFilterService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * Phase-81 ARREARS-DRILL: per-row aging bucket + severity-first sort.
 */
class Phase81ArrearsDrillTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    private User $landlord;

    private Lease $lease;

    protected function setUp(): void
    {
        parent::setUp();
        $setup = $this->createLandlordWithFullSetup();
        $this->landlord = $setup['landlord'];
        $this->lease = Model::withoutEvents(
            fn () => $this->createTenantWithActiveLease($this->landlord, $setup['units']->get(0))['lease'],
        );
    }

    private function overdue(int $daysOverdue): Invoice
    {
        return Model::withoutEvents(fn () => Invoice::factory()->create([
            'lease_id' => $this->lease->id,
            'landlord_id' => $this->landlord->id,
            'status' => 'overdue',
            'total_due' => 10000,
            'amount_paid' => 0,
            'due_date' => now()->subDays($daysOverdue),
        ]));
    }

    public function test_rows_carry_aging_buckets(): void
    {
        $recent = $this->overdue(10);
        $old = $this->overdue(95);

        $rows = collect(app(FinanceFilterService::class)->getArrearsData(new Request, $this->landlord->id));

        $this->assertSame('0_30', $rows->firstWhere('id', $recent->id)['aging_bucket']);
        $this->assertSame('90_plus', $rows->firstWhere('id', $old->id)['aging_bucket']);
    }

    public function test_rows_sorted_by_days_overdue_desc(): void
    {
        $this->overdue(10);
        $this->overdue(95);
        $this->overdue(45);

        $rows = collect(app(FinanceFilterService::class)->getArrearsData(new Request, $this->landlord->id));
        $days = $rows->pluck('days_overdue')->all();

        $sorted = $days;
        rsort($sorted);
        $this->assertSame($sorted, $days, 'arrears must be most-overdue first');
    }
}
