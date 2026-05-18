<?php

declare(strict_types=1);

namespace Tests\Feature\Plans;

use App\Console\Commands\TrialAutoExpire;
use App\Events\PlanChanged;
use App\Exceptions\BillingPortalUnavailable;
use App\Exceptions\CouponInvalidException;
use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\SubscriptionPlanChange;
use App\Services\Subscriptions\CouponService;
use App\Services\Subscriptions\PlanChangeService;
use App\Services\Subscriptions\PlanGateService;
use App\Services\Subscriptions\TrialStartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Phase-60 PLAN-MANAGEMENT CI surface watchdog. Cross-category
 * presence map for every Phase 60 closure.
 */
class Phase60PlanManagementSurfaceTest extends TestCase
{
    use RefreshDatabase;

    // -- PLAN-CHANGE -----------------------------------------------------

    public function test_plan_change_service_event_and_audit_model_exist(): void
    {
        $this->assertTrue(class_exists(PlanChangeService::class));
        $this->assertTrue(class_exists(PlanChanged::class));
        $this->assertTrue(class_exists(SubscriptionPlanChange::class));
        $this->assertTrue(Schema::hasTable('subscription_plan_changes'));
    }

    public function test_subscription_change_route_registered(): void
    {
        $route = Route::getRoutes()->getByName('subscription.change');
        $this->assertNotNull($route);
    }

    // -- FEATURE-GATES ---------------------------------------------------

    public function test_plan_gate_service_has_can_and_features_for_methods(): void
    {
        $this->assertTrue(class_exists(PlanGateService::class));
        $this->assertTrue(method_exists(PlanGateService::class, 'can'));
        $this->assertTrue(method_exists(PlanGateService::class, 'featuresFor'));
    }

    public function test_inertia_share_includes_feature_access(): void
    {
        $middleware = app(\App\Http\Middleware\HandleInertiaRequests::class);
        $request = \Illuminate\Http\Request::create('/', 'GET');
        $shared = $middleware->share($request);

        $this->assertArrayHasKey('featureAccess', $shared);
    }

    // -- TRIAL-DEPTH -----------------------------------------------------

    public function test_trial_start_service_and_auto_expire_command_exist(): void
    {
        $this->assertTrue(class_exists(TrialStartService::class));
        $this->assertTrue(class_exists(TrialAutoExpire::class));
        $this->assertTrue(method_exists(TrialStartService::class, 'startTrialFor'));
    }

    public function test_trial_auto_expire_scheduled_at_0930(): void
    {
        $events = collect(Schedule::events());
        $entry = $events->first(fn ($e) => str_contains((string) $e->command, 'trial:auto-expire'));

        $this->assertNotNull($entry);
        $this->assertSame('30 9 * * *', $entry->expression);
    }

    // -- COUPONS ---------------------------------------------------------

    public function test_coupons_schema_and_service_exist(): void
    {
        $this->assertTrue(Schema::hasTable('coupons'));
        $this->assertTrue(Schema::hasTable('coupon_redemptions'));
        $this->assertTrue(class_exists(Coupon::class));
        $this->assertTrue(class_exists(CouponRedemption::class));
        $this->assertTrue(class_exists(CouponService::class));
        $this->assertTrue(class_exists(CouponInvalidException::class));
    }

    public function test_apply_coupon_route_registered(): void
    {
        $route = Route::getRoutes()->getByName('subscription.apply-coupon');
        $this->assertNotNull($route);
    }

    // -- BILLING-PORTAL --------------------------------------------------

    public function test_billing_portal_method_and_exception_exist(): void
    {
        $this->assertTrue(method_exists(\App\Services\StripeService::class, 'createBillingPortalSession'));
        $this->assertTrue(class_exists(BillingPortalUnavailable::class));
    }

    public function test_billing_portal_route_registered(): void
    {
        $route = Route::getRoutes()->getByName('subscription.billing.portal');
        $this->assertNotNull($route);
    }

    // -- DOCS ------------------------------------------------------------

    public function test_billing_runbook_exists_with_phase_60_section(): void
    {
        $path = base_path('docs/runbooks/billing.md');
        $this->assertFileExists($path);
        $body = (string) file_get_contents($path);
        $this->assertStringContainsString('Phase 60', $body);
        $this->assertStringContainsString('PLAN-MANAGEMENT', $body);
    }

    public function test_alert_thresholds_md_lists_phase_60_gauges(): void
    {
        $body = (string) file_get_contents(base_path('docs/runbooks/alert-thresholds.md'));
        $this->assertStringContainsString('plan_feature_denied_count', $body);
        $this->assertStringContainsString('trial_expired_count', $body);
        $this->assertStringContainsString('coupon_redeemed_count', $body);
    }
}
