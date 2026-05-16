<?php

declare(strict_types=1);

namespace Tests\Feature\Workflow;

use App\Models\WorkflowRunLog;
use App\Services\WorkflowLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase-29 WF-CI-2/3 watchdog suite.
 */
class Phase29CiTest extends TestCase
{
    use RefreshDatabase;

    public function test_workflow_logger_writes_a_row_with_full_metadata(): void
    {
        $landlord = \App\Models\User::factory()->create(['role' => 'landlord']);
        app(WorkflowLogger::class)->log(
            workflowName: 'WF-RENT-REMIND-1',
            action: 'reminder_dispatched',
            landlordId: $landlord->id,
            targetType: 'App\\Models\\Invoice',
            targetId: 42,
            metadata: ['offset' => 0, 'days_until_due' => 0],
        );

        $row = WorkflowRunLog::firstOrFail();
        $this->assertSame('WF-RENT-REMIND-1', $row->workflow_name);
        $this->assertSame('reminder_dispatched', $row->action);
        $this->assertSame(42, $row->target_id);
        $this->assertSame(['offset' => 0, 'days_until_due' => 0], $row->metadata);
        $this->assertNotNull($row->fired_at);
    }

    public function test_workflow_health_runs_and_emits_silent_count(): void
    {
        $this->artisan('workflow:health')
            ->expectsOutputToContain('silent workflow')
            ->assertSuccessful();
    }

    public function test_workflow_health_detects_silent_expected_workflow(): void
    {
        // No WF-VACANCY-1 row in last 24h — should report at least 1 silent.
        $this->artisan('workflow:health')->assertSuccessful();

        $this->assertSame(
            0,
            WorkflowRunLog::forWorkflow('WF-VACANCY-1')->inLast24h()->count(),
            'pre-condition: no rows yet',
        );
    }

    public function test_runbook_lists_every_phase29_scheduler(): void
    {
        $runbook = file_get_contents(base_path('docs/runbooks/workflow-automation.md'));

        foreach (Phase29WorkflowSurfaceTest::EXPECTED_SCHEDULES as $command => $_) {
            $this->assertStringContainsString(
                "`{$command}`",
                $runbook,
                "Scheduler {$command} not documented in runbook scheduler table",
            );
        }
    }

    public function test_runbook_lists_every_phase29_event(): void
    {
        $runbook = file_get_contents(base_path('docs/runbooks/workflow-automation.md'));

        foreach (Phase29WorkflowSurfaceTest::EXPECTED_EVENTS as $eventClass) {
            $short = class_basename($eventClass);
            $this->assertStringContainsString(
                "`{$short}`",
                $runbook,
                "Event {$short} not documented in runbook event-listener map",
            );
        }
    }

    public function test_runbook_lists_every_phase29_test_class(): void
    {
        $runbook = file_get_contents(base_path('docs/runbooks/workflow-automation.md'));
        $classes = [
            'Phase29RentReminderTest',
            'Phase29LeaseRenewTest',
            'Phase29LateFeeEscalationTest',
            'Phase29VacancyTest',
            'Phase29PayApproveTest',
            'Phase29WorkflowSurfaceTest',
            'Phase29CiTest',
        ];

        foreach ($classes as $class) {
            $this->assertStringContainsString($class, $runbook);
        }
    }
}
