<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Models\User;
use App\Support\DateFilter;
use App\Support\TenantClock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase-17 Phase 2 coverage:
 *   TIME-1: TenantClock::nowFor + ::timezoneFor honour User->getTimezone()
 *   TIME-2: DateFilter::parseUserDay anchors a date in the user's TZ
 *   TIME-2: DateFilter::parseUserDayOr falls back to default when blank
 */
class Phase17TimeTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_clock_returns_users_timezone_or_default(): void
    {
        // Confirm that User->getTimezone() returns the column value
        // (or 'Africa/Nairobi' default) and that TenantClock honours it.
        $usUser = User::factory()->create(['timezone' => 'America/New_York']);

        $this->assertSame('America/New_York', TenantClock::timezoneFor($usUser));
        $this->assertSame(config('app.timezone'), TenantClock::timezoneFor(null));
    }

    public function test_tenant_clock_now_for_emits_user_tz(): void
    {
        $usUser = User::factory()->create(['timezone' => 'America/New_York']);

        $now = TenantClock::nowFor($usUser);

        $this->assertSame('America/New_York', $now->timezone->getName());
    }

    public function test_date_filter_parse_user_day_anchors_in_user_tz(): void
    {
        // A user in America/New_York filtering '2026-01-15' should
        // construct a Carbon at 2026-01-15 00:00 NY-time, which is
        // 2026-01-15 05:00 UTC / 2026-01-15 08:00 Africa/Nairobi.
        // Pre-fix this fell back to Nairobi-midnight (off by 8 hours).
        $usUser = User::factory()->create(['timezone' => 'America/New_York']);

        $start = DateFilter::parseUserDay('2026-01-15', $usUser, 'startOfDay');

        $this->assertSame('America/New_York', $start->timezone->getName());
        $this->assertSame('2026-01-15 00:00:00', $start->format('Y-m-d H:i:s'));
        $this->assertSame('2026-01-15T05:00:00+00:00', $start->copy()->utc()->toIso8601String());
    }

    public function test_date_filter_end_of_day_uses_user_tz(): void
    {
        $usUser = User::factory()->create(['timezone' => 'America/New_York']);

        $end = DateFilter::parseUserDay('2026-01-15', $usUser, 'endOfDay');

        $this->assertSame('2026-01-15 23:59:59', $end->format('Y-m-d H:i:s'));
    }

    public function test_date_filter_parse_user_day_or_returns_fallback_when_blank(): void
    {
        $usUser = User::factory()->create(['timezone' => 'America/New_York']);

        $fallback = now()->subMonth();
        $result = DateFilter::parseUserDayOr(null, $usUser, $fallback, 'startOfDay');

        $this->assertSame($fallback->timestamp, $result->timestamp);
    }

    public function test_kenya_user_unchanged_from_legacy_behaviour(): void
    {
        // A Kenya user filtering '2026-01-15' must still see Nairobi
        // midnight as the boundary — pre-fix behaviour was correct for
        // them, the bug only affected non-Kenya users.
        $kenyaUser = User::factory()->create(['timezone' => 'Africa/Nairobi']);

        $start = DateFilter::parseUserDay('2026-01-15', $kenyaUser, 'startOfDay');

        $this->assertSame('Africa/Nairobi', $start->timezone->getName());
        $this->assertSame('2026-01-15 00:00:00', $start->format('Y-m-d H:i:s'));
    }
}
