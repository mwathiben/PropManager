<?php

declare(strict_types=1);

namespace Tests\Feature\Insight;

use App\Models\AlertFiring;
use App\Models\MrrSnapshot;
use App\Models\OperationalIncident;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\Insight\InsightDashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase36OpsDashboardTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        $user = User::factory()->create();
        $user->role = 'super_admin';
        $user->save();

        return $user;
    }

    public function test_landlord_cost_endpoint_returns_inertia_for_html_request(): void
    {
        $admin = $this->superAdmin();
        $response = $this->actingAs($admin)->get(route('ops.landlord-cost.top-n'));
        $response->assertOk();
        $page = $response->viewData('page');
        $this->assertSame('Ops/LandlordCost', $page['component']);
        $this->assertArrayHasKey('landlords', $page['props']);
    }

    public function test_landlord_cost_endpoint_returns_json_for_api_request(): void
    {
        $admin = $this->superAdmin();
        $response = $this->actingAs($admin)
            ->getJson(route('ops.landlord-cost.top-n'))
            ->assertOk()
            ->json();
        $this->assertArrayHasKey('landlords', $response);
        $this->assertArrayHasKey('window_days', $response);
    }

    public function test_mrr_trend_endpoint_returns_inertia_for_html_request(): void
    {
        $admin = $this->superAdmin();
        $response = $this->actingAs($admin)->get(route('ops.mrr.trend'));
        $response->assertOk();
        $page = $response->viewData('page');
        $this->assertSame('Ops/MrrTrend', $page['component']);
    }

    public function test_ops_dashboard_renders_for_super_admin(): void
    {
        $admin = $this->superAdmin();
        $response = $this->actingAs($admin)->get(route('ops.index'));
        $response->assertOk();
        $page = $response->viewData('page');
        $this->assertSame('Ops/Index', $page['component']);
        $this->assertArrayHasKey('summary', $page['props']);
        $this->assertArrayHasKey('mrr_total_kes_today', $page['props']['summary']);
    }

    public function test_ops_dashboard_blocks_non_super_admin(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        $this->actingAs($landlord)
            ->get(route('ops.index'))
            ->assertForbidden();
    }

    public function test_operator_summary_computes_kpis(): void
    {
        $plan = SubscriptionPlan::factory()->starter()->create();
        MrrSnapshot::create([
            'day' => now()->toDateString(),
            'plan_id' => $plan->id,
            'mrr_kes' => 50000,
            'active_subscriptions' => 33,
        ]);
        OperationalIncident::create([
            'opened_by_user_id' => User::factory()->create()->id,
            'severity' => 'sev3',
            'title' => 'Test incident',
            'status' => 'open',
            'opened_at' => now(),
        ]);
        AlertFiring::create([
            'alert_key' => 'failed_jobs_growth',
            'severity' => 'sev3',
            'value' => 100,
            'threshold' => 25,
            'fired_at' => now()->subHours(2),
        ]);

        $summary = app(InsightDashboardService::class)->operatorSummary();
        $this->assertEqualsWithDelta(50000.0, $summary['mrr_total_kes_today'], 0.01);
        $this->assertSame(1, $summary['active_incident_count']);
        $this->assertSame(1, $summary['last_24h_alert_count']);
        $this->assertSame(1, $summary['unresolved_alert_count']);
    }

    public function test_inertia_share_includes_ops_nav_for_super_admin(): void
    {
        $admin = $this->superAdmin();
        $middleware = app(\App\Http\Middleware\HandleInertiaRequests::class);
        $request = \Illuminate\Http\Request::create('/');
        $request->setUserResolver(fn () => $admin);

        $shared = $middleware->share($request);
        $resolved = is_callable($shared['opsNav']) ? ($shared['opsNav'])() : $shared['opsNav'];
        $this->assertIsArray($resolved);
        $this->assertGreaterThanOrEqual(4, count($resolved));
        $this->assertSame('ops.index', $resolved[0]['route']);
    }

    public function test_inertia_share_returns_null_ops_nav_for_landlord(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        $middleware = app(\App\Http\Middleware\HandleInertiaRequests::class);
        $request = \Illuminate\Http\Request::create('/');
        $request->setUserResolver(fn () => $landlord);

        $shared = $middleware->share($request);
        $resolved = is_callable($shared['opsNav']) ? ($shared['opsNav'])() : $shared['opsNav'];
        $this->assertNull($resolved);
    }
}
