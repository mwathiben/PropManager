<?php

declare(strict_types=1);

namespace App\Services\Subscriptions;

use App\Exceptions\CouponInvalidException;
use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\Subscription;
use App\Models\User;
use App\Services\MetricsService;
use Illuminate\Support\Facades\DB;

/**
 * Phase-60 COUPONS-2: validates + redeems coupon codes. Throws
 * CouponInvalidException with a translation-friendly reason on
 * validation failure; persists a redemption row + emits
 * coupon_redeemed_count gauge increment on success.
 *
 * Stripe mirror is intentionally NOT performed here — the Stripe
 * webhook (Phase-41) already syncs coupon discounts when the
 * invoice is finalized. This service writes the local record.
 */
class CouponService
{
    public function __construct(
        private readonly MetricsService $metrics,
    ) {}

    public function redeem(string $code, User $user, ?Subscription $subscription = null): CouponRedemption
    {
        $coupon = Coupon::active()->where('code', $code)->first();

        if (! $coupon) {
            throw new CouponInvalidException('coupons.invalid_code');
        }

        if ($coupon->hasReachedMaxRedemptions()) {
            throw new CouponInvalidException('coupons.max_redemptions_reached');
        }

        $alreadyRedeemed = CouponRedemption::query()
            ->where('coupon_id', $coupon->id)
            ->where('user_id', $user->id)
            ->exists();

        if ($alreadyRedeemed) {
            throw new CouponInvalidException('coupons.already_redeemed');
        }

        $redemption = DB::transaction(function () use ($coupon, $user, $subscription) {
            return CouponRedemption::create([
                'coupon_id' => $coupon->id,
                'user_id' => $user->id,
                'subscription_id' => $subscription?->id,
                'redeemed_at' => now(),
            ]);
        });

        $this->metrics->increment('coupon_redeemed_count', 1, ['code' => $coupon->code]);

        return $redemption;
    }
}
