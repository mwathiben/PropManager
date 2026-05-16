<?php

declare(strict_types=1);

namespace Tests\Feature\Insight;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schedule;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Phase-36 INSIGHT-CI-1/2/3 watchdog: every Phase-36 cron registered
 * with the expected cadence + timezone, alert keys present in the
 * registry, sanctum-gated landlord API surface reachable, both lang
 * namespaces loadable + key sets match.
 */
class Phase36InsightSurfaceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @var array<string, array{expression: string, timezone: string}>
     */
    public const EXPECTED_SCHEDULES = [
        'cron:budget-audit' => ['expression' => '30 4 * * *', 'timezone' => 'Africa/Nairobi'],
    ];

    /**
     * @var string[]
     */
    public const EXPECTED_ALERT_KEYS = [
        'high_cron_runtime',
    ];

    /**
     * @var string[]
     */
    public const EXPECTED_LANDLORD_API_ROUTES = [
        'api.v1.landlord.engagement.index',
        'api.v1.landlord.usage.index',
        'api.v1.landlord.referrals.index',
        'api.v1.landlord.insights.summary',
    ];

    public function test_every_phase36_scheduler_is_registered_with_correct_cadence(): void
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
            "Phase-36 schedulers missing or misconfigured:\n  - ".implode("\n  - ", $missing),
        );
    }

    public function test_phase36_alert_registry_contains_required_keys(): void
    {
        $registry = collect(config('alerts.alerts'))->pluck('key')->all();
        $missing = array_values(array_diff(self::EXPECTED_ALERT_KEYS, $registry));
        $this->assertEmpty(
            $missing,
            'Phase-36 alert keys missing from config/alerts.php: '.implode(', ', $missing),
        );
    }

    public function test_phase36_landlord_api_endpoints_are_reachable_with_correct_ability(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        Sanctum::actingAs($landlord, ['landlord:manage']);

        foreach (self::EXPECTED_LANDLORD_API_ROUTES as $name) {
            $response = $this->getJson(route($name));
            $this->assertContains(
                $response->status(),
                [200, 204],
                "Route {$name} returned {$response->status()} (expected 200/204).",
            );
        }
    }

    public function test_phase36_insight_lang_namespace_exists_with_parity(): void
    {
        $en = require lang_path('en/insight.php');
        $sw = require lang_path('sw/insight.php');

        $this->assertIsArray($en);
        $this->assertIsArray($sw);
        $this->assertSame(
            array_keys($this->flatten($en)),
            array_keys($this->flatten($sw)),
            'Phase-36 insight.php key order must match between en and sw.',
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
