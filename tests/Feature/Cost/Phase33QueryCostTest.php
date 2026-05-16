<?php

declare(strict_types=1);

namespace Tests\Feature\Cost;

use App\Models\AlertFiring;
use App\Models\QueryCostLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase33QueryCostTest extends TestCase
{
    use RefreshDatabase;

    public function test_query_cost_audit_emits_per_class_gauges(): void
    {
        QueryCostLog::create([
            'route_class' => 'read_path',
            'query_count' => 12,
            'rows_scanned' => 12_000,
            'rows_returned' => 100,
            'request_at' => now(),
        ]);
        QueryCostLog::create([
            'route_class' => 'read_path',
            'query_count' => 20,
            'rows_scanned' => 50_000,
            'rows_returned' => 50,
            'request_at' => now(),
        ]);

        $exit = \Artisan::call('query:cost-audit');
        $output = \Artisan::output();

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('read_path', $output);
        $this->assertStringContainsString('p90=', $output);
    }

    public function test_query_cost_audit_fires_alert_when_p90_above_threshold(): void
    {
        for ($i = 0; $i < 5; $i++) {
            QueryCostLog::create([
                'route_class' => 'report',
                'query_count' => 50,
                'rows_scanned' => 5_000_000,
                'rows_returned' => 1,
                'request_at' => now()->subMinutes($i),
            ]);
        }

        \Artisan::call('query:cost-audit --threshold=1000');

        $this->assertDatabaseHas('alert_firings', [
            'alert_key' => 'high_query_scan_ratio',
            'severity' => 'sev3',
        ]);
    }

    public function test_query_cost_audit_resolves_alert_when_below_threshold(): void
    {
        AlertFiring::create([
            'alert_key' => 'high_query_scan_ratio',
            'severity' => 'sev3',
            'value' => 5_000_000,
            'threshold' => 1000,
            'fired_at' => now()->subHour(),
        ]);

        QueryCostLog::create([
            'route_class' => 'read_path',
            'query_count' => 12,
            'rows_scanned' => 1200,
            'rows_returned' => 100,
            'request_at' => now(),
        ]);

        \Artisan::call('query:cost-audit --threshold=1000');

        $firing = AlertFiring::query()->where('alert_key', 'high_query_scan_ratio')->latest('id')->first();
        $this->assertNotNull($firing->resolved_at);
    }

    public function test_query_cost_audit_handles_empty_log_table(): void
    {
        $exit = \Artisan::call('query:cost-audit');
        $this->assertSame(0, $exit);
    }

    public function test_track_query_cost_middleware_writes_sample_above_threshold(): void
    {
        $middleware = new \App\Http\Middleware\TrackQueryCost;
        $request = \Illuminate\Http\Request::create('/test', 'GET');

        $response = $middleware->handle($request, function () {
            // Simulate 15 SELECTs.
            for ($i = 0; $i < 15; $i++) {
                \DB::table('users')->limit(1)->get();
            }

            return new \Illuminate\Http\Response('ok');
        });

        $this->assertSame(200, $response->getStatusCode());
        $this->assertGreaterThanOrEqual(1, QueryCostLog::query()->count());
    }

    public function test_track_query_cost_middleware_skips_sample_below_threshold(): void
    {
        $middleware = new \App\Http\Middleware\TrackQueryCost;
        $request = \Illuminate\Http\Request::create('/test', 'GET');

        $middleware->handle($request, function () {
            \DB::table('users')->limit(1)->get();

            return new \Illuminate\Http\Response('ok');
        });

        $this->assertSame(0, QueryCostLog::query()->count());
    }
}
