<?php

declare(strict_types=1);

namespace App\Listeners\Growth;

use App\Events\ReferralAttributed;
use App\Models\AttributionTouchpoint;
use App\Models\User;
use App\Services\Growth\AttributionTouchpointRecorder;

/**
 * Phase-56 MULTI-TOUCH-1: write a 'referral' touchpoint for the
 * referred user when their referral flips to attributed.
 *
 * Synchronous + best-effort. The recorder is fail-soft so this listener
 * never propagates an exception to the dispatcher.
 */
class RecordReferralAttributedTouchpoint
{
    public function __construct(private readonly AttributionTouchpointRecorder $recorder) {}

    public function handle(ReferralAttributed $event): void
    {
        $referral = $event->referral;
        $referredUser = User::find($referral->referred_user_id);
        if ($referredUser === null) {
            return;
        }

        $this->recorder->record(
            user: $referredUser,
            channel: AttributionTouchpoint::CHANNEL_REFERRAL,
            campaign: $referral->referral_code,
            touchedAt: $referral->attributed_at ?? now(),
        );
    }
}
