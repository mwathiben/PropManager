<?php

declare(strict_types=1);

namespace Tests\Feature\Insight;

use App\Models\LandlordEngagementScore;
use App\Models\Referral;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\Insight\InsightDashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase36LandlordGrowthTest extends TestCase
{
    use RefreshDatabase;

    public function test_landlord_summary_returns_zero_engagement_for_new_landlord(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        $summary = app(InsightDashboardService::class)->landlordSummary($landlord->id);
        $this->assertSame(0, $summary['engagement_score']);
        $this->assertSame(0, $summary['referral_count_30d']);
    }

    public function test_landlord_summary_picks_latest_engagement_score(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        LandlordEngagementScore::query()->withoutGlobalScopes()->create([
            'landlord_id' => $landlord->id,
            'day' => now()->subDays(8)->toDateString(),
            'score' => 50,
            'components' => [],
        ]);
        LandlordEngagementScore::query()->withoutGlobalScopes()->create([
            'landlord_id' => $landlord->id,
            'day' => now()->toDateString(),
            'score' => 80,
            'components' => [],
        ]);

        $summary = app(InsightDashboardService::class)->landlordSummary($landlord->id);
        $this->assertSame(80, $summary['engagement_score']);
        $this->assertSame(30, $summary['engagement_score_delta_7d']);
    }

    public function test_landlord_summary_counts_30d_referrals(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        $referredA = User::factory()->create(['role' => 'landlord']);
        $referredB = User::factory()->create(['role' => 'landlord']);

        Referral::create([
            'referrer_user_id' => $landlord->id,
            'referred_user_id' => $referredA->id,
            'referral_code' => 'ABC12345',
            'status' => 'attributed',
            'attributed_at' => now()->subDays(5),
        ]);
        Referral::create([
            'referrer_user_id' => $landlord->id,
            'referred_user_id' => $referredB->id,
            'referral_code' => 'XYZ98765',
            'status' => 'attributed',
            'attributed_at' => now()->subDays(45), // out of window
        ]);

        $summary = app(InsightDashboardService::class)->landlordSummary($landlord->id);
        $this->assertSame(1, $summary['referral_count_30d']);
    }

    public function test_landlord_summary_includes_usage_ratios_when_subscribed(): void
    {
        $plan = SubscriptionPlan::factory()->starter()->create();
        $landlord = User::factory()->create(['role' => 'landlord']);
        Subscription::factory()->active()->forUser($landlord)->forPlan($plan)->create();

        $summary = app(InsightDashboardService::class)->landlordSummary($landlord->id);
        $this->assertNotEmpty($summary['usage_ratios']);
        $this->assertSame('properties', $summary['usage_ratios'][0]['feature']);
    }

    public function test_growth_page_renders_for_landlord(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        $response = $this->actingAs($landlord)->get(route('landlord.growth'));
        $response->assertOk();
        $page = $response->viewData('page');
        $this->assertSame('Insight/LandlordGrowth', $page['component']);
        $this->assertArrayHasKey('engagement_history', $page['props']);
        $this->assertArrayHasKey('referrals', $page['props']);
    }

    public function test_growth_page_blocks_non_landlord(): void
    {
        $tenant = User::factory()->create(['role' => 'tenant']);
        $this->actingAs($tenant)
            ->get(route('landlord.growth'))
            ->assertForbidden();
    }
}
