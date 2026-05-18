<?php

declare(strict_types=1);

namespace Tests\Feature\Perf;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Phase-57 PERF-DEEP surface watchdog. Cross-category presence map for
 * every Phase 57 closure.
 */
class Phase57PerfDeepSurfaceTest extends TestCase
{
    use RefreshDatabase;

    // -- P95-BUDGETS ------------------------------------------------------

    public function test_slo_enforce_budgets_command_and_schedule(): void
    {
        $this->assertTrue(class_exists(\App\Console\Commands\SloEnforceBudgets::class));
        $this->assertSame('slo:enforce-budgets', (new \App\Console\Commands\SloEnforceBudgets)->getName());

        $events = collect(Schedule::events());
        $entry = $events->first(fn ($e) => str_contains((string) $e->command, 'slo:enforce-budgets'));
        $this->assertNotNull($entry, 'slo:enforce-budgets schedule entry missing.');
        $this->assertSame('0 5 * * *', $entry->expression);
    }

    public function test_budget_enforcement_service_pure_function(): void
    {
        $this->assertTrue(class_exists(\App\Services\Sre\BudgetEnforcementService::class));
        $this->assertTrue(method_exists(\App\Services\Sre\BudgetEnforcementService::class, 'evaluate'));
    }

    // -- L7-CACHE ---------------------------------------------------------

    public function test_set_read_cache_headers_emits_vary_with_cookie(): void
    {
        $this->assertStringContainsString(
            'Cookie',
            \App\Http\Middleware\SetReadCacheHeaders::VARY_HEADER,
            'VARY_HEADER missing Cookie — shared-cache tenant leak risk.',
        );
    }

    public function test_cache_read_shared_alias_registered(): void
    {
        $bootstrap = (string) file_get_contents(base_path('bootstrap/app.php'));
        $this->assertStringContainsString("'cache.read.shared'", $bootstrap);
    }

    // -- READ-REPLICAS ----------------------------------------------------

    public function test_read_only_macro_chains_on_eloquent_builder(): void
    {
        $builder = \App\Models\User::query();
        $this->assertSame($builder, $builder->readOnly());
    }

    public function test_connection_router_exists_with_ensure_fresh_read(): void
    {
        $this->assertTrue(class_exists(\App\Services\Sre\ConnectionRouter::class));
        $this->assertTrue(method_exists(\App\Services\Sre\ConnectionRouter::class, 'ensureFreshRead'));
    }

    // -- SLOW-QUERY -------------------------------------------------------

    public function test_slow_query_tables_exist(): void
    {
        $this->assertTrue(Schema::hasTable('slow_query_log_entries'));
        $this->assertTrue(Schema::hasTable('slow_query_log_weekly_rollups'));
    }

    public function test_slow_query_rollup_scheduled_monday_0630(): void
    {
        $events = collect(Schedule::events());
        $entry = $events->first(fn ($e) => str_contains((string) $e->command, 'slow-query:rollup'));

        $this->assertNotNull($entry);
        $this->assertSame('30 6 * * 1', $entry->expression);
    }

    // -- INDEX-AUDIT ------------------------------------------------------

    public function test_index_audit_scan_command_and_schedule(): void
    {
        $this->assertTrue(class_exists(\App\Console\Commands\IndexAuditScan::class));

        $events = collect(Schedule::events());
        $entry = $events->first(fn ($e) => str_contains((string) $e->command, 'index-audit:scan'));

        $this->assertNotNull($entry);
        $this->assertSame('30 4 * * *', $entry->expression);
    }

    public function test_index_audit_catalog_has_at_least_eight_queries(): void
    {
        $this->assertGreaterThanOrEqual(8, count((new \App\Services\Sre\IndexAuditCatalog)->queries()));
    }

    // -- CI ---------------------------------------------------------------

    public function test_perf_runbook_mentions_phase_57(): void
    {
        $body = (string) file_get_contents(base_path('docs/runbooks/perf.md'));
        $this->assertStringContainsString('Phase 57', $body);
        $this->assertStringContainsString('PERF-DEEP', $body);
    }

    public function test_cache_runbook_mentions_phase_57(): void
    {
        $body = (string) file_get_contents(base_path('docs/runbooks/cache.md'));
        $this->assertStringContainsString('Phase 57', $body);
    }
}
