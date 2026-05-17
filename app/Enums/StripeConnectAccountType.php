<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Phase-42 CONNECT-STANDARD-1: Express (Phase-41 default,
 * PropManager-managed onboarding, capabilities limited to
 * transfers/card_payments via destination charges) vs Standard
 * (full landlord-controlled account, direct-charge capability,
 * independent tax reporting via on_behalf_of).
 *
 * The pick is per-landlord and permanent — Stripe doesn't support
 * converting between Connect account types after creation.
 */
enum StripeConnectAccountType: string
{
    case Express = 'express';
    case Standard = 'standard';

    public function label(): string
    {
        return match ($this) {
            self::Express => 'Express (managed onboarding)',
            self::Standard => 'Standard (direct charges)',
        };
    }

    public static function values(): array
    {
        return array_map(static fn (self $c) => $c->value, self::cases());
    }
}
