<?php

declare(strict_types=1);

namespace Tests\Feature\Reports;

use App\Models\Expense;
use App\Services\OwnerStatementService;
use App\Services\PropertyPnlService;
use App\Services\RentRollService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * Phase-100 REPORTS-DEPTH-3: rent-roll snapshot (per-property P&L + owner statements
 * added as those sub-phases land).
 */
class Phase100ReportsDepthTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    // --- 100-A RENT ROLL --------------------------------------------------

    public function test_rent_roll_reports_occupied_vacant_and_financial_position(): void
    {
        $setup = $this->createLandlordWithFullSetup();
        $landlord = $setup['landlord'];
        $unit = $setup['units']->first();

        ['lease' => $lease] = $this->createTenantWithActiveLease($landlord, $unit);
        $this->createInvoiceForLease($lease, 'sent'); // unpaid → outstanding 25000

        $report = app(RentRollService::class)->forLandlord($landlord->id);

        $this->assertCount(8, $report['rows']);

        $occupiedRow = collect($report['rows'])->firstWhere('unit', $unit->unit_number);
        $this->assertSame('occupied', $occupiedRow['status']);
        $this->assertEqualsWithDelta(25000.0, $occupiedRow['rent'], 0.01);
        $this->assertEqualsWithDelta(25000.0, $occupiedRow['deposit_held'], 0.01);
        $this->assertEqualsWithDelta(25000.0, $occupiedRow['outstanding'], 0.01);

        $t = $report['totals'];
        $this->assertSame(8, $t['units']);
        $this->assertSame(1, $t['occupied']);
        $this->assertSame(7, $t['vacant']);
        $this->assertEqualsWithDelta(25000.0, $t['monthly_rent'], 0.01);
        $this->assertEqualsWithDelta(25000.0, $t['deposits_held'], 0.01);
        $this->assertEqualsWithDelta(25000.0, $t['outstanding'], 0.01);
        $this->assertEqualsWithDelta(12.5, $t['occupancy_rate'], 0.01);
    }

    public function test_rent_roll_flags_a_lease_expiring_within_60_days(): void
    {
        $setup = $this->createLandlordWithFullSetup();
        $landlord = $setup['landlord'];
        $unit = $setup['units']->first();

        ['lease' => $lease] = $this->createTenantWithActiveLease($landlord, $unit);
        $lease->update(['end_date' => now()->addDays(30)]);

        $report = app(RentRollService::class)->forLandlord($landlord->id);
        $row = collect($report['rows'])->firstWhere('unit', $unit->unit_number);

        $this->assertSame('expiring', $row['status']);
        $this->assertSame(1, $report['totals']['expiring']);
    }

    public function test_rent_roll_is_scoped_to_the_landlord(): void
    {
        $setup = $this->createLandlordWithFullSetup();
        $other = $this->createLandlordWithFullSetup();
        $this->createTenantWithActiveLease($other['landlord'], $other['units']->first());

        $report = app(RentRollService::class)->forLandlord($setup['landlord']->id);

        // Only this landlord's 8 units — never the other landlord's.
        $this->assertCount(8, $report['rows']);
        $this->assertSame(0, $report['totals']['occupied']);
    }

    public function test_rent_roll_export_endpoint_returns_each_format(): void
    {
        $setup = $this->createLandlordWithFullSetup();
        $landlord = $setup['landlord'];
        ['lease' => $lease] = $this->createTenantWithActiveLease($landlord, $setup['units']->first());

        $xlsx = $this->actingAs($landlord)->get(route('finances.reports.rent-roll', ['format' => 'xlsx']));
        $xlsx->assertOk();
        $this->assertStringContainsString('spreadsheetml', $xlsx->headers->get('content-type'));

        $pdf = $this->actingAs($landlord)->get(route('finances.reports.rent-roll', ['format' => 'pdf']));
        $pdf->assertOk();
        $this->assertSame('application/pdf', $pdf->headers->get('content-type'));

        $csv = $this->actingAs($landlord)->get(route('finances.reports.rent-roll', ['format' => 'csv']));
        $csv->assertOk();
        $this->assertStringContainsString('text/csv', $csv->headers->get('content-type'));
        $this->assertStringContainsString($lease->tenant->name, $csv->getContent());
    }

    public function test_rent_roll_endpoint_forbidden_for_a_tenant(): void
    {
        $setup = $this->createLandlordWithFullSetup();
        ['tenant' => $tenant] = $this->createTenantWithActiveLease($setup['landlord'], $setup['units']->first());

        $this->actingAs($tenant->fresh())
            ->get(route('finances.reports.rent-roll', ['format' => 'csv']))
            ->assertForbidden();
    }

    // --- 100-B PER-PROPERTY P&L ------------------------------------------

    public function test_property_pnl_computes_collected_expenses_and_net(): void
    {
        $setup = $this->createLandlordWithFullSetup();
        $landlord = $setup['landlord'];
        ['lease' => $lease] = $this->createTenantWithActiveLease($landlord, $setup['units']->first());

        $this->createPaymentWithInvoice($lease, 25000); // collected this period
        $this->recordExpense($landlord->id, $setup['building']->id, 5000);

        $report = app(PropertyPnlService::class)->forLandlord(
            $landlord->id,
            Carbon::now()->subMonth(),
            Carbon::now()->addDay(),
        );

        $this->assertCount(1, $report['rows']);
        $row = $report['rows'][0];
        $this->assertEqualsWithDelta(25000.0, $row['collected'], 0.01);
        $this->assertEqualsWithDelta(5000.0, $row['expenses'], 0.01);
        $this->assertEqualsWithDelta(20000.0, $row['net'], 0.01);

        $this->assertEqualsWithDelta(25000.0, $report['totals']['collected'], 0.01);
        $this->assertEqualsWithDelta(20000.0, $report['totals']['net'], 0.01);
    }

    public function test_property_pnl_is_scoped_to_the_landlord(): void
    {
        $setup = $this->createLandlordWithFullSetup();
        $other = $this->createLandlordWithFullSetup();
        ['lease' => $otherLease] = $this->createTenantWithActiveLease($other['landlord'], $other['units']->first());
        $this->createPaymentWithInvoice($otherLease, 99000);

        $report = app(PropertyPnlService::class)->forLandlord(
            $setup['landlord']->id, Carbon::now()->subMonth(), Carbon::now()->addDay(),
        );

        $this->assertEqualsWithDelta(0.0, $report['totals']['collected'], 0.01);
    }

    public function test_property_pnl_excludes_voided_payments(): void
    {
        $setup = $this->createLandlordWithFullSetup();
        $landlord = $setup['landlord'];
        ['lease' => $lease] = $this->createTenantWithActiveLease($landlord, $setup['units']->first());

        $this->createPaymentWithInvoice($lease, 10000);
        ['payment' => $voided] = $this->createPaymentWithInvoice($lease, 5000);
        $voided->forceFill(['is_voided' => true, 'voided_at' => now()])->save();

        $report = app(PropertyPnlService::class)->forLandlord(
            $landlord->id, Carbon::now()->subMonth(), Carbon::now()->addDay(),
        );

        // Only the non-voided 10,000 is collected revenue.
        $this->assertEqualsWithDelta(10000.0, $report['totals']['collected'], 0.01);
    }

    public function test_property_pnl_export_endpoint_returns_each_format(): void
    {
        $setup = $this->createLandlordWithFullSetup();
        $landlord = $setup['landlord'];

        $xlsx = $this->actingAs($landlord)->get(route('finances.reports.property-pnl', ['format' => 'xlsx', 'period' => '12']));
        $xlsx->assertOk();
        $this->assertStringContainsString('spreadsheetml', $xlsx->headers->get('content-type'));

        $csv = $this->actingAs($landlord)->get(route('finances.reports.property-pnl', ['format' => 'csv', 'period' => '12']));
        $csv->assertOk();
        $this->assertStringContainsString('text/csv', $csv->headers->get('content-type'));
    }

    // --- 100-C OWNER STATEMENT -------------------------------------------

    public function test_owner_statement_totals_collected_expenses_and_net(): void
    {
        $setup = $this->createLandlordWithFullSetup();
        $landlord = $setup['landlord'];
        ['lease' => $lease] = $this->createTenantWithActiveLease($landlord, $setup['units']->first());

        $this->createPaymentWithInvoice($lease, 25000);
        $this->recordExpense($landlord->id, $setup['building']->id, 5000);

        $data = app(OwnerStatementService::class)->forProperty(
            $landlord->id, $setup['property']->id, Carbon::now()->subMonth(), Carbon::now()->addDay(),
        );

        $this->assertNotNull($data);
        $this->assertEqualsWithDelta(25000.0, $data['collected'], 0.01);
        $this->assertEqualsWithDelta(5000.0, $data['total_expenses'], 0.01);
        $this->assertEqualsWithDelta(20000.0, $data['net'], 0.01);
        $this->assertCount(1, $data['expenses']);
    }

    public function test_owner_statement_pdf_endpoint(): void
    {
        $setup = $this->createLandlordWithFullSetup();

        $pdf = $this->actingAs($setup['landlord'])->get(route('finances.reports.owner-statement', [
            'property_id' => $setup['property']->id, 'period' => '12',
        ]));

        $pdf->assertOk();
        $this->assertSame('application/pdf', $pdf->headers->get('content-type'));
    }

    public function test_owner_statement_404_for_another_landlords_property(): void
    {
        $setup = $this->createLandlordWithFullSetup();
        $other = $this->createLandlordWithFullSetup();

        $this->actingAs($setup['landlord'])->get(route('finances.reports.owner-statement', [
            'property_id' => $other['property']->id, 'period' => '12',
        ]))->assertNotFound();
    }

    private function recordExpense(int $landlordId, int $buildingId, float $amount): void
    {
        Expense::create([
            'landlord_id' => $landlordId,
            'building_id' => $buildingId,
            'description' => 'Test expense',
            'amount' => $amount,
            'expense_date' => now(),
        ]);
    }
}
