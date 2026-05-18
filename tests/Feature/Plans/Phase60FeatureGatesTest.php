<?php

declare(strict_types=1);

namespace Tests\Feature\Plans;

use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\MetricsService;
use App\Services\Subscriptions\PlanGateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Phase-60 FEATURE-GATES-1/2/3: PlanGateService wraps the existing
 * User::canAccessFeature() with Cache::remember 5m + denial counter.
 */
class Phase60FeatureGatesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_can_returns_true_when_plan_has_feature(): void
    {
        $user = User::factory()->create(['role' => 'landlord']);
        $plan = SubscriptionPlan::factory()->create([
            'reports_enabled' => true,
        ]);
        Subscription::factory()->create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'status' => SubscriptionStatus::Active,
        ]);

        $this->assertTrue(app(PlanGateService::class)->can('reports', $user));
    }

    public function test_can_returns_false_when_plan_lacks_feature(): void
    {
        $user = User::factory()->create(['role' => 'landlord']);
        $plan = SubscriptionPlan::factory()->create([
            'ocr_enabled' => false,
        ]);
        Subscription::factory()->create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'status' => SubscriptionStatus::Active,
        ]);

        $this->assertFalse(app(PlanGateService::class)->can('ocr', $user));
    }

    public function test_can_returns_false_for_null_user(): void
    {
        $this->assertFalse(app(PlanGateService::class)->can('reports', null));
    }

    public function test_denial_increments_plan_feature_denied_count_gauge(): void
    {
        $user = User::factory()->create(['role' => 'landlord']);
        $plan = SubscriptionPlan::factory()->create(['sms_notifications_enabled' => false]);
        Subscription::factory()->create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'status' => SubscriptionStatus::Active,
        ]);

        $metrics = $this->mock(MetricsService::class);
        $metrics->shouldReceive('increment')
            ->with('plan_feature_denied_count', 1, ['feature' => 'sms'])
            ->once();

        app()->instance(MetricsService::class, $metrics);
        $service = new PlanGateService($metrics);
        $service->can('sms', $user);
    }

    public function test_features_for_returns_all_six_flags(): void
    {
        $user = User::factory()->create(['role' => 'landlord']);
        $plan = SubscriptionPlan::factory()->create([
            'water_billing_enabled' => true,
            'ocr_enabled' => false,
            'reports_enabled' => true,
            'bulk_operations_enabled' => false,
            'document_storage_enabled' => true,
            'sms_notifications_enabled' => false,
        ]);
        Subscription::factory()->create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'status' => SubscriptionStatus::Active,
        ]);

        $features = app(PlanGateService::class)->featuresFor($user);

        $this->assertSame(
            ['water_billing', 'ocr', 'reports', 'bulk_operations', 'documents', 'sms'],
            array_keys($features),
        );
        $this->assertTrue($features['water_billing']);
        $this->assertFalse($features['ocr']);
        $this->assertTrue($features['reports']);
    }

    public function test_inertia_share_emits_plan_features_for_landlord(): void
    {
        $user = User::factory()->create(['role' => 'landlord']);
        $plan = SubscriptionPlan::factory()->create([
            'water_billing_enabled' => true,
            'reports_enabled' => true,
        ]);
        Subscription::factory()->create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'status' => SubscriptionStatus::Active,
        ]);

        // Call the share() method directly rather than hitting a
        // controller — avoids dashboard's per-tenant context setup
        // and keeps the assertion focused on the share contract.
        $middleware = app(\App\Http\Middleware\HandleInertiaRequests::class);
        $request = \Illuminate\Http\Request::create('/', 'GET');
        $request->setUserResolver(fn () => $user);

        $shared = $middleware->share($request);

        $this->assertArrayHasKey('featureAccess', $shared);
        $this->assertSame(true, $shared['featureAccess']['water_billing']);
        $this->assertSame(true, $shared['featureAccess']['reports']);
    }
}
