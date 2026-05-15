<?php

declare(strict_types=1);

namespace Tests\Feature\Reports;

use App\Models\Lease;
use App\Models\Payment;
use App\Models\Unit;
use App\Models\User;
use App\Services\Reports\CohortService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase-27 BI-COHORT-1/2/3 watchdogs: retention matrix shape, the
 * acquisition table balance identity, LTV methodology (refunds
 * excluded, voids excluded, zero-payment tenants count toward
 * divisor).
 */
class Phase27CohortTest extends TestCase
{
    use RefreshDatabase;

    private CohortService $service;

    private User $landlord;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(CohortService::class);
        $this->landlord = User::factory()->create(['role' => 'landlord']);
    }

    private function makePayment(Lease $lease, float $amount, Carbon $paidAt, bool $voided): Payment
    {
        return Payment::create([
            'lease_id' => $lease->id,
            'landlord_id' => $lease->landlord_id,
            'amount' => $amount,
            'currency' => 'KES',
            'payment_method' => 'cash',
            'payment_date' => $paidAt,
            'reference' => 'PAY-'.uniqid(),
            'is_voided' => $voided,
        ]);
    }

    private function leaseStartingAt(Carbon $date, ?Carbon $endDate = null): Lease
    {
        $unit = Unit::factory()->create(['landlord_id' => $this->landlord->id]);

        return Lease::factory()->create([
            'unit_id' => $unit->id,
            'landlord_id' => $this->landlord->id,
            'tenant_id' => User::factory()->create([
                'role' => 'tenant',
                'landlord_id' => $this->landlord->id,
            ])->id,
            'start_date' => $date,
            'end_date' => $endDate,
            'is_active' => $endDate === null,
        ]);
    }

    public function test_retention_matrix_diagonal_is_one(): void
    {
        $this->leaseStartingAt(Carbon::now()->subMonths(3)->startOfMonth());
        $this->leaseStartingAt(Carbon::now()->subMonths(2)->startOfMonth());

        $matrix = $this->service->retentionMatrix($this->landlord->id, 6);

        foreach ($matrix as $month => $row) {
            $this->assertSame(
                1.0,
                $row[0],
                "BI-COHORT-1: cohort {$month} must survive its own start month (offset 0 = 1.0).",
            );
        }
    }

    public function test_retention_matrix_drops_when_lease_ends(): void
    {
        $cohortStart = Carbon::now()->subMonths(5)->startOfMonth();
        $earlyEnd = $cohortStart->copy()->addMonths(1)->endOfMonth();

        // One survives, one churns at month 1.
        $this->leaseStartingAt($cohortStart);
        $this->leaseStartingAt($cohortStart, $earlyEnd);

        $matrix = $this->service->retentionMatrix($this->landlord->id, 6);
        $cohortKey = $cohortStart->format('Y-m');

        $this->assertSame(1.0, $matrix[$cohortKey][0], 'BI-COHORT-1: 2/2 alive at offset 0.');
        $this->assertSame(1.0, $matrix[$cohortKey][1], 'BI-COHORT-1: 2/2 alive at offset 1 (one ends ON the offset month, so alive at its end).');
        $this->assertSame(0.5, $matrix[$cohortKey][2], 'BI-COHORT-1: 1/2 alive at offset 2 (one lease churned).');
    }

    public function test_retention_matrix_future_offsets_are_null(): void
    {
        $cohortStart = Carbon::now()->subMonths(1)->startOfMonth();
        $this->leaseStartingAt($cohortStart);

        $matrix = $this->service->retentionMatrix($this->landlord->id, 6);
        $cohortKey = $cohortStart->format('Y-m');

        // Offset 0 and 1 should be populated (now is in or past month 1).
        $this->assertNotNull($matrix[$cohortKey][0]);
        // Offsets beyond today are null (future).
        $this->assertNull($matrix[$cohortKey][5], 'BI-COHORT-1: offsets in the future must be null.');
    }

    public function test_acquisition_table_returns_row_per_month(): void
    {
        $rows = $this->service->acquisitionTable($this->landlord->id, 6);

        $this->assertCount(6, $rows, 'BI-COHORT-2: window of 6 months must produce 6 rows.');
        foreach ($rows as $row) {
            $this->assertArrayHasKey('month', $row);
            $this->assertArrayHasKey('new', $row);
            $this->assertArrayHasKey('reactivated', $row);
            $this->assertArrayHasKey('churned', $row);
            $this->assertArrayHasKey('net_delta', $row);
            $this->assertSame($row['new'] + $row['reactivated'] - $row['churned'], $row['net_delta']);
        }
    }

    public function test_acquisition_table_separates_new_from_reactivated(): void
    {
        $unit = Unit::factory()->create(['landlord_id' => $this->landlord->id]);
        $tenant = User::factory()->create([
            'role' => 'tenant',
            'landlord_id' => $this->landlord->id,
        ]);

        // First lease, 4 months ago, ended 2 months ago.
        Lease::factory()->create([
            'unit_id' => $unit->id,
            'landlord_id' => $this->landlord->id,
            'tenant_id' => $tenant->id,
            'start_date' => Carbon::now()->subMonths(4)->startOfMonth(),
            'end_date' => Carbon::now()->subMonths(2)->endOfMonth(),
            'is_active' => false,
        ]);

        // Same tenant, new lease 1 month ago.
        Lease::factory()->create([
            'unit_id' => $unit->id,
            'landlord_id' => $this->landlord->id,
            'tenant_id' => $tenant->id,
            'start_date' => Carbon::now()->subMonths(1)->startOfMonth(),
            'is_active' => true,
        ]);

        $rows = $this->service->acquisitionTable($this->landlord->id, 6);
        $byMonth = collect($rows)->keyBy('month');

        $firstMonth = Carbon::now()->subMonths(4)->format('Y-m');
        $secondMonth = Carbon::now()->subMonths(1)->format('Y-m');

        $this->assertSame(1, $byMonth->get($firstMonth)['new'], 'BI-COHORT-2: first lease must count as new.');
        $this->assertSame(0, $byMonth->get($firstMonth)['reactivated']);

        $this->assertSame(0, $byMonth->get($secondMonth)['new'], 'BI-COHORT-2: second lease for same tenant must NOT count as new.');
        $this->assertSame(1, $byMonth->get($secondMonth)['reactivated'], 'BI-COHORT-2: second lease for returning tenant counts as reactivated.');
    }

    public function test_lifetime_value_excludes_voided_payments(): void
    {
        $cohortMonth = Carbon::now()->subMonths(2)->format('Y-m');
        $cohortStart = Carbon::parse($cohortMonth.'-01');

        $lease = $this->leaseStartingAt($cohortStart);

        // Two payments: one valid 1000, one voided 500.
        $this->makePayment($lease, 1000, $cohortStart->copy()->addDays(5), false);
        $this->makePayment($lease, 500, $cohortStart->copy()->addDays(10), true);

        $ltv = $this->service->lifetimeValue($this->landlord->id, $cohortMonth);

        $this->assertSame(1, $ltv['tenants_count']);
        $this->assertSame(1000.0, $ltv['total_payments'], 'BI-COHORT-3: voided payments must NOT count toward LTV.');
        $this->assertSame(1000.0, $ltv['mean_ltv']);
    }

    public function test_lifetime_value_counts_zero_payment_tenants_in_divisor(): void
    {
        $cohortMonth = Carbon::now()->subMonths(2)->format('Y-m');
        $cohortStart = Carbon::parse($cohortMonth.'-01');

        $leaseA = $this->leaseStartingAt($cohortStart);
        $this->leaseStartingAt($cohortStart); // tenant B, no payments

        $this->makePayment($leaseA, 2000, $cohortStart->copy()->addDays(5), false);

        $ltv = $this->service->lifetimeValue($this->landlord->id, $cohortMonth);

        $this->assertSame(2, $ltv['tenants_count'], 'BI-COHORT-3: cohort size includes zero-payment tenants (honest representation).');
        $this->assertSame(2000.0, $ltv['total_payments']);
        $this->assertSame(1000.0, $ltv['mean_ltv'], 'BI-COHORT-3: mean = total / cohort_size (not / paying_tenants).');
        $this->assertSame(1000.0, $ltv['median_ltv'], 'BI-COHORT-3: median of {0, 2000} = 1000.');
    }

    public function test_lifetime_value_handles_empty_cohort(): void
    {
        $futureMonth = Carbon::now()->addMonths(6)->format('Y-m');
        $ltv = $this->service->lifetimeValue($this->landlord->id, $futureMonth);

        $this->assertSame(0, $ltv['tenants_count']);
        $this->assertSame(0.0, $ltv['total_payments']);
        $this->assertSame(0.0, $ltv['mean_ltv']);
        $this->assertSame(0.0, $ltv['median_ltv']);
    }

    public function test_cohort_route_renders_inertia_page_for_landlord(): void
    {
        $response = $this->actingAs($this->landlord)->get(route('reports.cohort'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Reports/Cohort')
            ->has('retentionMatrix')
            ->has('acquisitionTable')
            ->has('lifetimeValue'),
        );
    }

    public function test_cohort_route_rejects_tenant_role(): void
    {
        $tenant = User::factory()->create(['role' => 'tenant']);
        $this->actingAs($tenant)
            ->get(route('reports.cohort'))
            ->assertForbidden();
    }
}
