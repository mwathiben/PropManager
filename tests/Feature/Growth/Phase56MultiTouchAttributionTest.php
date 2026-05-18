<?php

declare(strict_types=1);

namespace Tests\Feature\Growth;

use App\Models\AttributionTouchpoint;
use App\Models\User;
use App\Services\Growth\AttributionModelService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase-56 MULTI-TOUCH-3 watchdog. Exercises every attribution model
 * against four canonical fixture scenarios so the credit-allocation
 * algebra is pinned for future refactors.
 */
class Phase56MultiTouchAttributionTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_touchpoints_returns_empty_array(): void
    {
        $user = User::factory()->create();

        $result = app(AttributionModelService::class)->computeForUser($user->id);

        $this->assertSame([], $result);
    }

    public function test_single_touch_all_models_assign_100_percent(): void
    {
        $user = User::factory()->create();
        $this->touch($user, 'referral', now()->subDays(3));

        $result = app(AttributionModelService::class)->computeForUser($user->id);

        foreach (AttributionModelService::ALL_MODELS as $model) {
            $this->assertEqualsWithDelta(100.0, $result[$model]['referral'] ?? 0, 0.5, "Model {$model} should award 100% to the single touch.");
        }
    }

    public function test_two_touch_first_last_linear_u_shape_allocations(): void
    {
        $user = User::factory()->create();
        $this->touch($user, 'social', now()->subDays(5));
        $this->touch($user, 'email', now()->subDays(2));

        $result = app(AttributionModelService::class)->computeForUser($user->id);

        $this->assertEqualsWithDelta(100.0, $result[AttributionModelService::MODEL_FIRST_TOUCH]['social'], 0.5);
        $this->assertEqualsWithDelta(100.0, $result[AttributionModelService::MODEL_LAST_TOUCH]['email'], 0.5);
        $this->assertEqualsWithDelta(50.0, $result[AttributionModelService::MODEL_LINEAR]['social'], 0.5);
        $this->assertEqualsWithDelta(50.0, $result[AttributionModelService::MODEL_LINEAR]['email'], 0.5);
        $this->assertEqualsWithDelta(50.0, $result[AttributionModelService::MODEL_U_SHAPE]['social'], 0.5);
        $this->assertEqualsWithDelta(50.0, $result[AttributionModelService::MODEL_U_SHAPE]['email'], 0.5);
    }

    public function test_three_touch_distinct_channels_yields_40_20_40_u_shape(): void
    {
        $user = User::factory()->create();
        $this->touch($user, 'organic_search', now()->subDays(10));
        $this->touch($user, 'social', now()->subDays(5));
        $this->touch($user, 'email', now()->subDays(1));

        $result = app(AttributionModelService::class)->computeForUser($user->id);

        $this->assertEqualsWithDelta(100.0, $result[AttributionModelService::MODEL_FIRST_TOUCH]['organic_search'], 0.5);
        $this->assertEqualsWithDelta(100.0, $result[AttributionModelService::MODEL_LAST_TOUCH]['email'], 0.5);

        foreach (['organic_search', 'social', 'email'] as $channel) {
            $this->assertEqualsWithDelta(100.0 / 3, $result[AttributionModelService::MODEL_LINEAR][$channel], 0.5);
        }

        $this->assertEqualsWithDelta(40.0, $result[AttributionModelService::MODEL_U_SHAPE]['organic_search'], 0.5);
        $this->assertEqualsWithDelta(20.0, $result[AttributionModelService::MODEL_U_SHAPE]['social'], 0.5);
        $this->assertEqualsWithDelta(40.0, $result[AttributionModelService::MODEL_U_SHAPE]['email'], 0.5);
    }

    public function test_same_channel_repeated_credits_aggregate(): void
    {
        $user = User::factory()->create();
        $this->touch($user, 'email', now()->subDays(7));
        $this->touch($user, 'email', now()->subDays(3));

        $result = app(AttributionModelService::class)->computeForUser($user->id);

        $this->assertEqualsWithDelta(100.0, $result[AttributionModelService::MODEL_LINEAR]['email'], 0.5);
    }

    public function test_recorder_writes_idempotent_rows(): void
    {
        $user = User::factory()->create();
        $touchedAt = now();

        app(\App\Services\Growth\AttributionTouchpointRecorder::class)->record(
            user: $user,
            channel: AttributionTouchpoint::CHANNEL_DIRECT,
            touchedAt: $touchedAt,
        );
        app(\App\Services\Growth\AttributionTouchpointRecorder::class)->record(
            user: $user,
            channel: AttributionTouchpoint::CHANNEL_DIRECT,
            touchedAt: $touchedAt,
        );

        $this->assertSame(1, AttributionTouchpoint::where('user_id', $user->id)->count());
    }

    private function touch(User $user, string $channel, $touchedAt): void
    {
        AttributionTouchpoint::create([
            'user_id' => $user->id,
            'channel' => $channel,
            'touched_at' => $touchedAt,
        ]);
    }
}
