<?php

declare(strict_types=1);

namespace Tests\Feature\Growth;

use App\Models\User;
use App\Services\Growth\FunnelEventEmitter;
use App\Services\Growth\FunnelRollupService;
use App\Services\Growth\FunnelStage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase-56 FUNNEL-SANKEY-1/2/3 watchdog. Verifies enum emission writes
 * canonical event names and rollup produces a balanced node/link payload.
 */
class Phase56FunnelSankeyTest extends TestCase
{
    use RefreshDatabase;

    public function test_funnel_event_emitter_writes_canonical_event_name(): void
    {
        $user = User::factory()->create(['role' => 'landlord']);
        app(FunnelEventEmitter::class)->emit($user, FunnelStage::SIGNUP);

        $this->assertDatabaseHas('product_events', [
            'user_id' => $user->id,
            'event_name' => 'funnel.signup',
        ]);
    }

    public function test_rollup_balances_continuation_and_drop_off_links(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        $userA = User::factory()->create(['role' => 'landlord']);
        $userB = User::factory()->create(['role' => 'landlord']);
        $userC = User::factory()->create(['role' => 'landlord']);

        $emitter = app(FunnelEventEmitter::class);
        foreach ([$userA, $userB, $userC] as $u) {
            $emitter->emit($u, FunnelStage::SIGNUP);
        }
        $emitter->emit($userA, FunnelStage::ONBOARDING_COMPLETE);
        $emitter->emit($userB, FunnelStage::ONBOARDING_COMPLETE);
        $emitter->emit($userA, FunnelStage::FIRST_PAYMENT);

        $payload = app(FunnelRollupService::class)->computeSankeyPayload();
        $nodes = collect($payload['nodes']);
        $links = collect($payload['links']);

        $this->assertSame(3, $nodes->firstWhere('id', 'signup')['count']);
        $this->assertSame(2, $nodes->firstWhere('id', 'onboarding_complete')['count']);
        $this->assertSame(1, $nodes->firstWhere('id', 'first_payment')['count']);

        $signupContinuation = $links->first(fn ($l) => $l['source'] === 'signup' && $l['target'] === 'onboarding_complete');
        $signupDropOff = $links->first(fn ($l) => $l['source'] === 'signup' && $l['target'] === 'dropped_at_onboarding_complete');
        $this->assertSame(2, $signupContinuation['value']);
        $this->assertSame(1, $signupDropOff['value']);
        $this->assertSame($signupContinuation['value'] + $signupDropOff['value'], $nodes->firstWhere('id', 'signup')['count']);
    }

    public function test_landlord_scoped_rollup_excludes_other_landlords(): void
    {
        $landlordA = User::factory()->create(['role' => 'landlord']);
        $landlordB = User::factory()->create(['role' => 'landlord']);

        $emitter = app(FunnelEventEmitter::class);
        $emitter->emit($landlordA, FunnelStage::SIGNUP);
        $emitter->emit($landlordB, FunnelStage::SIGNUP);

        $scoped = app(FunnelRollupService::class)->computeSankeyPayload(landlordId: $landlordA->id);
        $signup = collect($scoped['nodes'])->firstWhere('id', 'signup');

        $this->assertSame(1, $signup['count'], 'Landlord-scoped rollup must exclude other landlords.');
    }
}
