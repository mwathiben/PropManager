<?php

declare(strict_types=1);

namespace Tests\Feature\Perf;

use App\Models\User;
use App\Services\Sre\ConnectionRouter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase-57 READ-REPLICAS-1/2/3 watchdog.
 *
 * In test env the DB is single-host (no real replica), so we can't
 * meaningfully assert which connection a query lands on. We CAN assert
 * the readOnly() macro is registered + chainable, the ConnectionRouter
 * helper exists with the expected signature, and the Phase 56 callsites
 * carry the // Phase-57 READ-REPLICAS-3 marker so a future refactor
 * doesn't silently drop the opt-in.
 */
class Phase57ReadReplicasTest extends TestCase
{
    use RefreshDatabase;

    public function test_read_only_macro_is_chainable_on_eloquent_builder(): void
    {
        $builder = User::query();
        $result = $builder->readOnly();

        $this->assertSame($builder, $result, 'readOnly() must return $this for chaining.');
    }

    public function test_read_only_macro_runs_query_successfully(): void
    {
        $user = User::factory()->create();

        $fetched = User::query()->readOnly()->where('id', $user->id)->first();

        // Test connection is single-host so the readOnly() call effectively
        // routes to the same connection; correctness is what matters.
        $this->assertNotNull($fetched);
        $this->assertSame($user->id, $fetched->id);
    }

    public function test_ensure_fresh_read_returns_result_when_present(): void
    {
        $user = User::factory()->create();

        $result = app(ConnectionRouter::class)->ensureFreshRead(
            fn () => User::query()->readOnly()->where('id', $user->id)->first(),
        );

        $this->assertNotNull($result);
        $this->assertSame($user->id, $result->id);
    }

    public function test_ensure_fresh_read_returns_null_when_truly_missing(): void
    {
        $result = app(ConnectionRouter::class)->ensureFreshRead(
            fn () => User::query()->readOnly()->where('id', 99_999_999)->first(),
        );

        $this->assertNull($result);
    }

    public function test_phase56_callsites_carry_read_only_marker(): void
    {
        $churn = (string) file_get_contents(base_path('app/Services/Growth/ChurnService.php'));
        $this->assertStringContainsString(
            'Phase-57 READ-REPLICAS-3',
            $churn,
            'ChurnService::cohortsBySource lost its readOnly() opt-in marker.',
        );
        $this->assertStringContainsString('->readOnly()', $churn);

        $funnel = (string) file_get_contents(base_path('app/Services/Growth/FunnelRollupService.php'));
        $this->assertStringContainsString(
            'Phase-57 READ-REPLICAS-3',
            $funnel,
            'FunnelRollupService::computeSankeyPayload lost its readOnly() opt-in marker.',
        );
        $this->assertStringContainsString('->readOnly()', $funnel);
    }
}
