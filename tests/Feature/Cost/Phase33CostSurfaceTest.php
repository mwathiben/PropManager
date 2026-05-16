<?php

declare(strict_types=1);

namespace Tests\Feature\Cost;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schedule;
use Tests\TestCase;

/**
 * Phase-33 COST-CI-1 watchdog: keeps the surface honest as the
 * codebase evolves. Same pattern as Phase32SreSurfaceTest. Asserts:
 *   - Every Phase-33 cron is scheduled with the right cron expression
 *     + Africa/Nairobi timezone.
 *   - The three Phase-33 alert keys are present in config/alerts.php.
 *   - lang/{en,sw}/cost.php load + share identical key order.
 */
class Phase33CostSurfaceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @var array<string, array{expression: string, timezone: string}>
     */
    public const EXPECTED_SCHEDULES = [
        'cost:attribute' => ['expression' => '30 3 * * *', 'timezone' => 'Africa/Nairobi'],
        'query:cost-audit' => ['expression' => '45 3 * * *', 'timezone' => 'Africa/Nairobi'],
        'cache:hit-rate-audit' => ['expression' => '50 3 * * *', 'timezone' => 'Africa/Nairobi'],
        'log:volume-audit' => ['expression' => '55 3 * * *', 'timezone' => 'Africa/Nairobi'],
        'storage:tier-policy' => ['expression' => '30 4 * * 0', 'timezone' => 'Africa/Nairobi'],
        'storage:cost-audit' => ['expression' => '0 5 * * 0', 'timezone' => 'Africa/Nairobi'],
    ];

    /**
     * @var string[]
     */
    public const EXPECTED_ALERT_KEYS = [
        'high_query_scan_ratio',
        'low_cache_hit_rate',
        'high_landlord_log_volume',
    ];

    public function test_every_phase33_scheduler_is_registered_with_correct_cadence(): void
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
            "Phase-33 schedulers missing or misconfigured:\n  - ".implode("\n  - ", $missing),
        );
    }

    public function test_phase33_alert_registry_contains_required_keys(): void
    {
        $registry = collect(config('alerts.alerts'))->pluck('key')->all();
        $missing = array_values(array_diff(self::EXPECTED_ALERT_KEYS, $registry));
        $this->assertEmpty(
            $missing,
            'Phase-33 alert keys missing from config/alerts.php: '.implode(', ', $missing),
        );
    }

    public function test_phase33_cost_lang_namespace_exists_with_parity(): void
    {
        $en = require lang_path('en/cost.php');
        $sw = require lang_path('sw/cost.php');

        $this->assertIsArray($en);
        $this->assertIsArray($sw);
        $this->assertSame(
            array_keys($this->flatten($en)),
            array_keys($this->flatten($sw)),
            'Phase-33 cost.php key order must match between en and sw.',
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
