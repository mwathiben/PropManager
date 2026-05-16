<?php

declare(strict_types=1);

namespace Tests\Feature\Integrations;

use App\Events\PaymentAllocated;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schedule;
use Tests\TestCase;

/**
 * Phase-30 INT-CI-2 watchdog. Surface coverage for every Phase-30
 * integration cron + event listener. Mirrors Phase29WorkflowSurfaceTest.
 */
class Phase30IntegrationSurfaceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @var array<string, array{expression: string, timezone: string}>
     */
    public const EXPECTED_SCHEDULES = [
        'mpesa:reconcile-status' => ['expression' => '*/30 * * * *', 'timezone' => 'Africa/Nairobi'],
        'bank-reconciliation:audit' => ['expression' => '50 5 * * *', 'timezone' => 'Africa/Nairobi'],
        'finance:close-month' => ['expression' => '30 2 1 * *', 'timezone' => 'Africa/Nairobi'],
        'payment-plan-allocations:audit' => ['expression' => '45 5 * * *', 'timezone' => 'Africa/Nairobi'],
    ];

    /**
     * @var string[]
     */
    public const EXPECTED_EVENTS = [
        PaymentAllocated::class,
    ];

    public function test_every_phase30_scheduler_is_registered_with_correct_cadence(): void
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
            "Phase-30 schedulers missing or misconfigured:\n  - ".implode("\n  - ", $missing),
        );
    }

    public function test_every_phase30_event_has_a_listener(): void
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
            "Phase-30 events without listeners:\n  - ".implode("\n  - ", $missing),
        );
    }

    public function test_workflow_logger_is_threaded_into_phase29_commands(): void
    {
        // Phase-30 INT-CI-1: silent-failure detector demands every
        // Phase-29 command writes a workflow_runs_log row on completion.
        $files = [
            'RentRemindersDispatch.php',
            'LeasesScanRenewals.php',
            'InvoicesEscalateOverdue.php',
            'OccupancyAudit.php',
        ];
        foreach ($files as $file) {
            $contents = file_get_contents(app_path('Console/Commands/'.$file));
            $this->assertStringContainsString(
                'WorkflowLogger',
                $contents,
                "Phase-30 INT-CI-1: {$file} must inject WorkflowLogger.",
            );
            $this->assertStringContainsString(
                "action: 'completed'",
                $contents,
                "Phase-30 INT-CI-1: {$file} must emit a 'completed' workflow log row.",
            );
        }
    }
}
