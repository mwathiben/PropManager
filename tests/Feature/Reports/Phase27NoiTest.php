<?php

declare(strict_types=1);

namespace Tests\Feature\Reports;

use App\Models\Building;
use App\Models\Expense;
use App\Models\Lease;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Unit;
use App\Models\User;
use App\Services\Reports\NoiService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Phase-27 BI-NOI-1/2/3 watchdogs: per-property NOI shape, cap-rate
 * annualisation correctness, expense allocation methodology.
 */
class Phase27NoiTest extends TestCase
{
    use RefreshDatabase;

    private NoiService $service;

    private User $landlord;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(NoiService::class);
        $this->landlord = User::factory()->create(['role' => 'landlord']);
    }

    private function property(?float $value = null): Property
    {
        return Property::factory()->create([
            'landlord_id' => $this->landlord->id,
            'estimated_value' => $value,
        ]);
    }

    private function unitOn(Property $property): Unit
    {
        $building = Building::factory()->create([
            'landlord_id' => $this->landlord->id,
            'property_id' => $property->id,
        ]);

        return Unit::factory()->create([
            'landlord_id' => $this->landlord->id,
            'building_id' => $building->id,
        ]);
    }

    private function makeExpense(array $overrides = []): Expense
    {
        return Expense::create(array_merge([
            'landlord_id' => $this->landlord->id,
            'description' => 'test expense',
            'amount' => 5000,
            'expense_date' => Carbon::now(),
            'payment_method' => 'cash',
            'reference' => 'EXP-'.uniqid(),
            'allocation_method' => 'direct',
        ], $overrides));
    }

    private function payRent(Unit $unit, float $amount, Carbon $when): Payment
    {
        $tenant = User::factory()->create([
            'role' => 'tenant',
            'landlord_id' => $this->landlord->id,
        ]);
        $lease = Lease::factory()->create([
            'unit_id' => $unit->id,
            'tenant_id' => $tenant->id,
            'landlord_id' => $this->landlord->id,
            'start_date' => $when->copy()->subDays(7),
        ]);

        return Payment::create([
            'lease_id' => $lease->id,
            'landlord_id' => $this->landlord->id,
            'amount' => $amount,
            'currency' => 'KES',
            'payment_method' => 'cash',
            'payment_date' => $when,
            'reference' => 'PAY-'.uniqid(),
        ]);
    }

    public function test_estimated_value_column_exists(): void
    {
        $this->assertTrue(
            Schema::hasColumn('properties', 'estimated_value'),
            'BI-NOI-2: properties.estimated_value must exist for the cap-rate denominator.',
        );
    }

    public function test_allocation_method_column_exists_with_direct_default(): void
    {
        $this->assertTrue(
            Schema::hasColumn('expenses', 'allocation_method'),
            'BI-NOI-3: expenses.allocation_method must exist.',
        );

        // Insert via the raw DB so we test the DB default value
        // (not the model's PHP default). Use Carbon explicit values
        // to avoid PHP setting a different default.
        \DB::table('expenses')->insert([
            'landlord_id' => $this->landlord->id,
            'description' => 'test',
            'amount' => 5000,
            'expense_date' => Carbon::now()->toDateString(),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);
        $row = \DB::table('expenses')->latest('id')->first();
        $this->assertSame(
            'direct',
            (string) $row->allocation_method,
            'BI-NOI-3: allocation_method must default to "direct" so existing rows are unchanged.',
        );
    }

    public function test_noi_equals_revenue_minus_expenses(): void
    {
        $property = $this->property();
        $unit = $this->unitOn($property);

        $now = Carbon::now()->startOfMonth();
        $this->payRent($unit, 50000, $now->copy()->addDays(5));
        $this->makeExpense([
            'property_id' => $property->id,
            'amount' => 12000,
            'expense_date' => $now->copy()->addDays(10),
            'allocation_method' => 'direct',
        ]);

        $result = $this->service->byProperty(
            $this->landlord->id,
            $now->copy()->subDay(),
            $now->copy()->endOfMonth(),
        );

        $row = collect($result['properties'])->firstWhere('property_id', $property->id);

        $this->assertSame(50000.0, $row['revenue']);
        $this->assertSame(12000.0, $row['direct_expenses']);
        $this->assertSame(38000.0, $row['noi'], 'BI-NOI-1: NOI must equal revenue minus expenses.');
        $this->assertEqualsWithDelta(0.76, $row['noi_margin'], 0.001);
    }

    public function test_voided_payments_excluded_from_noi_revenue(): void
    {
        $property = $this->property();
        $unit = $this->unitOn($property);
        $now = Carbon::now()->startOfMonth();

        $payment = $this->payRent($unit, 30000, $now->copy()->addDays(2));
        $payment->update(['is_voided' => true]);

        $result = $this->service->byProperty(
            $this->landlord->id,
            $now->copy()->subDay(),
            $now->copy()->endOfMonth(),
        );
        $row = collect($result['properties'])->firstWhere('property_id', $property->id);

        $this->assertSame(0.0, $row['revenue'], 'BI-NOI-1: voided payments must NOT count as revenue.');
    }

    public function test_per_unit_allocation_splits_expense_by_unit_count(): void
    {
        $propA = $this->property();
        $propB = $this->property();
        // propA gets 3 units, propB gets 1 unit — 75/25 split.
        $unitA1 = $this->unitOn($propA);
        $this->unitOn($propA);
        $this->unitOn($propA);
        $unitB1 = $this->unitOn($propB);

        $now = Carbon::now()->startOfMonth();
        // Seed minimal revenue so both properties appear in the
        // per-revenue calculation if the test extends.
        $this->payRent($unitA1, 10000, $now->copy()->addDays(1));
        $this->payRent($unitB1, 10000, $now->copy()->addDays(1));

        $this->makeExpense([
            'amount' => 10000,
            'expense_date' => $now->copy()->addDays(5),
            'allocation_method' => 'per_unit',
            'property_id' => null,
            'building_id' => null,
            'unit_id' => null,
        ]);

        $result = $this->service->byProperty(
            $this->landlord->id,
            $now->copy()->subDay(),
            $now->copy()->endOfMonth(),
        );

        $rowA = collect($result['properties'])->firstWhere('property_id', $propA->id);
        $rowB = collect($result['properties'])->firstWhere('property_id', $propB->id);

        $this->assertEqualsWithDelta(7500.0, $rowA['allocated_expenses'], 0.01, 'BI-NOI-3: per_unit gives 3/4 of 10000 to propA.');
        $this->assertEqualsWithDelta(2500.0, $rowB['allocated_expenses'], 0.01, 'BI-NOI-3: per_unit gives 1/4 of 10000 to propB.');
    }

    public function test_cap_rate_annualises_short_window(): void
    {
        $property = $this->property(1_000_000.0);
        $unit = $this->unitOn($property);

        // 3-month window, 50000 revenue, no expenses → period NOI 50000.
        // Annualised = 50000 * (365 / ~91) ≈ 200000. Cap rate ≈ 20%.
        $start = Carbon::now()->subMonths(3)->startOfDay();
        $end = Carbon::now()->endOfDay();
        $this->payRent($unit, 50000, $start->copy()->addDays(10));

        $rows = $this->service->capRate($this->landlord->id, $start, $end);
        $row = collect($rows)->firstWhere('property_id', $property->id);

        $this->assertNotNull($row);
        $this->assertNotNull($row['cap_rate'], 'BI-NOI-2: cap_rate must be present when estimated_value is set.');
        $this->assertGreaterThan(0.15, $row['cap_rate'], 'BI-NOI-2: annualisation must scale a 3-month NOI by ~4x.');
        $this->assertLessThan(0.25, $row['cap_rate']);
        $this->assertSame('emerald', $row['band']);
    }

    public function test_cap_rate_is_null_when_estimated_value_absent(): void
    {
        $property = $this->property(null);
        $unit = $this->unitOn($property);
        $this->payRent($unit, 10000, Carbon::now()->subDays(5));

        $rows = $this->service->capRate(
            $this->landlord->id,
            Carbon::now()->subMonth(),
            Carbon::now(),
        );
        $row = collect($rows)->firstWhere('property_id', $property->id);

        $this->assertNull($row['cap_rate']);
        $this->assertSame('unknown', $row['band']);
    }

    public function test_cap_rate_band_assignment(): void
    {
        // 5% → amber, 7.5% → green, 12% → emerald.
        foreach ([
            [10_000_000.0, 50_000.0 * 12, 0.06, 'green'],
            [20_000_000.0, 50_000.0 * 12, 0.03, 'amber'],
            [5_000_000.0, 60_000.0 * 12, 0.144, 'emerald'],
        ] as [$value, $annualRevenue, $expectedRate, $expectedBand]) {
            $property = $this->property($value);
            $unit = $this->unitOn($property);

            $start = Carbon::now()->startOfYear();
            $end = Carbon::now()->endOfDay();
            // Distribute revenue evenly across YTD.
            $monthsElapsed = max(1, $start->diffInMonths($end) + 1);
            $perMonth = $annualRevenue / 12;
            for ($i = 0; $i < min($monthsElapsed, 12); $i++) {
                $this->payRent($unit, $perMonth, $start->copy()->addMonths($i)->addDays(2));
            }

            $rows = $this->service->capRate($this->landlord->id, $start, $end);
            $row = collect($rows)->firstWhere('property_id', $property->id);

            $this->assertSame($expectedBand, $row['band'], "BI-NOI-2: rate ≈ {$expectedRate} expected band '{$expectedBand}'.");
        }
    }

    public function test_noi_route_renders_inertia_page(): void
    {
        $this->actingAs($this->landlord)
            ->get(route('reports.noi'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Reports/Noi')
                ->has('byProperty')
                ->has('capRate'),
            );
    }

    public function test_noi_route_rejects_tenant_role(): void
    {
        $tenant = User::factory()->create(['role' => 'tenant']);
        $this->actingAs($tenant)
            ->get(route('reports.noi'))
            ->assertForbidden();
    }

    public function test_expense_allocation_constant_matches_runbook(): void
    {
        // BI-NOI-3 contract: the model constant + the runbook table +
        // the NoiService switch must agree. The watchdog asserts every
        // constant value appears in the runbook table.
        $runbook = (string) file_get_contents(base_path('docs/runbooks/bi.md'));
        foreach (Expense::ALLOCATION_METHODS as $method) {
            $this->assertStringContainsString(
                $method,
                $runbook,
                "BI-NOI-3: docs/runbooks/bi.md must document allocation method '{$method}'.",
            );
        }
    }
}
