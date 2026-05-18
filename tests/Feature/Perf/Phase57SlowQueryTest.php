<?php

declare(strict_types=1);

namespace Tests\Feature\Perf;

use App\Models\SlowQueryLogEntry;
use App\Models\SlowQueryLogWeeklyRollup;
use App\Services\Sre\SqlShapeNormaliser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Phase-57 SLOW-QUERY-1/2/3 watchdog.
 */
class Phase57SlowQueryTest extends TestCase
{
    use RefreshDatabase;

    public function test_slow_query_tables_exist_with_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('slow_query_log_entries'));
        foreach (['landlord_id', 'sql_shape', 'duration_ms', 'connection_name', 'executed_at'] as $col) {
            $this->assertTrue(Schema::hasColumn('slow_query_log_entries', $col));
        }

        $this->assertTrue(Schema::hasTable('slow_query_log_weekly_rollups'));
        foreach (['week_start_date', 'sql_shape', 'landlord_id', 'occurrence_count', 'p95_duration_ms', 'max_duration_ms', 'sample_sql_truncated'] as $col) {
            $this->assertTrue(Schema::hasColumn('slow_query_log_weekly_rollups', $col));
        }
    }

    public function test_sql_shape_normaliser_strips_string_literals(): void
    {
        $normaliser = new SqlShapeNormaliser;
        $shape = $normaliser->normalise("SELECT * FROM users WHERE name = 'alice'");

        $this->assertSame('SELECT * FROM users WHERE name = ?', $shape);
    }

    public function test_sql_shape_normaliser_strips_numeric_literals(): void
    {
        $normaliser = new SqlShapeNormaliser;
        $shape = $normaliser->normalise('SELECT * FROM users WHERE id = 123 AND age = 45.5');

        $this->assertStringContainsString('id = ?', $shape);
        $this->assertStringContainsString('age = ?', $shape);
    }

    public function test_sql_shape_normaliser_collapses_in_lists(): void
    {
        $normaliser = new SqlShapeNormaliser;
        $shape = $normaliser->normalise('SELECT * FROM users WHERE id IN (1, 2, 3, 4, 5)');

        $this->assertSame('SELECT * FROM users WHERE id IN (?)', $shape);
    }

    public function test_sql_shape_normaliser_truncates_at_max_length(): void
    {
        $normaliser = new SqlShapeNormaliser;
        $long = 'SELECT * FROM users WHERE '.str_repeat('column_x = ? AND ', 100);

        $shape = $normaliser->normalise($long);

        $this->assertLessThanOrEqual(SqlShapeNormaliser::MAX_SHAPE_LENGTH, strlen($shape));
    }

    public function test_slow_query_rollup_command_aggregates_entries(): void
    {
        // 3 occurrences of one shape, all in the rollup window.
        $shape = 'SELECT * FROM users WHERE id = ?';
        foreach ([100, 200, 300] as $duration) {
            SlowQueryLogEntry::create([
                'landlord_id' => null,
                'sql_shape' => $shape,
                'duration_ms' => $duration,
                'connection_name' => 'mysql',
                'executed_at' => now()->subDays(2),
            ]);
        }

        $this->artisan('slow-query:rollup')->assertExitCode(0);

        $rollup = SlowQueryLogWeeklyRollup::query()
            ->where('sql_shape', $shape)
            ->first();

        $this->assertNotNull($rollup);
        $this->assertSame(3, $rollup->occurrence_count);
        $this->assertGreaterThanOrEqual(200, $rollup->p95_duration_ms);
        $this->assertSame(300, $rollup->max_duration_ms);
    }

    public function test_slow_query_rollup_partitions_by_landlord(): void
    {
        $shape = 'SELECT * FROM tickets WHERE landlord_id = ?';
        SlowQueryLogEntry::create([
            'landlord_id' => 100,
            'sql_shape' => $shape,
            'duration_ms' => 500,
            'connection_name' => 'mysql',
            'executed_at' => now()->subDay(),
        ]);
        SlowQueryLogEntry::create([
            'landlord_id' => 200,
            'sql_shape' => $shape,
            'duration_ms' => 600,
            'connection_name' => 'mysql',
            'executed_at' => now()->subDay(),
        ]);

        $this->artisan('slow-query:rollup')->assertExitCode(0);

        $rollups = SlowQueryLogWeeklyRollup::query()
            ->where('sql_shape', $shape)
            ->get();

        $this->assertSame(2, $rollups->count(), 'Different landlord_ids must produce separate rollup rows.');
    }

    public function test_slow_query_rollup_command_signature(): void
    {
        $this->assertSame('slow-query:rollup', (new \App\Console\Commands\SlowQueryRollup)->getName());
    }
}
