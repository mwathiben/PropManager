<?php

declare(strict_types=1);

namespace Tests\Feature\Perf;

use App\Console\Commands\IndexAuditScan;
use App\Services\Sre\IndexAuditCatalog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase-57 INDEX-AUDIT-1/2/3 watchdog.
 */
class Phase57IndexAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_returns_at_least_eight_queries(): void
    {
        $entries = (new IndexAuditCatalog)->queries();

        $this->assertGreaterThanOrEqual(8, count($entries));
    }

    public function test_every_catalog_entry_is_a_builder_factory(): void
    {
        foreach ((new IndexAuditCatalog)->queries() as $label => $factory) {
            $builder = $factory();
            $this->assertInstanceOf(
                Builder::class,
                $builder,
                "Catalog entry {$label} must return an Eloquent Builder.",
            );
            // Builder must be planable (toSql + getBindings don't throw).
            $this->assertIsString($builder->toSql());
            $this->assertIsArray($builder->getBindings());
        }
    }

    public function test_scan_command_runs_without_error(): void
    {
        $this->artisan('index-audit:scan')->assertExitCode(0);
    }

    public function test_scan_command_signature(): void
    {
        $this->assertSame('index-audit:scan', (new IndexAuditScan)->getName());
    }

    public function test_catalog_labels_are_dot_separated_for_metric_safety(): void
    {
        foreach ((new IndexAuditCatalog)->queries() as $label => $factory) {
            // Prometheus label values should be readable + URL-safe.
            $this->assertMatchesRegularExpression(
                '/^[a-z][a-z0-9_.]*$/',
                $label,
                "Catalog label {$label} must be lowercase dot-separated.",
            );
        }
    }
}
