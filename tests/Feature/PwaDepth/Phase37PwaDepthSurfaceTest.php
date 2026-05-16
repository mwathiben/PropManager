<?php

declare(strict_types=1);

namespace Tests\Feature\PwaDepth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schedule;
use Tests\TestCase;

/**
 * Phase-37 PWA-CI-1/2/3 watchdog: every Phase-37 cron registered
 * with the expected cadence + timezone, alert keys present in the
 * registry, route names exist, lang/{en,sw}/pwa.php key sets match.
 */
class Phase37PwaDepthSurfaceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @var array<string, array{expression: string, timezone: string}>
     */
    public const EXPECTED_SCHEDULES = [
        'insight:weekly-digest' => ['expression' => '0 7 * * 1', 'timezone' => 'Africa/Nairobi'],
        'gateway:proration-audit' => ['expression' => '30 5 * * *', 'timezone' => 'Africa/Nairobi'],
        'product:prune' => ['expression' => '0 3 * * 0', 'timezone' => 'Africa/Nairobi'],
        'product:cold-storage-rollover' => ['expression' => '30 3 1 * *', 'timezone' => 'Africa/Nairobi'],
    ];

    /**
     * @var string[]
     */
    public const EXPECTED_ALERT_KEYS = [
        'high_gateway_proration_drift',
    ];

    /**
     * @var string[]
     */
    public const EXPECTED_ROUTE_NAMES = [
        'settings.notifications',
        'ops.experiments.index',
        'ops.experiments.show',
        'ops.experiments.update',
        'ops.experiments.conclude',
    ];

    public function test_every_phase37_scheduler_is_registered_with_correct_cadence(): void
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
            "Phase-37 schedulers missing or misconfigured:\n  - ".implode("\n  - ", $missing),
        );
    }

    public function test_phase37_alert_registry_contains_required_keys(): void
    {
        $registry = collect(config('alerts.alerts'))->pluck('key')->all();
        $missing = array_values(array_diff(self::EXPECTED_ALERT_KEYS, $registry));
        $this->assertEmpty(
            $missing,
            'Phase-37 alert keys missing from config/alerts.php: '.implode(', ', $missing),
        );
    }

    public function test_phase37_routes_exist_by_name(): void
    {
        $missing = [];
        foreach (self::EXPECTED_ROUTE_NAMES as $name) {
            if (! Route::has($name)) {
                $missing[] = $name;
            }
        }
        $this->assertEmpty(
            $missing,
            'Phase-37 routes missing: '.implode(', ', $missing),
        );
    }

    public function test_phase37_pwa_lang_namespace_exists_with_parity(): void
    {
        $en = require lang_path('en/pwa.php');
        $sw = require lang_path('sw/pwa.php');

        $this->assertIsArray($en);
        $this->assertIsArray($sw);
        $this->assertSame(
            array_keys($this->flatten($en)),
            array_keys($this->flatten($sw)),
            'Phase-37 pwa.php key order must match between en and sw.',
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
