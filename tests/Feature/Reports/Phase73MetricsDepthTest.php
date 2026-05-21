<?php

declare(strict_types=1);

namespace Tests\Feature\Reports;

use App\Models\ReportMetric;
use App\Models\User;
use App\Services\Reports\ReportBuilderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * Phase-73 METRICS-DEPTH: the live no-persist formula validation endpoint,
 * the metrics manage page, and the extended ReportBuilderService allow-list
 * (new safe dimensions map to real columns; off-list fields still rejected).
 */
class Phase73MetricsDepthTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    private User $landlord;

    protected function setUp(): void
    {
        parent::setUp();

        $this->landlord = $this->createLandlordWithFullSetup()['landlord'];
    }

    public function test_validate_accepts_a_well_formed_formula(): void
    {
        $this->actingAs($this->landlord)
            ->postJson(route('reports.metrics.validate'), [
                'expression' => '{invoice.amount_paid} / {invoice.total_due} * 100',
            ])
            ->assertOk()
            ->assertJson(['valid' => true]);

        $this->assertSame(0, ReportMetric::withoutGlobalScopes()->count());
    }

    public function test_validate_rejects_a_malformed_formula_without_persisting(): void
    {
        $this->actingAs($this->landlord)
            ->postJson(route('reports.metrics.validate'), [
                'expression' => '{invoice.amount_paid} + + {invoice.total_due}',
            ])
            ->assertOk()
            ->assertJson(['valid' => false]);

        $this->assertSame(0, ReportMetric::withoutGlobalScopes()->count());
    }

    public function test_validate_rejects_an_injection_payload(): void
    {
        foreach ([
            "system('rm -rf /')",
            '${jndi:ldap://x}',
            "'; DROP TABLE users; --",
            '{users.password}',
            '{invoice.status} + 1', // non-numeric field cannot appear in a metric
        ] as $payload) {
            $this->actingAs($this->landlord)
                ->postJson(route('reports.metrics.validate'), ['expression' => $payload])
                ->assertOk()
                ->assertJson(['valid' => false], "Injection payload accepted: {$payload}");
        }

        $this->assertSame(0, ReportMetric::withoutGlobalScopes()->count());
    }

    public function test_manage_page_renders_with_field_catalogue(): void
    {
        $this->actingAs($this->landlord)
            ->get(route('reports.metrics.manage'))
            ->assertOk();
    }

    public function test_store_persists_a_metric_and_caches_rpn(): void
    {
        $this->actingAs($this->landlord)
            ->post(route('reports.metrics.store'), [
                'name' => 'Collection rate',
                'expression' => '{invoice.amount_paid} / {invoice.total_due} * 100',
                'unit' => '%',
            ])
            ->assertRedirect(route('reports.metrics.manage'));

        $metric = ReportMetric::withoutGlobalScopes()->firstOrFail();
        $this->assertSame('Collection rate', $metric->name);
        $this->assertNotEmpty($metric->parsed_rpn);
    }

    public function test_new_allow_list_dimensions_map_to_real_columns(): void
    {
        $newFields = [
            'payment.reconciliation_status',
            'invoice.rent_due',
            'invoice.arrears',
            'invoice.late_fees_total',
            'invoice.billing_period_start',
            'lease.end_date',
            'lease.deposit_amount',
            'lease.service_charge',
        ];

        foreach ($newFields as $key) {
            $this->assertArrayHasKey($key, ReportBuilderService::ALLOWED_FIELDS, "Missing new dimension {$key}");
            $meta = ReportBuilderService::ALLOWED_FIELDS[$key];
            $this->assertContains($meta['table'], ReportBuilderService::ALLOWED_TABLES);
            $this->assertTrue(
                Schema::hasColumn($meta['table'], $meta['column']),
                "New dimension {$key} references non-existent column {$meta['table']}.{$meta['column']}",
            );
        }
    }

    public function test_builder_selects_and_filters_on_new_dimensions(): void
    {
        // Proves the new columns are real + queryable: select two new numeric
        // dimensions and filter on one (parameterised, no DB::raw).
        $rows = app(ReportBuilderService::class)->run([
            'table' => 'invoices',
            'fields' => ['invoice.arrears', 'invoice.rent_due', 'invoice.late_fees_total'],
            'filters' => [['field' => 'invoice.arrears', 'op' => '>=', 'value' => 0]],
        ], (int) $this->landlord->id);

        $this->assertIsArray($rows);
    }

    public function test_builder_groups_by_a_new_dimension(): void
    {
        // Grouping selects only the grouped field (only_full_group_by safe).
        $rows = app(ReportBuilderService::class)->run([
            'table' => 'payments',
            'fields' => ['payment.reconciliation_status'],
            'group_by' => ['payment.reconciliation_status'],
        ], (int) $this->landlord->id);

        $this->assertIsArray($rows);
    }

    public function test_builder_still_rejects_an_off_list_field(): void
    {
        $this->expectException(\Illuminate\Validation\ValidationException::class);

        app(ReportBuilderService::class)->run([
            'table' => 'invoices',
            'fields' => ['invoice.secret_column'],
        ], (int) $this->landlord->id);
    }
}
