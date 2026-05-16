<?php

declare(strict_types=1);

namespace Tests\Feature\Insight;

use App\Models\AlertFiring;
use App\Models\WorkflowRunLog;
use App\Services\WorkflowLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase36CronBudgetTest extends TestCase
{
    use RefreshDatabase;

    public function test_measure_writes_row_with_duration_and_started_at(): void
    {
        app(WorkflowLogger::class)->measure(
            workflowName: 'test:command',
            action: 'completed',
            body: fn () => usleep(20000), // 20ms sleep
        );

        $row = WorkflowRunLog::where('workflow_name', 'test:command')->first();
        $this->assertNotNull($row);
        $this->assertNotNull($row->started_at);
        $this->assertNotNull($row->duration_ms);
        $this->assertGreaterThanOrEqual(15, $row->duration_ms);
        $this->assertLessThan(2000, $row->duration_ms);
        $this->assertSame('completed', $row->action);
    }

    public function test_measure_records_error_suffix_and_rethrows(): void
    {
        $thrown = false;
        try {
            app(WorkflowLogger::class)->measure(
                workflowName: 'test:command',
                action: 'completed',
                body: fn () => throw new \RuntimeException('boom'),
            );
        } catch (\RuntimeException $e) {
            $thrown = true;
        }

        $this->assertTrue($thrown);
        $row = WorkflowRunLog::where('workflow_name', 'test:command')->first();
        $this->assertSame('completed:error', $row->action);
        $this->assertNotNull($row->duration_ms);
    }

    public function test_log_path_remains_backwards_compatible(): void
    {
        app(WorkflowLogger::class)->log('test:command', 'completed');

        $row = WorkflowRunLog::where('workflow_name', 'test:command')->first();
        $this->assertNotNull($row);
        $this->assertNull($row->duration_ms);
        $this->assertNull($row->started_at);
    }

    public function test_budget_audit_emits_total_minutes_gauge(): void
    {
        WorkflowRunLog::create([
            'workflow_name' => 'fast:command',
            'action' => 'completed',
            'duration_ms' => 30000, // 30s
            'started_at' => now(),
            'fired_at' => now(),
        ]);
        WorkflowRunLog::create([
            'workflow_name' => 'slow:command',
            'action' => 'completed',
            'duration_ms' => 90000, // 90s
            'started_at' => now(),
            'fired_at' => now(),
        ]);

        $exit = \Artisan::call('cron:budget-audit');
        $output = \Artisan::output();
        $this->assertSame(0, $exit);
        $this->assertStringContainsString('Tracked 2', $output);
        $this->assertStringContainsString('Total 24h runtime: 2.00 minutes', $output);
    }

    public function test_budget_audit_fires_alert_when_above_threshold(): void
    {
        WorkflowRunLog::create([
            'workflow_name' => 'huge:command',
            'action' => 'completed',
            'duration_ms' => 120000, // 2 minutes
            'started_at' => now(),
            'fired_at' => now(),
        ]);

        \Artisan::call('cron:budget-audit', ['--threshold' => '1']);

        $this->assertDatabaseHas('alert_firings', [
            'alert_key' => 'high_cron_runtime',
            'severity' => 'sev3',
        ]);
    }

    public function test_budget_audit_resolves_alert_when_below_threshold(): void
    {
        AlertFiring::create([
            'alert_key' => 'high_cron_runtime',
            'severity' => 'sev3',
            'value' => 90,
            'threshold' => 60,
            'fired_at' => now()->subHour(),
        ]);

        \Artisan::call('cron:budget-audit');

        $firing = AlertFiring::where('alert_key', 'high_cron_runtime')->latest('id')->first();
        $this->assertNotNull($firing->resolved_at);
    }

    public function test_budget_audit_handles_empty_log_table(): void
    {
        $exit = \Artisan::call('cron:budget-audit');
        $output = \Artisan::output();
        $this->assertSame(0, $exit);
        $this->assertStringContainsString('Tracked 0', $output);
    }

    public function test_budget_audit_skips_rows_with_null_duration(): void
    {
        WorkflowRunLog::create([
            'workflow_name' => 'legacy:command',
            'action' => 'completed',
            'duration_ms' => null, // Phase-29 log() style
            'fired_at' => now(),
        ]);
        WorkflowRunLog::create([
            'workflow_name' => 'modern:command',
            'action' => 'completed',
            'duration_ms' => 5000,
            'started_at' => now(),
            'fired_at' => now(),
        ]);

        \Artisan::call('cron:budget-audit');
        $output = \Artisan::output();
        $this->assertStringContainsString('Tracked 1', $output);
    }
}
