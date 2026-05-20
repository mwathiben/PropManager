<?php

declare(strict_types=1);

namespace Tests\Feature\Growth;

use App\Models\Referral;
use App\Models\User;
use App\Services\Growth\ReferralLeaderboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Phase-66 REFERRAL-LEADERBOARD CI: composite scoring, DPA opt-out
 * exclusion, server-boundary anonymisation, the landlord (anonymised)
 * and ops (full) HTTP surfaces, and the opt-out toggle's cache bust.
 */
class Phase66ReferralLeaderboardTest extends TestCase
{
    use RefreshDatabase;

    private int $refSeq = 0;

    protected function setUp(): void
    {
        parent::setUp();
        // Boards are cached per (generation, limit, anon, viewer); user
        // ids repeat under RefreshDatabase, so flush to stop a prior
        // test's cached board leaking into this one.
        Cache::flush();
        config(['referral.leaderboard.reward_weight' => 2, 'referral.leaderboard.max' => 50]);
    }

    private function service(): ReferralLeaderboardService
    {
        return app(ReferralLeaderboardService::class);
    }

    private function referrer(array $attributes = []): User
    {
        return User::factory()->create(['role' => 'landlord'] + $attributes);
    }

    /**
     * Seed $attributed attributed + $rewarded rewarded referrals for a
     * referrer. Composite score becomes $attributed + $rewarded * weight.
     */
    private function seedReferrals(int $referrerId, int $attributed, int $rewarded): void
    {
        for ($i = 0; $i < $attributed; $i++) {
            $this->makeReferral($referrerId, Referral::STATUS_ATTRIBUTED);
        }
        for ($i = 0; $i < $rewarded; $i++) {
            $this->makeReferral($referrerId, Referral::STATUS_REWARDED);
        }
    }

    private function makeReferral(int $referrerId, string $status): void
    {
        $this->refSeq++;

        Referral::create([
            'referrer_user_id' => $referrerId,
            // No FK + unique constraint on referred_user_id, so distinct
            // synthetic ids keep each row valid without minting users.
            'referred_user_id' => 900000 + $this->refSeq,
            'referral_code' => str_pad((string) $this->refSeq, 8, '0', STR_PAD_LEFT),
            'status' => $status,
            'attributed_at' => now(),
            'rewarded_at' => $status === Referral::STATUS_REWARDED ? now() : null,
        ]);
    }

    public function test_composite_score_ranks_referrers_descending(): void
    {
        $a = $this->referrer();
        $b = $this->referrer();
        $c = $this->referrer();

        $this->seedReferrals($a->id, attributed: 1, rewarded: 3); // 1 + 6 = 7
        $this->seedReferrals($b->id, attributed: 5, rewarded: 0); // 5
        $this->seedReferrals($c->id, attributed: 0, rewarded: 1); // 2

        $board = $this->service()->topReferrers(limit: 10, anonymise: false);

        $this->assertSame(3, $board['total_ranked']);
        $this->assertSame(
            [$a->name, $b->name, $c->name],
            array_column($board['entries'], 'name'),
        );

        $this->assertSame(1, $board['entries'][0]['rank']);
        $this->assertSame(7, $board['entries'][0]['score']);
        $this->assertSame(5, $board['entries'][1]['score']);
        $this->assertSame(2, $board['entries'][2]['score']);
    }

    public function test_opt_out_excludes_referrer_entirely(): void
    {
        $hidden = $this->referrer(['leaderboard_opt_out' => true]);
        $shown = $this->referrer();

        $this->seedReferrals($hidden->id, attributed: 10, rewarded: 5); // would be #1
        $this->seedReferrals($shown->id, attributed: 1, rewarded: 0);

        $board = $this->service()->topReferrers(limit: 10, anonymise: false);

        $this->assertSame(1, $board['total_ranked']);
        $this->assertSame([$shown->name], array_column($board['entries'], 'name'));
    }

    public function test_anonymise_nulls_other_names_but_reveals_viewer_self(): void
    {
        $top = $this->referrer();
        $viewer = $this->referrer();

        $this->seedReferrals($top->id, attributed: 0, rewarded: 5);  // 10, rank 1
        $this->seedReferrals($viewer->id, attributed: 3, rewarded: 0); // 3, rank 2

        $board = $this->service()->topReferrers(limit: 10, anonymise: true, viewerId: $viewer->id);

        // Other referrer is masked.
        $this->assertSame(1, $board['entries'][0]['rank']);
        $this->assertFalse($board['entries'][0]['is_self']);
        $this->assertNull($board['entries'][0]['name']);

        // Viewer's own row is de-anonymised even inside an anonymised board.
        $this->assertTrue($board['entries'][1]['is_self']);
        $this->assertSame($viewer->name, $board['entries'][1]['name']);

        $this->assertNotNull($board['viewer']);
        $this->assertSame(2, $board['viewer']['rank']);
        $this->assertSame($viewer->name, $board['viewer']['name']);
    }

    public function test_viewer_row_returned_even_when_outside_top_n(): void
    {
        $first = $this->referrer();
        $second = $this->referrer();
        $viewer = $this->referrer();

        $this->seedReferrals($first->id, attributed: 0, rewarded: 5);  // 10
        $this->seedReferrals($second->id, attributed: 0, rewarded: 4); // 8
        $this->seedReferrals($viewer->id, attributed: 1, rewarded: 0); // 1, rank 3

        $board = $this->service()->topReferrers(limit: 2, anonymise: true, viewerId: $viewer->id);

        // Only the top 2 are visible...
        $this->assertCount(2, $board['entries']);
        $this->assertFalse($board['entries'][0]['is_self']);
        $this->assertFalse($board['entries'][1]['is_self']);

        // ...but the viewer still learns exactly where they stand.
        $this->assertSame(3, $board['viewer']['rank']);
        $this->assertTrue($board['viewer']['is_self']);
        $this->assertSame($viewer->name, $board['viewer']['name']);
        $this->assertSame(3, $board['total_ranked']);
    }

    public function test_landlord_route_forces_anonymised_board(): void
    {
        $viewer = $this->referrer(); // no referrals → not on the board
        $top = $this->referrer();
        $this->seedReferrals($top->id, attributed: 4, rewarded: 0);

        $this->actingAs($viewer)
            ->get(route('growth.leaderboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Growth/Leaderboard')
                ->where('opted_out', false)
                ->where('leaderboard.entries.0.is_self', false)
                ->where('leaderboard.entries.0.name', null)
            );
    }

    public function test_ops_route_allows_full_names(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);
        $top = $this->referrer();
        $this->seedReferrals($top->id, attributed: 4, rewarded: 1); // 6

        $this->actingAs($admin)
            ->get(route('ops.growth.referral-leaderboard.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Ops/Growth/ReferralLeaderboard')
                ->where('leaderboard.entries.0.name', $top->name)
            );
    }

    public function test_non_super_admin_blocked_from_ops_route(): void
    {
        $landlord = $this->referrer();

        $this->actingAs($landlord)
            ->get(route('ops.growth.referral-leaderboard.index'))
            ->assertForbidden();
    }

    public function test_opt_out_endpoint_persists_busts_cache_and_excludes(): void
    {
        $landlord = $this->referrer();
        $this->seedReferrals($landlord->id, attributed: 3, rewarded: 1); // 5

        // Board (and its cache) currently includes the landlord.
        $before = $this->service()->topReferrers(limit: 10, anonymise: false);
        $this->assertContains($landlord->name, array_column($before['entries'], 'name'));

        $this->actingAs($landlord)
            ->post(route('growth.leaderboard.opt-out'), ['opt_out' => true])
            ->assertRedirect();

        $this->assertTrue((bool) $landlord->fresh()->leaderboard_opt_out);

        // Cache was rolled, so the rebuilt board no longer lists them.
        $after = $this->service()->topReferrers(limit: 10, anonymise: false);
        $this->assertNotContains($landlord->name, array_column($after['entries'], 'name'));
        $this->assertSame(0, $after['total_ranked']);
    }

    public function test_opt_out_endpoint_validates_boolean(): void
    {
        $landlord = $this->referrer();

        $this->actingAs($landlord)
            ->postJson(route('growth.leaderboard.opt-out'), ['opt_out' => 'maybe'])
            ->assertStatus(422);
    }
}
