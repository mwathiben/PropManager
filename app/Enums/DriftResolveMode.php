<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Phase-42 PLAN-SYNC-AUTO-1: how PropManager should react when
 * Stripe price.updated webhook surfaces a divergence between the
 * local SubscriptionPlan.price_monthly and the Stripe Price
 * unit_amount.
 *
 *   - manual_review: log the drift, emit the gauge, then stop —
 *     operator decides which side wins. Safe default.
 *   - always_app_wins: re-push SubscriptionPlan.price_monthly to
 *     Stripe via StripeSubscriptionService::createOrUpdatePlan,
 *     overwriting the Stripe-side change.
 *   - always_stripe_wins: write the Stripe Price unit_amount back
 *     to SubscriptionPlan.price_monthly. Convenient when pricing
 *     decisions live exclusively in Stripe (revenue ops control).
 */
enum DriftResolveMode: string
{
    case ManualReview = 'manual_review';
    case AlwaysAppWins = 'always_app_wins';
    case AlwaysStripeWins = 'always_stripe_wins';

    public function label(): string
    {
        return match ($this) {
            self::ManualReview => 'Manual review',
            self::AlwaysAppWins => 'Always app wins',
            self::AlwaysStripeWins => 'Always Stripe wins',
        };
    }

    public static function values(): array
    {
        return array_map(static fn (self $c) => $c->value, self::cases());
    }
}
