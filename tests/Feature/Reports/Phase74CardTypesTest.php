<?php

declare(strict_types=1);

namespace Tests\Feature\Reports;

use App\Models\LandlordDashboard;
use App\Models\ReportMetric;
use App\Models\SavedReport;
use App\Models\User;
use App\Services\Reports\DashboardService;
use App\Services\Reports\ReportBuilderService;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * Phase-74 CARD-TYPES: kpi (aggregate), chart (label+value points), and text
 * (static note) cards render through the registry + fail closed on bad/foreign
 * input.
 */
class Phase74CardTypesTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    private User $landlord;

    private User $otherLandlord;

    private SavedReport $report;

    private ReportMetric $metric;

    protected function setUp(): void
    {
        parent::setUp();

        $this->landlord = $this->createLandlordWithFullSetup()['landlord'];
        $this->otherLandlord = $this->createLandlordWithFullSetup()['landlord'];

        $this->actingAs($this->landlord);
        $this->report = SavedReport::create([
            'name' => 'Payments',
            'config' => ['table' => 'payments', 'fields' => ['payment.amount'], 'filters' => [], 'group_by' => [], 'sort_by' => [], 'limit' => 50],
        ]);
        $this->metric = ReportMetric::create([
            'slug' => 'amount',
            'name' => 'Amount',
            'expression' => '{payment.amount}',
            'parsed_rpn' => app(\App\Services\Reports\MetricFormulaService::class)->parse('{payment.amount}'),
            'unit' => 'KES',
        ]);
    }

    private function dashboard(array $layout): LandlordDashboard
    {
        $this->actingAs($this->landlord);

        return LandlordDashboard::create([
            'name' => 'D',
            'slug' => 'd-'.uniqid(),
            'layout' => $layout,
        ]);
    }

    public function test_kpi_card_aggregates_the_metric(): void
    {
        Model::withoutEvents(function () {
            PaymentFactory::new()->create(['landlord_id' => $this->landlord->id, 'amount' => 100]);
            PaymentFactory::new()->create(['landlord_id' => $this->landlord->id, 'amount' => 200]);
        });

        $dashboard = $this->dashboard([
            ['type' => 'kpi', 'saved_report_id' => $this->report->id, 'metric_slug' => 'amount', 'agg' => 'sum'],
        ]);

        $payload = app(DashboardService::class)->buildPayload($dashboard);
        $card = $payload['cards'][0];

        $this->assertSame('kpi', $card['type']);
        $this->assertSame('sum', $card['agg']);
        $this->assertSame(2, $card['count']);
        $this->assertEqualsWithDelta(300.0, $card['value'], 0.01);
        $this->assertSame('KES', $card['unit']);
    }

    public function test_kpi_rejects_an_invalid_aggregate(): void
    {
        $this->expectException(ValidationException::class);
        app(DashboardService::class)->validateLayout([
            ['type' => 'kpi', 'saved_report_id' => $this->report->id, 'metric_slug' => 'amount', 'agg' => 'median'],
        ], (int) $this->landlord->id);
    }

    public function test_kpi_rejects_a_cross_tenant_metric(): void
    {
        $this->actingAs($this->otherLandlord);
        $foreignMetric = ReportMetric::create([
            'slug' => 'theirs',
            'name' => 'Theirs',
            'expression' => '{payment.amount}',
            'parsed_rpn' => app(\App\Services\Reports\MetricFormulaService::class)->parse('{payment.amount}'),
        ]);

        $this->expectException(ValidationException::class);
        app(DashboardService::class)->validateLayout([
            ['type' => 'kpi', 'saved_report_id' => $this->report->id, 'metric_slug' => $foreignMetric->slug, 'agg' => 'avg'],
        ], (int) $this->landlord->id);
    }

    public function test_chart_card_returns_points(): void
    {
        Model::withoutEvents(function () {
            PaymentFactory::new()->create(['landlord_id' => $this->landlord->id, 'amount' => 100]);
        });

        $key = array_keys(app(ReportBuilderService::class)->run($this->report->config, (int) $this->landlord->id)[0])[0];

        $dashboard = $this->dashboard([
            ['type' => 'chart', 'saved_report_id' => $this->report->id, 'label_field' => $key, 'value_field' => $key],
        ]);

        $payload = app(DashboardService::class)->buildPayload($dashboard);
        $card = $payload['cards'][0];

        $this->assertSame('chart', $card['type']);
        $this->assertCount(1, $card['points']);
        $this->assertSame(100.0, $card['points'][0]['value']);
    }

    public function test_chart_rejects_an_unknown_field(): void
    {
        Model::withoutEvents(function () {
            PaymentFactory::new()->create(['landlord_id' => $this->landlord->id, 'amount' => 100]);
        });

        $dashboard = $this->dashboard([
            ['type' => 'chart', 'saved_report_id' => $this->report->id, 'label_field' => 'nope', 'value_field' => 'nope'],
        ]);

        $this->expectException(ValidationException::class);
        app(DashboardService::class)->buildPayload($dashboard);
    }

    public function test_chart_requires_fields(): void
    {
        $this->expectException(ValidationException::class);
        app(DashboardService::class)->validateLayout([
            ['type' => 'chart', 'saved_report_id' => $this->report->id],
        ], (int) $this->landlord->id);
    }

    public function test_text_card_renders_escaped_body(): void
    {
        $dashboard = $this->dashboard([
            ['type' => 'text', 'body' => 'Quarterly review', 'title' => 'Note'],
        ]);

        $payload = app(DashboardService::class)->buildPayload($dashboard);
        $card = $payload['cards'][0];

        $this->assertSame('text', $card['type']);
        $this->assertSame('Quarterly review', $card['body']);
    }

    public function test_text_card_rejects_oversize_body(): void
    {
        $this->expectException(ValidationException::class);
        app(DashboardService::class)->validateLayout([
            ['type' => 'text', 'body' => str_repeat('x', 2001)],
        ], (int) $this->landlord->id);
    }
}
