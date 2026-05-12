<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonImmutable;

/**
 * Phase-17 TIME-2: parse inbound date-filter inputs in the USER's
 * timezone, not the application timezone.
 *
 * Pre-Phase-17, `Carbon::parse($filters['date_from'])` fell back to
 * APP_TIMEZONE=Africa/Nairobi. A user in America/New_York filtering
 * '2026-01-15' meant `2026-01-15 00:00 NY` — which the server parsed
 * as `2026-01-15 00:00 Nairobi` (off by 8 hours / one day). Reports
 * silently included or excluded data either side of the user's day
 * boundary.
 *
 * Use `DateFilter::parseUserDay($input, $user, $boundary)` at every
 * controller / service entry point that accepts a user-supplied
 * date filter.
 *
 * Returns mutable Carbon for interop with existing query builders;
 * the Carbon-style ->setTimezone() calls downstream still work.
 */
final class DateFilter
{
    /**
     * Parse a date string in the user's timezone, anchored at the start
     * of the day. For the END of the day use $boundary='endOfDay'.
     */
    public static function parseUserDay(string $input, ?User $user, string $boundary = 'startOfDay'): Carbon
    {
        $tz = TenantClock::timezoneFor($user);
        $parsed = Carbon::parse($input, $tz);

        return match ($boundary) {
            'startOfDay' => $parsed->startOfDay(),
            'endOfDay' => $parsed->endOfDay(),
            default => $parsed,
        };
    }

    /**
     * Parse-or-default. Returns the supplied date in user-TZ, or the
     * provided fallback (already a Carbon) if input is null/empty.
     */
    public static function parseUserDayOr(?string $input, ?User $user, Carbon|CarbonImmutable $fallback, string $boundary = 'startOfDay'): Carbon
    {
        if ($input === null || $input === '') {
            return $fallback instanceof CarbonImmutable
                ? Carbon::instance($fallback)
                : $fallback;
        }

        return self::parseUserDay($input, $user, $boundary);
    }
}
