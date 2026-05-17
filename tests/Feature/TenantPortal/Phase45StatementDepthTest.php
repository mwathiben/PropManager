<?php

declare(strict_types=1);

namespace Tests\Feature\TenantPortal;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\TenantStatementPreference;
use App\Models\User;
use App\Services\Tenant\StatementService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * Phase-45 STATEMENT-DEPTH-1/2/3:
 *  - new periods: calendar_year, last_12_months, custom
 *  - filters: ?types[]=&min_amount=&max_amount=
 *  - tenant column persistence: tenant_statement_preferences table
 */
class Phase45StatementDepthTest extends TestCase
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

    public function test_calendar_year_period_spans_jan_through_dec_of_current_year(): void
    {
        $response = $this->actingAs($this->tenant)
            ->get(route('tenant.statement.index', ['period' => 'calendar_year']));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('period', 'calendar_year')
            ->where('from', CarbonImmutable::now()->startOfYear()->toDateString())
            ->where('to', CarbonImmutable::now()->endOfYear()->toDateString())
        );
    }

    public function test_last_12_months_period_rolls_back_one_year_from_current_month(): void
    {
        $response = $this->actingAs($this->tenant)
            ->get(route('tenant.statement.index', ['period' => 'last_12_months']));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('period', 'last_12_months')
            ->where('from', CarbonImmutable::now()->subMonths(12)->startOfMonth()->toDateString())
            ->where('to', CarbonImmutable::now()->endOfMonth()->toDateString())
        );
    }

    public function test_custom_period_uses_from_and_to_query_params(): void
    {
        $now = CarbonImmutable::now();
        $from = $now->subMonths(2)->startOfMonth();
        $to = $now->subMonth()->endOfMonth();

        $response = $this->actingAs($this->tenant)->get(route('tenant.statement.index', [
            'period' => 'custom',
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
        ]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('period', 'custom')
            ->where('from', $from->toDateString())
            ->where('to', $to->toDateString())
        );
    }

    public function test_custom_period_clamps_to_today_when_to_in_future(): void
    {
        $future = CarbonImmutable::now()->addYears(2)->toDateString();

        $response = $this->actingAs($this->tenant)->get(route('tenant.statement.index', [
            'period' => 'custom',
            'from' => '2026-01-01',
            'to' => $future,
        ]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('to', CarbonImmutable::now()->endOfDay()->toDateString())
        );
    }

    public function test_filter_types_drops_invoice_rows_when_only_payment_requested(): void
    {
        $this->seedActivity();

        $rows = app(StatementService::class)->forTenant(
            $this->tenant,
            CarbonImmutable::parse('2026-01-01'),
            CarbonImmutable::parse('2026-03-31'),
            ['types' => ['payment']],
        );

        $this->assertSame(0, $rows->where('kind', 'invoice')->count(), 'invoice rows filtered out');
        $this->assertGreaterThan(0, $rows->where('kind', 'payment')->count(), 'payment rows preserved');
        $this->assertNotNull($rows->where('kind', 'opening')->first(), 'opening row always rendered');
        $this->assertNotNull($rows->where('kind', 'closing')->first(), 'closing row always rendered');
    }

    public function test_filter_min_amount_drops_small_transactions(): void
    {
        $this->seedActivity();

        $rows = app(StatementService::class)->forTenant(
            $this->tenant,
            CarbonImmutable::parse('2026-01-01'),
            CarbonImmutable::parse('2026-03-31'),
            ['min_amount' => 30000.0],
        );

        // Seeded activity uses 25000 charges + 25000 payments — below 30000.
        $this->assertSame(0, $rows->where('kind', 'invoice')->count());
        $this->assertSame(0, $rows->where('kind', 'payment')->count());

        // Running balance still walks every event — closing should match
        // unfiltered closing.
        $unfiltered = app(StatementService::class)->forTenant(
            $this->tenant,
            CarbonImmutable::parse('2026-01-01'),
            CarbonImmutable::parse('2026-03-31'),
        );

        $this->assertEqualsWithDelta(
            $unfiltered->where('kind', 'closing')->first()['running_balance'],
            $rows->where('kind', 'closing')->first()['running_balance'],
            0.01,
            'filtering must not change the closing balance',
        );
    }

    public function test_monthly_subtotals_returns_one_row_per_calendar_month(): void
    {
        $this->seedActivity();

        $months = app(StatementService::class)->monthlySubtotals(
            $this->tenant,
            CarbonImmutable::parse('2026-01-01'),
            CarbonImmutable::parse('2026-03-31'),
        );

        $this->assertCount(3, $months);
        $this->assertSame(['2026-01', '2026-02', '2026-03'], $months->pluck('month')->all());

        // Jan: 25k charge + 25k payment → net 0
        // Feb: 25k charge + 25k payment → net 0
        // Mar: 25k charge + 0 payment → net 25000
        $this->assertEqualsWithDelta(0.0, $months[0]['net'], 0.01);
        $this->assertEqualsWithDelta(0.0, $months[1]['net'], 0.01);
        $this->assertEqualsWithDelta(25000.0, $months[2]['net'], 0.01);
    }

    public function test_xlsx_includes_monthly_summary_sheet_for_multi_month_windows(): void
    {
        $this->seedActivity();

        $response = $this->actingAs($this->tenant)
            ->get(route('tenant.statement.xlsx', ['period' => 'year_to_date']));

        $response->assertOk();
        $sheetNames = $this->extractSheetNames($response->getFile()->getRealPath());

        $this->assertCount(2, $sheetNames, 'multi-month window must produce 2 sheets');
        $this->assertContains('Monthly Summary', $sheetNames);
    }

    public function test_xlsx_skips_monthly_summary_for_single_month_window(): void
    {
        $this->seedActivity();

        $response = $this->actingAs($this->tenant)
            ->get(route('tenant.statement.xlsx', ['period' => 'current_month']));

        $response->assertOk();
        $sheetNames = $this->extractSheetNames($response->getFile()->getRealPath());

        $this->assertCount(1, $sheetNames, 'single-month window must produce 1 sheet');
        $this->assertNotContains('Monthly Summary', $sheetNames);
    }

    /**
     * @return list<string>
     */
    private function extractSheetNames(string $path): array
    {
        $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($path);

        return $spreadsheet->getSheetNames();
    }

    public function test_preferences_endpoint_persists_selected_columns(): void
    {
        $this->actingAs($this->tenant)
            ->patch(route('tenant.statement.preferences'), [
                'columns' => ['date', 'description', 'running_balance'],
            ])
            ->assertRedirect();

        $row = TenantStatementPreference::query()->where('user_id', $this->tenant->id)->first();
        $this->assertNotNull($row);
        $this->assertSame(['date', 'description', 'running_balance'], $row->columns);
    }

    public function test_preferences_endpoint_rejects_unknown_columns(): void
    {
        $this->actingAs($this->tenant)
            ->patch(route('tenant.statement.preferences'), [
                'columns' => ['date', 'lifetime_balance'],
            ])
            ->assertInvalid('columns.1');
    }

    public function test_columns_for_returns_default_set_when_no_preference_exists(): void
    {
        $this->assertSame(
            TenantStatementPreference::DEFAULT_COLUMNS,
            TenantStatementPreference::columnsFor($this->tenant),
        );
    }

    public function test_columns_for_returns_default_when_preference_row_is_empty_after_filtering(): void
    {
        TenantStatementPreference::create([
            'user_id' => $this->tenant->id,
            'columns' => ['bogus_column'],
        ]);

        $this->assertSame(
            TenantStatementPreference::DEFAULT_COLUMNS,
            TenantStatementPreference::columnsFor($this->tenant),
        );
    }

    private function seedActivity(): void
    {
        $invoiceDates = ['2026-01-05', '2026-02-05', '2026-03-05'];
        foreach ($invoiceDates as $i => $date) {
            Invoice::create([
                'lease_id' => $this->lease->id,
                'landlord_id' => $this->landlord->id,
                'invoice_number' => 'INV-2026-'.($i + 1),
                'rent_due' => 25000,
                'water_due' => 0,
                'arrears' => 0,
                'wallet_applied' => 0,
                'total_due' => 25000,
                'amount_paid' => 0,
                'status' => 'sent',
                'billing_period_start' => $date,
                'due_date' => $date,
            ]);
        }

        Payment::create([
            'lease_id' => $this->lease->id,
            'landlord_id' => $this->landlord->id,
            'amount' => 25000,
            'payment_method' => 'mpesa',
            'payment_date' => '2026-01-10',
            'reference' => 'MPESA-001',
            'is_voided' => false,
        ]);

        Payment::create([
            'lease_id' => $this->lease->id,
            'landlord_id' => $this->landlord->id,
            'amount' => 25000,
            'payment_method' => 'mpesa',
            'payment_date' => '2026-02-10',
            'reference' => 'MPESA-002',
            'is_voided' => false,
        ]);
    }
}
