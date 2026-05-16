<?php

declare(strict_types=1);

namespace Tests\Feature\Onboarding;

use App\Events\MilestoneRecorded;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schedule;
use Tests\TestCase;

/**
 * Phase-31 ONB-CI-1 watchdog: surface coverage — every Phase-31
 * cron is registered with the expected cadence + timezone, every
 * Phase-31 event has a listener, every i18n namespace is loadable.
 */
class Phase31OnboardingSurfaceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @var array<string, array{expression: string, timezone: string}>
     */
    public const EXPECTED_SCHEDULES = [
        'onboarding-wizard:audit' => ['expression' => '45 4 * * *', 'timezone' => 'Africa/Nairobi'],
        'activation:audit' => ['expression' => '15 4 * * *', 'timezone' => 'Africa/Nairobi'],
    ];

    /**
     * @var string[]
     */
    public const EXPECTED_EVENTS = [
        MilestoneRecorded::class,
    ];

    public function test_every_phase31_scheduler_is_registered_with_correct_cadence(): void
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
            "Phase-31 schedulers missing or misconfigured:\n  - ".implode("\n  - ", $missing),
        );
    }

    public function test_every_phase31_event_has_a_listener(): void
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
            "Phase-31 events without listeners:\n  - ".implode("\n  - ", $missing),
        );
    }

    public function test_phase31_onboarding_lang_namespace_exists_with_parity(): void
    {
        $en = require lang_path('en/onboarding.php');
        $sw = require lang_path('sw/onboarding.php');

        $this->assertIsArray($en);
        $this->assertIsArray($sw);
        $this->assertSame(
            array_keys($this->flatten($en)),
            array_keys($this->flatten($sw)),
            'Phase-31 onboarding.php key order must match between en and sw.',
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
