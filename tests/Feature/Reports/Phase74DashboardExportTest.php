<?php

declare(strict_types=1);

namespace Tests\Feature\Reports;

use App\Models\LandlordDashboard;
use App\Models\SavedReport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * Phase-74 DASH-EXPORT: owner-only PDF + XLSX export of a dashboard. Cross-tenant
 * exports 404 via TenantScope route-model binding.
 */
class Phase74DashboardExportTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    private User $landlord;

    private User $otherLandlord;

    protected function setUp(): void
    {
        parent::setUp();

        $this->landlord = $this->createLandlordWithFullSetup()['landlord'];
        $this->otherLandlord = $this->createLandlordWithFullSetup()['landlord'];
    }

    private function dashboardFor(User $owner): LandlordDashboard
    {
        $this->actingAs($owner);

        return LandlordDashboard::create([
            'name' => 'Board',
            'slug' => 'd-'.uniqid(),
            'layout' => [['type' => 'text', 'body' => 'Quarterly note', 'size' => 'wide']],
        ]);
    }

    public function test_owner_can_export_pdf(): void
    {
        $dashboard = $this->dashboardFor($this->landlord);

        $response = $this->actingAs($this->landlord)->get(route('dashboards.export-pdf', $dashboard->id));

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('content-type'));
    }

    public function test_owner_can_export_xlsx(): void
    {
        $dashboard = $this->dashboardFor($this->landlord);

        $response = $this->actingAs($this->landlord)->get(route('dashboards.export-xlsx', $dashboard->id));

        $response->assertOk();
        $this->assertStringContainsString(
            'spreadsheetml',
            (string) $response->headers->get('content-type'),
        );
    }

    public function test_cannot_export_another_landlords_dashboard_pdf(): void
    {
        $foreign = $this->dashboardFor($this->otherLandlord);

        $this->actingAs($this->landlord)
            ->get(route('dashboards.export-pdf', $foreign->id))
            ->assertNotFound();
    }

    public function test_cannot_export_another_landlords_dashboard_xlsx(): void
    {
        $foreign = $this->dashboardFor($this->otherLandlord);

        $this->actingAs($this->landlord)
            ->get(route('dashboards.export-xlsx', $foreign->id))
            ->assertNotFound();
    }

    public function test_xlsx_handles_a_data_card_title_with_invalid_sheet_chars(): void
    {
        // A saved_report card produces a sheet whose title derives from the
        // (free-text) card title; chars like : / [ ] are illegal in xlsx sheet
        // names and must be stripped, not 500.
        $this->actingAs($this->landlord);
        $report = SavedReport::create([
            'name' => 'Payments',
            'config' => ['table' => 'payments', 'fields' => ['payment.amount'], 'filters' => [], 'group_by' => [], 'sort_by' => [], 'limit' => 50],
        ]);
        $dashboard = LandlordDashboard::create([
            'name' => 'Board',
            'slug' => 'd-'.uniqid(),
            'layout' => [['type' => 'saved_report', 'saved_report_id' => $report->id, 'title' => 'Q1: Profit/Loss [North]', 'size' => 'wide']],
        ]);

        $this->actingAs($this->landlord)
            ->get(route('dashboards.export-xlsx', $dashboard->id))
            ->assertOk();
    }
}
