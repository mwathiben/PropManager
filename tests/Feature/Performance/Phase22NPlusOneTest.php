<?php

declare(strict_types=1);

namespace Tests\Feature\Performance;

use App\Models\User;
use App\Support\NPlusOneBaseline;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\LazyLoadingViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase-22 PERF-NPLUS1-1: the N+1 test-env gate watchdog.
 *
 * The gate (AppServiceProvider) throws LazyLoadingViolationException in
 * the testing environment for any model+relation pair NOT on
 * NPlusOneBaseline::ALLOWED. These tests pin that behaviour AND pin the
 * allow-list size with a shrink-only threshold — PERF-NPLUS1-2 ratchets
 * the threshold down as it removes pairs.
 */
class Phase22NPlusOneTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Shrink-only baseline threshold. Equals NPlusOneBaseline::ALLOWED
     * size at the time the gate landed. PERF-NPLUS1-2 lowers this as it
     * fixes offenders; it must NEVER be raised without a code-review
     * justification (a raise means a new un-fixed N+1 was admitted).
     */
    private const BASELINE_THRESHOLD = 0;

    public function test_lazy_loading_prevention_is_active(): void
    {
        $this->assertTrue(
            Model::preventsLazyLoading(),
            'PERF-NPLUS1-1: Model::preventLazyLoading() must be active in the testing environment.',
        );
    }

    public function test_lazy_loading_throws_for_unallowlisted_relation(): void
    {
        // A relation NOT on the baseline must hard-throw when accessed
        // without an eager-load — that is the CI gate.
        //
        // NOTE: Laravel only stamps preventsLazyLoading onto models
        // retrieved as part of a MULTI-row result (Builder::hydrate
        // sets it only when count($items) > 1) — a lone ->first() is
        // not an N+1 risk by definition. So the fixture must produce a
        // collection of 2+ rows and access the relation on one of them.
        $landlord = User::factory()->create(['role' => 'landlord']);
        User::factory()->count(2)->create([
            'role' => 'caretaker',
            'landlord_id' => $landlord->id,
        ]);

        // Guard: if this pair were ever allow-listed the test is moot.
        $this->assertFalse(
            NPlusOneBaseline::isAllowed(User::class, 'landlord'),
            'Test fixture assumption: User::landlord must not be on the baseline allow-list.',
        );

        $caretakers = User::query()->where('role', 'caretaker')->get();
        $this->assertGreaterThan(1, $caretakers->count(), 'Fixture must produce a multi-row result.');

        $this->expectException(LazyLoadingViolationException::class);
        $caretakers->first()->landlord; // lazy access on a hydrated collection -> must throw
    }

    public function test_eager_loaded_relation_does_not_throw(): void
    {
        // The gate must only fire on LAZY loads — an eager-loaded
        // relation is the correct pattern and must pass cleanly.
        $landlord = User::factory()->create(['role' => 'landlord']);
        User::factory()->count(2)->create([
            'role' => 'caretaker',
            'landlord_id' => $landlord->id,
        ]);

        $caretakers = User::query()->where('role', 'caretaker')->with('landlord')->get();

        $this->assertNotNull(
            $caretakers->first()->landlord,
            'PERF-NPLUS1-1: an eager-loaded relation must resolve without throwing.',
        );
    }

    public function test_baseline_allowlist_is_within_shrink_only_threshold(): void
    {
        $count = count(NPlusOneBaseline::ALLOWED);

        $this->assertLessThanOrEqual(
            self::BASELINE_THRESHOLD,
            $count,
            "PERF-NPLUS1-1: the N+1 baseline allow-list has {$count} entries, over the shrink-only threshold of ".
            self::BASELINE_THRESHOLD.'. The list may only shrink — a new entry means an un-fixed N+1 was admitted. '.
            'Fix the lazy-load and remove the pair, or justify the addition in code review and raise the threshold deliberately.',
        );
    }

    public function test_baseline_pairs_are_well_formed(): void
    {
        if (NPlusOneBaseline::ALLOWED === []) {
            // The healthy state — the suite has zero tested-path N+1s.
            $this->assertSame([], NPlusOneBaseline::ALLOWED, 'PERF-NPLUS1-1: an empty allow-list is the goal state.');

            return;
        }

        foreach (NPlusOneBaseline::ALLOWED as $pair) {
            $this->assertMatchesRegularExpression(
                '/^[A-Za-z0-9\\\\]+::[A-Za-z0-9_]+$/',
                $pair,
                "PERF-NPLUS1-1: baseline entry '{$pair}' must be in 'Fully\\Qualified\\Model::relation' form.",
            );
            [$class] = explode('::', $pair, 2);
            $this->assertTrue(
                class_exists($class),
                "PERF-NPLUS1-1: baseline entry '{$pair}' references a non-existent class '{$class}' — stale entry, remove it.",
            );
        }
    }
}
