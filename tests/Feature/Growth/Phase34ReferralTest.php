<?php

declare(strict_types=1);

namespace Tests\Feature\Growth;

use App\Events\ReferralAttributed;
use App\Models\OnboardingMilestone;
use App\Models\Referral;
use App\Models\User;
use App\Services\Growth\ReferralAttributionService;
use App\Services\Onboarding\OnboardingMilestoneRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class Phase34ReferralTest extends TestCase
{
    use RefreshDatabase;

    public function test_landlord_gets_referral_code_on_creation(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        $landlord->refresh();

        $this->assertNotNull($landlord->referral_code);
        $this->assertSame(8, strlen($landlord->referral_code));
    }

    public function test_generate_code_for_is_idempotent(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        $landlord->refresh();
        $original = $landlord->referral_code;

        $second = app(ReferralAttributionService::class)->generateCodeFor($landlord);
        $this->assertSame($original, $second);
    }

    public function test_redeem_writes_pending_referral(): void
    {
        $referrer = User::factory()->create(['role' => 'landlord']);
        $referrer->refresh();
        $referred = User::factory()->create(['role' => 'landlord']);

        $referral = app(ReferralAttributionService::class)->redeem($referred, $referrer->referral_code);

        $this->assertNotNull($referral);
        $this->assertSame(Referral::STATUS_PENDING, $referral->status);
        $this->assertSame($referrer->id, $referral->referrer_user_id);
    }

    public function test_redeem_blocks_self_referral(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        $landlord->refresh();

        $referral = app(ReferralAttributionService::class)->redeem($landlord, $landlord->referral_code);
        $this->assertNull($referral);
    }

    public function test_redeem_blocks_duplicate_referral(): void
    {
        $referrerA = User::factory()->create(['role' => 'landlord']);
        $referrerB = User::factory()->create(['role' => 'landlord']);
        $referrerA->refresh();
        $referrerB->refresh();
        $referred = User::factory()->create(['role' => 'landlord']);

        $first = app(ReferralAttributionService::class)->redeem($referred, $referrerA->referral_code);
        $second = app(ReferralAttributionService::class)->redeem($referred, $referrerB->referral_code);

        $this->assertSame($first->id, $second->id);
        $this->assertSame($referrerA->id, $second->referrer_user_id);
        $this->assertSame(1, Referral::where('referred_user_id', $referred->id)->count());
    }

    public function test_attribute_flips_pending_to_attributed_and_fires_event(): void
    {
        Event::fake([ReferralAttributed::class]);

        $referrer = User::factory()->create(['role' => 'landlord']);
        $referrer->refresh();
        $referred = User::factory()->create(['role' => 'landlord']);

        app(ReferralAttributionService::class)->redeem($referred, $referrer->referral_code);
        $attributed = app(ReferralAttributionService::class)->attribute($referred);

        $this->assertSame(Referral::STATUS_ATTRIBUTED, $attributed->status);
        $this->assertNotNull($attributed->attributed_at);
        Event::assertDispatched(ReferralAttributed::class);
    }

    public function test_first_invoice_milestone_triggers_attribution(): void
    {
        $referrer = User::factory()->create(['role' => 'landlord']);
        $referrer->refresh();
        $referred = User::factory()->create(['role' => 'landlord']);

        app(ReferralAttributionService::class)->redeem($referred, $referrer->referral_code);

        app(OnboardingMilestoneRecorder::class)->record(
            landlordId: $referred->id,
            milestone: OnboardingMilestone::FIRST_INVOICE,
            metadata: [],
        );

        $referral = Referral::where('referred_user_id', $referred->id)->first();
        $this->assertSame(Referral::STATUS_ATTRIBUTED, $referral->status);
    }

    public function test_signed_up_milestone_does_not_trigger_attribution(): void
    {
        $referrer = User::factory()->create(['role' => 'landlord']);
        $referrer->refresh();
        $referred = User::factory()->create(['role' => 'landlord']);

        app(ReferralAttributionService::class)->redeem($referred, $referrer->referral_code);

        // signed_up auto-fires from UserObserver but we explicitly assert it stays pending.
        $referral = Referral::where('referred_user_id', $referred->id)->first();
        $this->assertSame(Referral::STATUS_PENDING, $referral->status);
    }

    public function test_redeem_endpoint_accepts_valid_code(): void
    {
        $referrer = User::factory()->create(['role' => 'landlord']);
        $referrer->refresh();
        $referred = User::factory()->create(['role' => 'landlord']);

        $this->actingAs($referred)
            ->postJson(route('referrals.redeem'), ['code' => $referrer->referral_code])
            ->assertOk()
            ->assertJsonPath('status', Referral::STATUS_PENDING);
    }

    public function test_redeem_endpoint_rejects_invalid_code(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);

        $this->actingAs($landlord)
            ->postJson(route('referrals.redeem'), ['code' => 'BOGUS999'])
            ->assertStatus(422);
    }

    public function test_mine_endpoint_returns_code_and_counts(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        $landlord->refresh();

        $response = $this->actingAs($landlord)
            ->getJson(route('referrals.mine'))
            ->assertOk()
            ->json();

        $this->assertSame($landlord->referral_code, $response['referral_code']);
        $this->assertArrayHasKey('counts', $response);
    }

    public function test_rollup_emits_per_landlord_gauge(): void
    {
        $referrer = User::factory()->create(['role' => 'landlord']);
        $referrer->refresh();
        $referred = User::factory()->create(['role' => 'landlord']);
        app(ReferralAttributionService::class)->redeem($referred, $referrer->referral_code);
        app(ReferralAttributionService::class)->attribute($referred);

        $exit = \Artisan::call('referrals:rollup');
        $output = \Artisan::output();
        $this->assertSame(0, $exit);
        $this->assertStringContainsString('1 landlord', $output);
    }
}
