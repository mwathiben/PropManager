<?php

declare(strict_types=1);

namespace Tests\Feature\Cost;

use App\Models\LandlordUsageMetric;
use App\Models\User;
use App\Services\Cost\LandlordUsageMetricRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase33CostAttributionTest extends TestCase
{
    use RefreshDatabase;

    public function test_recorder_inserts_new_row_for_first_metric(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        app(LandlordUsageMetricRecorder::class)->add($landlord->id, LandlordUsageMetric::METRIC_DB_QUERIES, 1000);

        $this->assertDatabaseHas('landlord_usage_metrics', [
            'landlord_id' => $landlord->id,
            'metric' => LandlordUsageMetric::METRIC_DB_QUERIES,
            'value' => 1000,
        ]);
    }

    public function test_recorder_atomically_increments_existing_row(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        $recorder = app(LandlordUsageMetricRecorder::class);

        $recorder->add($landlord->id, LandlordUsageMetric::METRIC_SMS_SENDS, 5);
        $recorder->add($landlord->id, LandlordUsageMetric::METRIC_SMS_SENDS, 7);
        $recorder->add($landlord->id, LandlordUsageMetric::METRIC_SMS_SENDS, 3);

        $row = LandlordUsageMetric::query()
            ->withoutGlobalScopes()
            ->where('landlord_id', $landlord->id)
            ->where('metric', LandlordUsageMetric::METRIC_SMS_SENDS)
            ->first();
        $this->assertSame(15, $row->value);
        $this->assertSame(1, LandlordUsageMetric::query()
            ->withoutGlobalScopes()
            ->where('landlord_id', $landlord->id)
            ->count());
    }

    public function test_recorder_rejects_unknown_metric(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        $this->expectException(\InvalidArgumentException::class);
        app(LandlordUsageMetricRecorder::class)->add($landlord->id, 'frodo_metric', 1);
    }

    public function test_recorder_silently_skips_zero_or_negative_delta(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        $recorder = app(LandlordUsageMetricRecorder::class);

        $recorder->add($landlord->id, LandlordUsageMetric::METRIC_DB_QUERIES, 0);
        $recorder->add($landlord->id, LandlordUsageMetric::METRIC_DB_QUERIES, -10);

        $this->assertSame(0, LandlordUsageMetric::query()
            ->withoutGlobalScopes()
            ->where('landlord_id', $landlord->id)
            ->count());
    }

    public function test_recorder_buckets_by_day(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        $recorder = app(LandlordUsageMetricRecorder::class);

        $recorder->add($landlord->id, LandlordUsageMetric::METRIC_DB_QUERIES, 100, new \DateTimeImmutable('2026-05-01'));
        $recorder->add($landlord->id, LandlordUsageMetric::METRIC_DB_QUERIES, 200, new \DateTimeImmutable('2026-05-02'));

        $this->assertSame(2, LandlordUsageMetric::query()
            ->withoutGlobalScopes()
            ->where('landlord_id', $landlord->id)
            ->count());
    }

    public function test_cost_attribute_emits_gauge_when_usage_exists(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        $recorder = app(LandlordUsageMetricRecorder::class);

        $recorder->add($landlord->id, LandlordUsageMetric::METRIC_SMS_SENDS, 100);
        $recorder->add($landlord->id, LandlordUsageMetric::METRIC_DB_QUERIES, 1_000_000);

        $exit = \Artisan::call('cost:attribute');
        $output = \Artisan::output();

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('Attributed cost for', $output);
    }

    public function test_landlord_cost_top_n_returns_top_landlords_for_super_admin(): void
    {
        $admin = User::factory()->create();
        $admin->role = 'super_admin';
        $admin->save();

        $a = User::factory()->create(['role' => 'landlord']);
        $b = User::factory()->create(['role' => 'landlord']);

        $recorder = app(LandlordUsageMetricRecorder::class);
        $recorder->add($a->id, LandlordUsageMetric::METRIC_SMS_SENDS, 1000);
        $recorder->add($b->id, LandlordUsageMetric::METRIC_SMS_SENDS, 50);

        $response = $this->actingAs($admin)
            ->getJson(route('ops.landlord-cost.top-n', ['days' => 30, 'limit' => 5]))
            ->assertOk()
            ->json();

        $this->assertSame(30, $response['window_days']);
        $this->assertNotEmpty($response['landlords']);
        $this->assertSame($a->id, $response['landlords'][0]['landlord_id']);
        $this->assertGreaterThan($response['landlords'][1]['cost_kes'], $response['landlords'][0]['cost_kes']);
    }

    public function test_landlord_cost_top_n_is_super_admin_only(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        $this->actingAs($landlord)
            ->getJson(route('ops.landlord-cost.top-n'))
            ->assertForbidden();
    }
}
