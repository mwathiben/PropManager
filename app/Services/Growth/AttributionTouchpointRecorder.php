<?php

declare(strict_types=1);

namespace App\Services\Growth;

use App\Models\AttributionTouchpoint;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Phase-56 MULTI-TOUCH-1: write attribution_touchpoints rows.
 *
 * Idempotent: a record() call with the same (user_id, channel, touched_at
 * within 1 second) is a no-op. This keeps double-event-dispatch (Auth's
 * Registered + ReferralAttributed firing back-to-back) from inflating
 * touchpoint counts.
 *
 * Fail-soft: any error is logged + swallowed. Attribution writes must
 * NEVER 500 the registration response.
 */
class AttributionTouchpointRecorder
{
    public function record(
        User $user,
        string $channel,
        ?string $medium = null,
        ?string $campaign = null,
        ?int $landlordId = null,
        ?Carbon $touchedAt = null,
    ): ?AttributionTouchpoint {
        $touchedAt ??= now();

        try {
            $existing = AttributionTouchpoint::query()
                ->where('user_id', $user->id)
                ->where('channel', $channel)
                ->whereBetween('touched_at', [
                    $touchedAt->copy()->subSecond(),
                    $touchedAt->copy()->addSecond(),
                ])
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            return AttributionTouchpoint::create([
                'user_id' => $user->id,
                'channel' => $channel,
                'medium' => $medium,
                'campaign' => $campaign,
                'landlord_id' => $landlordId,
                'touched_at' => $touchedAt,
            ]);
        } catch (Throwable $e) {
            Log::warning('attribution_touchpoint_record_failed', [
                'user_id' => $user->id,
                'channel' => $channel,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
