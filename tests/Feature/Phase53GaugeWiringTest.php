<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Building;
use App\Models\KycRequirement;
use App\Models\Lease;
use App\Models\Property;
use App\Models\SavedReport;
use App\Models\TenantKycSubmission;
use App\Models\Unit;
use App\Models\User;
use App\Services\I18n\TranslationCostTracker;
use App\Services\MetricsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Tests\TestCase;

/**
 * Phase-53 GAUGE-WIRING-1/2/3 watchdog. Verifies each of the three
 * deferred Prometheus gauges (tenant_kyc_blocked_count from Phase 48,
 * report_render_failure_count from Phase 50, i18n_translation_spend_usd_24h
 * from Phase 52) now has a working emitter.
 */
class Phase53GaugeWiringTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_tenant_kyc_blocked_audit_emits_gauge_with_blocked_count(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        $property = Property::factory()->create(['landlord_id' => $landlord->id]);
        $building = Building::factory()->create([
            'property_id' => $property->id,
            'landlord_id' => $landlord->id,
        ]);
        $unit = Unit::factory()->create([
            'building_id' => $building->id,
            'landlord_id' => $landlord->id,
        ]);

        // Required KYC: 2 items, platform-wide.
        $req1 = KycRequirement::create([
            'landlord_id' => null,
            'building_id' => null,
            'label' => 'ID front',
            'requirement_type' => 'document',
            'is_active' => true,
            'is_required' => true,
        ]);
        KycRequirement::create([
            'landlord_id' => null,
            'building_id' => null,
            'label' => 'ID back',
            'requirement_type' => 'document',
            'is_active' => true,
            'is_required' => true,
        ]);

        // Blocked tenant — has 1 of 2 required submissions.
        $blocked = User::factory()->create([
            'role' => 'tenant',
            'landlord_id' => $landlord->id,
        ]);
        Lease::factory()->create([
            'tenant_id' => $blocked->id,
            'unit_id' => $unit->id,
            'landlord_id' => $landlord->id,
            'is_active' => true,
        ]);
        TenantKycSubmission::create([
            'user_id' => $blocked->id,
            'requirement_id' => $req1->id,
            'landlord_id' => $landlord->id,
            'status' => 'pending',
        ]);

        // Unblocked tenant — meets both.
        $unblocked = User::factory()->create([
            'role' => 'tenant',
            'landlord_id' => $landlord->id,
        ]);
        $unit2 = Unit::factory()->create([
            'building_id' => $building->id,
            'landlord_id' => $landlord->id,
        ]);
        Lease::factory()->create([
            'tenant_id' => $unblocked->id,
            'unit_id' => $unit2->id,
            'landlord_id' => $landlord->id,
            'is_active' => true,
        ]);
        foreach (KycRequirement::all() as $req) {
            TenantKycSubmission::create([
                'user_id' => $unblocked->id,
                'requirement_id' => $req->id,
                'landlord_id' => $landlord->id,
                'status' => 'pending',
            ]);
        }

        $captured = null;
        $metrics = Mockery::mock(MetricsService::class)->makePartial();
        $metrics->shouldReceive('gauge')
            ->withArgs(function (string $name, $value, array $labels = []) use (&$captured) {
                if ($name === 'tenant_kyc_blocked_count') {
                    $captured = (float) $value;

                    return true;
                }

                return false;
            })
            ->andReturnNull();
        $this->app->instance(MetricsService::class, $metrics);

        $exit = \Artisan::call('tenant-kyc:blocked-audit');

        $this->assertSame(0, $exit);
        $this->assertSame(1.0, $captured, 'Expected exactly one blocked tenant in gauge value.');
    }

    public function test_tenant_kyc_blocked_audit_dry_run_skips_gauge(): void
    {
        $metrics = Mockery::mock(MetricsService::class)->makePartial();
        $metrics->shouldNotReceive('gauge');
        $this->app->instance(MetricsService::class, $metrics);

        $exit = \Artisan::call('tenant-kyc:blocked-audit', ['--dry-run' => true]);

        $this->assertSame(0, $exit);
    }

    public function test_i18n_spend_audit_emits_gauge_from_cost_tracker(): void
    {
        Cache::flush();

        $tracker = app(TranslationCostTracker::class);
        $tracker->record('deepl', 'ar', 1000, 0.025); // $0.025 to ar
        $tracker->record('google', 'sw', 500, 0.010); // $0.010 to sw

        $metrics = Mockery::mock(MetricsService::class)->makePartial();
        $metrics->shouldReceive('gauge')
            ->withArgs(function (string $name, float $value, array $labels = []) {
                if ($name === 'i18n_translation_spend_usd_24h' && abs($value - 0.035) < 0.0001) {
                    return true;
                }
                if ($name === 'i18n_translation_spend_usd_24h_by_locale' && isset($labels['locale'])) {
                    return true;
                }

                return false;
            })
            ->atLeast()->once()
            ->andReturnNull();
        $this->app->instance(MetricsService::class, $metrics);

        $exit = \Artisan::call('i18n:spend-audit');

        $this->assertSame(0, $exit);
    }

    public function test_report_render_failure_increments_on_builder_path(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);

        $metrics = Mockery::mock(MetricsService::class)->makePartial();
        $metrics->shouldReceive('increment')
            ->once()
            ->with('report_render_failure_count', 1, ['surface' => 'builder'])
            ->andReturnNull();
        $this->app->instance(MetricsService::class, $metrics);

        // Force a validation failure by sending a config with a table
        // not in the allowlist. Hits ReportBuilderService::requireTable.
        $this->actingAs($landlord)
            ->postJson(route('reports.builder.run'), [
                'config' => [
                    'table' => 'NOT_ALLOWED_TABLE',
                    'fields' => ['payment.amount'],
                ],
            ])
            ->assertStatus(422);
    }

    public function test_report_render_failure_increments_on_scheduled_preview_path(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        // Saved report config that will fail validation at run time.
        $report = SavedReport::create([
            'landlord_id' => $landlord->id,
            'name' => 'Broken',
            'config' => [
                'table' => 'NOT_ALLOWED_TABLE',
                'fields' => ['payment.amount'],
                'limit' => 10,
            ],
        ]);

        $metrics = Mockery::mock(MetricsService::class)->makePartial();
        $metrics->shouldReceive('increment')
            ->once()
            ->with('report_render_failure_count', 1, ['surface' => 'scheduled'])
            ->andReturnNull();
        $this->app->instance(MetricsService::class, $metrics);

        $this->actingAs($landlord)
            ->postJson(route('reports.scheduled.preview'), [
                'saved_report_id' => $report->id,
            ])
            ->assertStatus(422);
    }

    public function test_schedule_entries_exist_for_new_gauge_emitters(): void
    {
        $schedule = app(\Illuminate\Console\Scheduling\Schedule::class);
        $commands = collect($schedule->events())
            ->map(fn ($event) => $event->command)
            ->filter()
            ->map(fn ($cmd) => trim((string) $cmd))
            ->implode("\n");

        $this->assertStringContainsString('tenant-kyc:blocked-audit', $commands);
        $this->assertStringContainsString('i18n:spend-audit', $commands);
    }
}
