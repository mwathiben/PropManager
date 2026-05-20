<?php

declare(strict_types=1);

namespace Tests\Feature\Growth;

use App\Models\AlertFiring;
use App\Models\NpsPromptState;
use App\Models\NpsResponse;
use App\Models\User;
use App\Models\UserTourState;
use App\Services\Growth\NpsScoreService;
use App\Services\MetricsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Phase-66 GROWTH-OBSERVABILITY CI: NPS math, the nps:rollup gauges +
 * nps_negative alert, the growth:leaderboard-rollup gauges, and the
 * real-time onboarding-tour counter.
 */
class Phase66GrowthObservabilityTest extends TestCase
{
    use RefreshDatabase;

    private function landlord(): User
    {
        return User::factory()->create(['role' => 'landlord']);
    }

    /** Create an NPS response owned by $landlord (TenantScope stamps landlord_id). */
    private function respond(User $landlord, int $score, ?Carbon $when = null): void
    {
        $this->actingAs($landlord);
        NpsResponse::create([
            'user_id' => $landlord->id,
            'score' => $score,
            'category' => NpsResponse::categorise($score),
            'responded_at' => $when ?? now(),
        ]);
    }

    public function test_score_and_breakdown_math(): void
    {
        $landlord = $this->landlord();
        // 3 promoters (9), 1 passive (7), 1 detractor (3): score = (3-1)/5*100 = 40.
        $this->respond($landlord, 9);
        $this->respond($landlord, 10);
        $this->respond($landlord, 9);
        $this->respond($landlord, 7);
        $this->respond($landlord, 3);

        $result = app(NpsScoreService::class)->compute($landlord->id, 90);

        $this->assertSame(40, $result['score']);
        $this->assertSame(5, $result['response_count']);
        $this->assertSame(3, $result['breakdown']['promoter']);
        $this->assertSame(1, $result['breakdown']['passive']);
        $this->assertSame(1, $result['breakdown']['detractor']);
    }

    public function test_response_rate_is_responses_over_prompts(): void
    {
        $landlord = $this->landlord();
        // 4 users prompted in window, 1 responded → rate 0.25 platform-wide.
        foreach (range(1, 4) as $i) {
            NpsPromptState::create(['user_id' => $this->landlord()->id, 'last_prompted_at' => now()]);
        }
        $this->respond($landlord, 9);

        $result = app(NpsScoreService::class)->compute(null, 90);

        $this->assertSame(1, $result['response_count']);
        $this->assertEqualsWithDelta(0.25, $result['response_rate'], 0.0001); // 1 response / 4 prompts
    }

    public function test_nps_rollup_fires_negative_alert(): void
    {
        $landlord = $this->landlord();
        // 7 detractors + 3 promoters = score -40 over 10 responses.
        foreach (range(1, 7) as $i) {
            $this->respond($landlord, 2);
        }
        foreach (range(1, 3) as $i) {
            $this->respond($landlord, 10);
        }

        $this->artisan('nps:rollup')->assertSuccessful();

        $this->assertDatabaseHas('alert_firings', [
            'alert_key' => 'nps_negative',
            'severity' => 'sev4',
            'resolved_at' => null,
        ]);
    }

    public function test_nps_rollup_resolves_alert_when_not_negative(): void
    {
        AlertFiring::create([
            'alert_key' => 'nps_negative',
            'severity' => 'sev4',
            'value' => -40,
            'threshold' => 0,
            'fired_at' => now()->subHour(),
        ]);

        $this->artisan('nps:rollup')->assertSuccessful();

        $firing = AlertFiring::where('alert_key', 'nps_negative')->latest('id')->first();
        $this->assertNotNull($firing->resolved_at);
    }

    public function test_nps_rollup_emits_platform_gauge(): void
    {
        $spy = $this->spy(MetricsService::class);
        $this->respond($this->landlord(), 9);

        $this->artisan('nps:rollup')->assertSuccessful();

        $spy->shouldHaveReceived('gauge')->withArgs(
            fn (string $name, float $value, array $labels = []) => $name === 'nps_score' && ($labels['scope'] ?? null) === 'platform',
        );
    }

    public function test_leaderboard_rollup_emits_participation_and_tour_gauges(): void
    {
        $spy = $this->spy(MetricsService::class);

        $this->landlord();
        UserTourState::factory()->create(['status' => UserTourState::STATUS_ACTIVE]);
        UserTourState::factory()->completed()->create();

        $this->artisan('growth:leaderboard-rollup')->assertSuccessful();

        $spy->shouldHaveReceived('gauge')->withArgs(
            fn (string $name) => $name === 'referral_leaderboard_participants',
        );
        $spy->shouldHaveReceived('gauge')->withArgs(
            fn (string $name) => $name === 'onboarding_tour_completed_count',
        );
    }

    public function test_tour_complete_increments_counter_once_even_on_replay(): void
    {
        $spy = $this->spy(MetricsService::class);
        $landlord = $this->landlord();

        $this->actingAs($landlord)->post(route('onboarding-tour.complete'))->assertRedirect();
        // Replay on the now-terminal tour must not double-count.
        $this->actingAs($landlord)->post(route('onboarding-tour.complete'))->assertRedirect();

        $spy->shouldHaveReceived('increment')->with('onboarding_tour_completed_total')->once();
    }
}
