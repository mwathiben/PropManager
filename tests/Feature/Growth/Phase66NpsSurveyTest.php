<?php

declare(strict_types=1);

namespace Tests\Feature\Growth;

use App\Models\NpsPromptState;
use App\Models\NpsResponse;
use App\Models\User;
use App\Services\Growth\NpsEligibilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Phase-66 NPS-SURVEY CI-2: behaviour of the NPS surface — category
 * derivation, server-authoritative cadence gating, kill-switch, and
 * double-submit protection.
 */
class Phase66NpsSurveyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // The prompt payload is cached per-user; flush so a prior test's
        // cached decision (user ids repeat under RefreshDatabase) can't
        // leak into this one.
        Cache::flush();
    }

    private function eligibility(): NpsEligibilityService
    {
        return app(NpsEligibilityService::class);
    }

    private function eligibleLandlord(): User
    {
        return User::factory()->create([
            'role' => 'landlord',
            'created_at' => now()->subDays(30),
        ]);
    }

    public function test_categorise_maps_scores_to_nps_buckets(): void
    {
        $this->assertSame(NpsResponse::CATEGORY_DETRACTOR, NpsResponse::categorise(0));
        $this->assertSame(NpsResponse::CATEGORY_DETRACTOR, NpsResponse::categorise(6));
        $this->assertSame(NpsResponse::CATEGORY_PASSIVE, NpsResponse::categorise(7));
        $this->assertSame(NpsResponse::CATEGORY_PASSIVE, NpsResponse::categorise(8));
        $this->assertSame(NpsResponse::CATEGORY_PROMOTER, NpsResponse::categorise(9));
        $this->assertSame(NpsResponse::CATEGORY_PROMOTER, NpsResponse::categorise(10));
    }

    public function test_kill_switch_disables_all_prompts(): void
    {
        config(['nps.enabled' => false]);
        $landlord = $this->eligibleLandlord();

        $this->assertFalse($this->eligibility()->shouldPrompt($landlord));
        $this->assertNull($this->eligibility()->promptPayloadFor($landlord));
    }

    public function test_new_accounts_are_not_eligible(): void
    {
        $fresh = User::factory()->create(['role' => 'landlord', 'created_at' => now()->subDays(3)]);

        $this->assertFalse($this->eligibility()->shouldPrompt($fresh));
    }

    public function test_super_admin_is_never_prompted(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'created_at' => now()->subYear()]);

        $this->assertFalse($this->eligibility()->shouldPrompt($admin));
    }

    public function test_eligible_landlord_receives_prompt_payload(): void
    {
        $landlord = $this->eligibleLandlord();

        $this->assertTrue($this->eligibility()->shouldPrompt($landlord));
        $this->assertIsArray($this->eligibility()->promptPayloadFor($landlord));
    }

    public function test_store_persists_response_and_blocks_repeat_within_cadence(): void
    {
        $landlord = $this->eligibleLandlord();

        $this->actingAs($landlord)
            ->post(route('nps.store'), ['score' => 9, 'comment' => 'Love it', 'context' => 'dashboard'])
            ->assertRedirect();

        $response = NpsResponse::first();
        $this->assertNotNull($response);
        $this->assertSame(9, $response->score);
        $this->assertSame(NpsResponse::CATEGORY_PROMOTER, $response->category);
        $this->assertSame($landlord->id, $response->landlord_id);
        $this->assertSame($landlord->id, $response->user_id);

        // Cadence now blocks a re-prompt.
        $this->assertFalse($this->eligibility()->shouldPrompt($landlord->fresh()));

        // Server-side double-submit guard returns a validation error and
        // does NOT create a second row.
        $this->actingAs($landlord)
            ->postJson(route('nps.store'), ['score' => 2])
            ->assertStatus(422);

        $this->assertSame(1, NpsResponse::count());
    }

    public function test_score_out_of_range_is_rejected(): void
    {
        $landlord = $this->eligibleLandlord();

        $this->actingAs($landlord)
            ->postJson(route('nps.store'), ['score' => 11])
            ->assertStatus(422);

        $this->assertSame(0, NpsResponse::count());
    }

    public function test_dismiss_increments_and_snoozes(): void
    {
        $landlord = $this->eligibleLandlord();

        $this->actingAs($landlord)->post(route('nps.dismiss'))->assertRedirect();

        $state = NpsPromptState::where('user_id', $landlord->id)->first();
        $this->assertSame(1, $state->dismiss_count);
        $this->assertTrue($state->snoozed_until->isFuture());
        $this->assertFalse($this->eligibility()->shouldPrompt($landlord->fresh()));
    }

    public function test_max_dismissals_stops_prompting(): void
    {
        $landlord = $this->eligibleLandlord();
        NpsPromptState::create([
            'user_id' => $landlord->id,
            'dismiss_count' => (int) config('nps.max_dismissals', 3),
        ]);

        $this->assertFalse($this->eligibility()->shouldPrompt($landlord));
    }

    public function test_opt_out_is_terminal(): void
    {
        $landlord = $this->eligibleLandlord();

        $this->actingAs($landlord)->post(route('nps.opt-out'))->assertRedirect();

        $state = NpsPromptState::where('user_id', $landlord->id)->first();
        $this->assertNotNull($state->opted_out_at);
        $this->assertFalse($this->eligibility()->shouldPrompt($landlord->fresh()));
    }

    public function test_nps_endpoints_require_authentication(): void
    {
        $this->post(route('nps.impression'))->assertRedirect();
        $this->post(route('nps.store'), ['score' => 9])->assertRedirect();

        $this->assertSame(0, NpsPromptState::count());
        $this->assertSame(0, NpsResponse::count());
    }

    public function test_impression_records_prompt_server_side(): void
    {
        $landlord = $this->eligibleLandlord();

        $this->actingAs($landlord)->post(route('nps.impression'))->assertRedirect();

        $state = NpsPromptState::where('user_id', $landlord->id)->first();
        $this->assertNotNull($state->last_prompted_at);

        // Reprompt cooldown now suppresses an immediate re-show.
        $this->assertFalse($this->eligibility()->shouldPrompt($landlord->fresh()));
    }
}
