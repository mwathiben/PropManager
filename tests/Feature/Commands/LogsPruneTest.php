<?php

declare(strict_types=1);

namespace Tests\Feature\Commands;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Phase-12 RETAIN-1 / RETAIN-2: logs:prune respects the configured
 * retention window for each supported table; --confirm is required
 * for destructive action; --table=all sweeps both tables.
 */
class LogsPruneTest extends TestCase
{
    use RefreshDatabase;

    private function makeAuditLogAt(\Carbon\Carbon $when): void
    {
        DB::table('audit_logs')->insert([
            'event_type' => 'updated',
            'auditable_type' => 'TestModel',
            'auditable_id' => 1,
            'created_at' => $when,
            'updated_at' => $when,
        ]);
    }

    private function makeSecurityLogAt(\Carbon\Carbon $when): void
    {
        DB::table('security_logs')->insert([
            'event_type' => 'login',
            'severity' => 'info',
            'created_at' => $when,
            'updated_at' => $when,
        ]);
    }

    public function test_dry_run_reports_candidates_without_deleting(): void
    {
        config(['security.audit.retention_days' => 30]);

        $this->makeAuditLogAt(now()->subDays(60));
        $this->makeAuditLogAt(now()->subDays(10));

        $this->artisan('logs:prune', ['--table' => 'audit'])
            ->assertExitCode(0)
            ->expectsOutputToContain('DRY RUN');

        $this->assertSame(2, DB::table('audit_logs')->count());
    }

    public function test_confirm_prunes_audit_logs_past_retention(): void
    {
        config(['security.audit.retention_days' => 30]);

        $this->makeAuditLogAt(now()->subDays(60));   // prune
        $this->makeAuditLogAt(now()->subDays(45));   // prune
        $this->makeAuditLogAt(now()->subDays(10));   // keep

        $this->artisan('logs:prune', [
            '--table' => 'audit',
            '--confirm' => true,
        ])->assertExitCode(0);

        $this->assertSame(1, DB::table('audit_logs')->count());
    }

    public function test_confirm_prunes_security_logs_past_retention(): void
    {
        config(['security.logging.retention_days' => 90]);

        $this->makeSecurityLogAt(now()->subDays(180));  // prune
        $this->makeSecurityLogAt(now()->subDays(30));   // keep

        $this->artisan('logs:prune', [
            '--table' => 'security',
            '--confirm' => true,
        ])->assertExitCode(0);

        $this->assertSame(1, DB::table('security_logs')->count());
    }

    public function test_table_all_sweeps_both_tables(): void
    {
        config([
            'security.audit.retention_days' => 30,
            'security.logging.retention_days' => 90,
        ]);

        $this->makeAuditLogAt(now()->subDays(60));
        $this->makeSecurityLogAt(now()->subDays(180));

        $this->artisan('logs:prune', [
            '--table' => 'all',
            '--confirm' => true,
        ])->assertExitCode(0);

        $this->assertSame(0, DB::table('audit_logs')->count());
        $this->assertSame(0, DB::table('security_logs')->count());
    }

    public function test_missing_table_option_is_rejected(): void
    {
        $this->artisan('logs:prune', ['--confirm' => true])
            ->assertExitCode(2);
    }

    public function test_unknown_table_option_is_rejected(): void
    {
        $this->artisan('logs:prune', [
            '--table' => 'orders',
            '--confirm' => true,
        ])->assertExitCode(2);
    }

    public function test_chunking_handles_more_than_one_batch(): void
    {
        config(['security.audit.retention_days' => 30]);

        for ($i = 0; $i < 2500; $i++) {
            $this->makeAuditLogAt(now()->subDays(60));
        }

        $this->artisan('logs:prune', [
            '--table' => 'audit',
            '--confirm' => true,
        ])->assertExitCode(0);

        $this->assertSame(0, DB::table('audit_logs')->count());
    }
}
