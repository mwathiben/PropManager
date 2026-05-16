<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schedule;
use Tests\TestCase;

/**
 * Phase-35 PLATFORM-CI-1 watchdog: every Phase-35 cron registered
 * with the expected cadence + timezone, alert keys present in the
 * registry, lang namespaces parity holds.
 */
class Phase35PlatformSurfaceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @var array<string, array{expression: string, timezone: string}>
     */
    public const EXPECTED_SCHEDULES = [
        'subscriptions:apply-downgrades' => ['expression' => '0 2 * * *', 'timezone' => 'Africa/Nairobi'],
        'metered:soft-cap-audit' => ['expression' => '20 4 * * *', 'timezone' => 'Africa/Nairobi'],
        'product:rollup' => ['expression' => '25 4 * * *', 'timezone' => 'Africa/Nairobi'],
        'notifications:preference-drift-audit' => ['expression' => '0 7 * * 0', 'timezone' => 'Africa/Nairobi'],
    ];

    /**
     * @var string[]
     */
    public const EXPECTED_ALERT_KEYS = [
        'high_metered_overage',
    ];

    public function test_every_phase35_scheduler_is_registered_with_correct_cadence(): void
    {
        $events = collect(Schedule::events());
        $missing = [];

        foreach (self::EXPECTED_SCHEDULES as $commandName => $expected) {
            $entry = $events->first(fn ($e) => str_contains((string) $e->command, $commandName));
            if ($entry === null) {
                $missing[] = "{$commandName} (not scheduled)";

                continue;
            }
            if ($entry->expression !== $expected['expression']) {
                $missing[] = "{$commandName} expression {$entry->expression} != {$expected['expression']}";
            }
            if ($entry->timezone !== $expected['timezone']) {
                $missing[] = "{$commandName} timezone {$entry->timezone} != {$expected['timezone']}";
            }
        }

        $this->assertEmpty(
            $missing,
            "Phase-35 schedulers missing or misconfigured:\n  - ".implode("\n  - ", $missing),
        );
    }

    public function test_phase35_alert_registry_contains_required_keys(): void
    {
        $registry = collect(config('alerts.alerts'))->pluck('key')->all();
        $missing = array_values(array_diff(self::EXPECTED_ALERT_KEYS, $registry));
        $this->assertEmpty(
            $missing,
            'Phase-35 alert keys missing from config/alerts.php: '.implode(', ', $missing),
        );
    }

    public function test_phase35_platform_lang_namespace_exists_with_parity(): void
    {
        $en = require lang_path('en/platform.php');
        $sw = require lang_path('sw/platform.php');

        $this->assertIsArray($en);
        $this->assertIsArray($sw);
        $this->assertSame(
            array_keys($this->flatten($en)),
            array_keys($this->flatten($sw)),
            'Phase-35 platform.php key order must match between en and sw.',
        );
    }

    private function flatten(array $arr, string $prefix = ''): array
    {
        $out = [];
        foreach ($arr as $k => $v) {
            $key = $prefix === '' ? (string) $k : "{$prefix}.{$k}";
            if (is_array($v)) {
                $out += $this->flatten($v, $key);
            } else {
                $out[$key] = $v;
            }
        }

        return $out;
    }
}
