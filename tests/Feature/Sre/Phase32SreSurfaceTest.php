<?php

declare(strict_types=1);

namespace Tests\Feature\Sre;

use App\Events\AlertFiringRecorded;
use App\Events\DegradationDetected;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schedule;
use Tests\TestCase;

/**
 * Phase-32 SRE-CI-1 watchdog: every Phase-32 cron registered with the
 * expected cadence + timezone, every event has a listener, both lang
 * namespaces are loadable + key sets match.
 */
class Phase32SreSurfaceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @var array<string, array{expression: string, timezone: string}>
     */
    public const EXPECTED_SCHEDULES = [
        'runbook:coverage-audit' => ['expression' => '0 6 * * 0', 'timezone' => 'Africa/Nairobi'],
        'runbook:staleness-audit' => ['expression' => '30 6 * * 0', 'timezone' => 'Africa/Nairobi'],
        'alert:quality' => ['expression' => '0 6 * * *', 'timezone' => 'Africa/Nairobi'],
        'slo:budget-audit' => ['expression' => '*/15 * * * *', 'timezone' => 'Africa/Nairobi'],
        'mttr:audit' => ['expression' => '45 6 * * 1', 'timezone' => 'Africa/Nairobi'],
        'outbound:health-check' => ['expression' => '*/5 * * * *', 'timezone' => 'Africa/Nairobi'],
    ];

    /**
     * @var string[]
     */
    public const EXPECTED_EVENTS = [
        AlertFiringRecorded::class,
        DegradationDetected::class,
    ];

    public function test_every_phase32_scheduler_is_registered_with_correct_cadence(): void
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
            "Phase-32 schedulers missing or misconfigured:\n  - ".implode("\n  - ", $missing),
        );
    }

    public function test_every_phase32_event_has_a_listener(): void
    {
        $eventDispatcher = Event::getFacadeRoot();
        $missing = [];

        foreach (self::EXPECTED_EVENTS as $eventClass) {
            $listeners = $eventDispatcher->getListeners($eventClass);
            if (empty($listeners)) {
                $missing[] = $eventClass;
            }
        }

        $this->assertEmpty(
            $missing,
            "Phase-32 events without listeners:\n  - ".implode("\n  - ", $missing),
        );
    }

    public function test_phase32_sre_lang_namespace_exists_with_parity(): void
    {
        $en = require lang_path('en/sre.php');
        $sw = require lang_path('sw/sre.php');

        $this->assertIsArray($en);
        $this->assertIsArray($sw);
        $this->assertSame(
            array_keys($this->flatten($en)),
            array_keys($this->flatten($sw)),
            'Phase-32 sre.php key order must match between en and sw.',
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
