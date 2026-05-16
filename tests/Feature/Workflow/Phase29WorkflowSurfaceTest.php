<?php

declare(strict_types=1);

namespace Tests\Feature\Workflow;

use App\Events\DepositRefundApproved;
use App\Events\DepositRefundPaid;
use App\Events\DepositRefundRejected;
use App\Events\LeaseRenewalApproaching;
use App\Events\OccupancyTargetBreached;
use App\Events\PaymentPlanApproved;
use App\Events\PaymentPlanRejected;
use App\Events\VacancyDetected;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schedule;
use Tests\TestCase;

/**
 * Phase-29 WF-CI-1 watchdog: surface coverage — every Phase-29
 * scheduler is registered with the expected cadence + timezone, and
 * every Phase-29 event has at least one listener registered.
 */
class Phase29WorkflowSurfaceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @var array<string, array{expression: string, timezone: string}>
     */
    public const EXPECTED_SCHEDULES = [
        'rent-reminders:dispatch' => ['expression' => '0 8 * * *', 'timezone' => 'Africa/Nairobi'],
        'leases:scan-renewals' => ['expression' => '30 7 * * *', 'timezone' => 'Africa/Nairobi'],
        'invoices:escalate-overdue' => ['expression' => '30 0 * * *', 'timezone' => 'Africa/Nairobi'],
        'occupancy:audit' => ['expression' => '30 6 * * *', 'timezone' => 'Africa/Nairobi'],
        'workflow:health' => ['expression' => '30 4 * * *', 'timezone' => 'Africa/Nairobi'],
    ];

    /**
     * @var string[]
     */
    public const EXPECTED_EVENTS = [
        LeaseRenewalApproaching::class,
        VacancyDetected::class,
        OccupancyTargetBreached::class,
        PaymentPlanApproved::class,
        PaymentPlanRejected::class,
        DepositRefundApproved::class,
        DepositRefundRejected::class,
        DepositRefundPaid::class,
    ];

    public function test_every_phase29_scheduler_is_registered_with_correct_cadence(): void
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
            "Phase-29 schedulers missing or misconfigured:\n  - ".implode("\n  - ", $missing),
        );
    }

    public function test_every_phase29_event_has_a_listener(): void
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
            "Phase-29 events without listeners:\n  - ".implode("\n  - ", $missing),
        );
    }
}
