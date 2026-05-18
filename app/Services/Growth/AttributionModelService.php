<?php

declare(strict_types=1);

namespace App\Services\Growth;

use App\Models\AttributionTouchpoint;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Phase-56 MULTI-TOUCH-2: compute conversion-credit allocation across a
 * user's touchpoint sequence under four canonical models.
 *
 *  - first_touch  : 100% to the earliest touch's channel.
 *  - last_touch   : 100% to the latest touch's channel.
 *  - linear       : 100/N spread equally across every touch.
 *  - u_shape      : 40% first + 20% spread across middle touches + 40% last.
 *                   With N=1 collapses to 100% on the single touch.
 *                   With N=2 collapses to 50/50.
 *                   With N=3 yields 40/20/40.
 *
 * Same-channel touchpoints share their per-touch credit by summation —
 * the result is keyed by channel, not by touchpoint id.
 *
 * Returns an empty array when the user has no touchpoints — the caller
 * decides whether 'unknown' fallback applies.
 */
class AttributionModelService
{
    public const MODEL_FIRST_TOUCH = 'first_touch';

    public const MODEL_LAST_TOUCH = 'last_touch';

    public const MODEL_LINEAR = 'linear';

    public const MODEL_U_SHAPE = 'u_shape';

    public const ALL_MODELS = [
        self::MODEL_FIRST_TOUCH,
        self::MODEL_LAST_TOUCH,
        self::MODEL_LINEAR,
        self::MODEL_U_SHAPE,
    ];

    /**
     * @return array<string, array<string, float>> Map of model_name → channel → credit_pct.
     */
    public function computeForUser(int $userId, ?Carbon $convertedAt = null): array
    {
        $convertedAt ??= now();

        $touchpoints = AttributionTouchpoint::query()
            ->where('user_id', $userId)
            ->where('touched_at', '<=', $convertedAt)
            ->orderBy('touched_at')
            ->get();

        if ($touchpoints->isEmpty()) {
            return [];
        }

        return [
            self::MODEL_FIRST_TOUCH => $this->firstTouch($touchpoints),
            self::MODEL_LAST_TOUCH => $this->lastTouch($touchpoints),
            self::MODEL_LINEAR => $this->linear($touchpoints),
            self::MODEL_U_SHAPE => $this->uShape($touchpoints),
        ];
    }

    /**
     * @param  Collection<int, AttributionTouchpoint>  $touchpoints
     * @return array<string, float>
     */
    private function firstTouch(Collection $touchpoints): array
    {
        return [$touchpoints->first()->channel => 100.0];
    }

    /**
     * @param  Collection<int, AttributionTouchpoint>  $touchpoints
     * @return array<string, float>
     */
    private function lastTouch(Collection $touchpoints): array
    {
        return [$touchpoints->last()->channel => 100.0];
    }

    /**
     * @param  Collection<int, AttributionTouchpoint>  $touchpoints
     * @return array<string, float>
     */
    private function linear(Collection $touchpoints): array
    {
        $perTouch = 100.0 / $touchpoints->count();
        $credits = [];
        foreach ($touchpoints as $touchpoint) {
            $credits[$touchpoint->channel] = ($credits[$touchpoint->channel] ?? 0.0) + $perTouch;
        }

        return $credits;
    }

    /**
     * @param  Collection<int, AttributionTouchpoint>  $touchpoints
     * @return array<string, float>
     */
    private function uShape(Collection $touchpoints): array
    {
        $n = $touchpoints->count();
        $credits = [];

        if ($n === 1) {
            return [$touchpoints->first()->channel => 100.0];
        }

        if ($n === 2) {
            $first = $touchpoints->first();
            $last = $touchpoints->last();
            $credits[$first->channel] = ($credits[$first->channel] ?? 0.0) + 50.0;
            $credits[$last->channel] = ($credits[$last->channel] ?? 0.0) + 50.0;

            return $credits;
        }

        $first = $touchpoints->first();
        $last = $touchpoints->last();
        $middle = $touchpoints->slice(1, $n - 2);
        $middlePer = $middle->isEmpty() ? 0.0 : 20.0 / $middle->count();

        $credits[$first->channel] = ($credits[$first->channel] ?? 0.0) + 40.0;
        foreach ($middle as $touchpoint) {
            $credits[$touchpoint->channel] = ($credits[$touchpoint->channel] ?? 0.0) + $middlePer;
        }
        $credits[$last->channel] = ($credits[$last->channel] ?? 0.0) + 40.0;

        return $credits;
    }
}
