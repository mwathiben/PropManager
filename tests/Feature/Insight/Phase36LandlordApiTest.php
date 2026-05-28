<?php

declare(strict_types=1);

namespace Tests\Feature\Insight;

use App\Models\LandlordEngagementScore;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class Phase36LandlordApiTest extends TestCase
{
    use RefreshDatabase;

    private function authLandlord(): User
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        Sanctum::actingAs($landlord, ['landlord:manage']);

        return $landlord;
    }

    public function test_engagement_endpoint_returns_landlord_scores(): void
    {
        $landlord = $this->authLandlord();
        LandlordEngagementScore::query()->withoutGlobalScopes()->create([
            'landlord_id' => $landlord->id,
            'day' => now()->toDateString(),
            'score' => 75,
            'components' => ['login' => 100],
        ]);

        $response = $this->getJson(route('api.v1.landlord.engagement.index'))
            ->assertOk()
            ->json();

        $this->assertSame(30, $response['window_days']);
        $this->assertCount(1, $response['scores']);
        $this->assertSame(75, $response['scores'][0]['score']);
    }

    public function test_engagement_endpoint_respects_days_param(): void
    {
        $this->authLandlord();
        $response = $this->getJson(route('api.v1.landlord.engagement.index', ['days' => 60]))
            ->assertOk()
            ->json();
        $this->assertSame(60, $response['window_days']);
    }

    public function test_engagement_export_returns_csv(): void
    {
        $landlord = $this->authLandlord();
        LandlordEngagementScore::query()->withoutGlobalScopes()->create([
            'landlord_id' => $landlord->id,
            'day' => now()->toDateString(),
            'score' => 80,
            'components' => ['login' => 100, 'milestones' => 67],
        ]);

        $response = $this->get(route('api.v1.landlord.engagement.export'));
        $response->assertOk();
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('Content-Type'));
        $body = $response->streamedContent();
        $this->assertStringContainsString('day,score,login,milestones', $body);
        $this->assertStringContainsString('80', $body);
    }

    public function test_usage_endpoint_returns_features_with_ratios(): void
    {
        $plan = SubscriptionPlan::factory()->starter()->create();
        $landlord = $this->authLandlord();
        Subscription::factory()->active()->forUser($landlord)->forPlan($plan)->create();

        $response = $this->getJson(route('api.v1.landlord.usage.index'))
            ->assertOk()
            ->json();

        $this->assertArrayHasKey('period_start', $response);
        $this->assertArrayHasKey('features', $response);
        $featureNames = array_column($response['features'], 'feature');
        $this->assertContains('properties', $featureNames);
        $this->assertContains('units', $featureNames);
    }

    public function test_referrals_endpoint_returns_code_and_list(): void
    {
        $landlord = $this->authLandlord();
        $landlord->refresh();

        $response = $this->getJson(route('api.v1.landlord.referrals.index'))
            ->assertOk()
            ->json();

        $this->assertArrayHasKey('referral_code', $response);
        $this->assertArrayHasKey('referrals', $response);
        $this->assertArrayHasKey('counts', $response);
    }

    public function test_insights_summary_returns_aggregate_shape(): void
    {
        $plan = SubscriptionPlan::factory()->starter()->create();
        $landlord = $this->authLandlord();
        Subscription::factory()->active()->forUser($landlord)->forPlan($plan)->monthly()->create();

        $response = $this->getJson(route('api.v1.landlord.insights.summary'))
            ->assertOk()
            ->json();

        $this->assertArrayHasKey('engagement', $response);
        $this->assertArrayHasKey('usage', $response);
        $this->assertArrayHasKey('referrals', $response);
        $this->assertArrayHasKey('mrr_contribution', $response);
        $this->assertEqualsWithDelta(1500.0, $response['mrr_contribution']['current_period_kes'], 0.01);
    }

    public function test_endpoints_reject_unauthenticated(): void
    {
        $this->getJson(route('api.v1.landlord.engagement.index'))
            ->assertUnauthorized();
        $this->getJson(route('api.v1.landlord.usage.index'))
            ->assertUnauthorized();
    }

    public function test_endpoints_reject_wrong_ability(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        Sanctum::actingAs($landlord, ['tenant:read']);
        $this->getJson(route('api.v1.landlord.engagement.index'))
            ->assertForbidden();
    }
}
