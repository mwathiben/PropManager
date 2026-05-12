<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * Phase-17 TIME-1: per-user-timezone helper.
 *
 * The User model already carries a `timezone` column + `getTimezone()`
 * accessor (defaulting to Africa/Nairobi). Pre-Phase-17, only
 * QuietHoursService consumed it — every other emitted date was in
 * app-TZ, so non-Kenya users saw Kenya times labelled with their own
 * local dates.
 *
 * TenantClock is the centralised entry point. Callers anchor their
 * arithmetic against `TenantClock::nowFor($user)` and `parseUserDay`
 * so the user's timezone preference is honoured consistently.
 *
 * Phase-17 scope is the back-end ground truth — emit ISO 8601 strings
 * with explicit offset (`2026-05-12T00:05:00+03:00`) so the frontend
 * can localize deterministically. Front-end localization (Vue i18n)
 * is a Phase 19+ candidate.
 */
final class TenantClock
{
    public static function timezoneFor(?User $user): string
    {
        return $user?->getTimezone() ?? config('app.timezone', 'Africa/Nairobi');
    }

    public static function nowFor(?User $user): CarbonImmutable
    {
        return CarbonImmutable::now(self::timezoneFor($user));
    }
}
