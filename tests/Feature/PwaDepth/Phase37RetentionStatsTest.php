<?php

declare(strict_types=1);

namespace Tests\Feature\PwaDepth;

use App\Models\Experiment;
use App\Models\ExperimentExposure;
use App\Models\ProductEvent;
use App\Models\User;
use App\Services\Platform\ExperimentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Phase-37 PWA-RETENTION-STATS-1/2/3: product:prune retention,
 * cold-storage rollover, and ExperimentService::computeSignificance
 * two-proportion z-test against known scenarios.
 */
class Phase37RetentionStatsTest extends TestCase
{
    use RefreshDatabase;

    private function seedEvent(int $userId, int $landlordId, string $createdAt): void
    {
        ProductEvent::query()->withoutGlobalScopes()->create([
            'user_id' => $userId,
            'landlord_id' => $landlordId,
            'event_name' => 'page_view',
            'properties' => ['path' => '/dashboard'],
            'created_at' => $createdAt,
        ]);
    }

    public function test_prune_deletes_rows_older_than_default_180_day_retention(): void
    {
        $user = User::factory()->create();
        $this->seedEvent($user->id, $user->id, now()->subDays(200)->toDateTimeString());
        $this->seedEvent($user->id, $user->id, now()->subDays(100)->toDateTimeString());

        $this->artisan('product:prune')->assertExitCode(0);

        $remaining = ProductEvent::query()->withoutGlobalScopes()->count();
        $this->assertSame(1, $remaining);
    }

    public function test_prune_dry_run_does_not_delete(): void
    {
        $user = User::factory()->create();
        $this->seedEvent($user->id, $user->id, now()->subDays(300)->toDateTimeString());

        $this->artisan('product:prune', ['--dry-run' => true])->assertExitCode(0);

        $this->assertSame(1, ProductEvent::query()->withoutGlobalScopes()->count());
    }

    public function test_prune_respects_custom_days_option(): void
    {
        $user = User::factory()->create();
        $this->seedEvent($user->id, $user->id, now()->subDays(50)->toDateTimeString());
        $this->seedEvent($user->id, $user->id, now()->subDays(20)->toDateTimeString());

        $this->artisan('product:prune', ['--days' => 30])->assertExitCode(0);

        $this->assertSame(1, ProductEvent::query()->withoutGlobalScopes()->count());
    }

    public function test_cold_storage_rollover_writes_jsonl_gz_per_landlord(): void
    {
        Storage::fake('archive');
        $landlordA = User::factory()->create(['role' => 'landlord']);
        $landlordB = User::factory()->create(['role' => 'landlord']);
        $lastMonth = now()->subMonth()->startOfMonth()->addDay();
        $this->seedEvent($landlordA->id, $landlordA->id, $lastMonth->toDateTimeString());
        $this->seedEvent($landlordA->id, $landlordA->id, $lastMonth->copy()->addHour()->toDateTimeString());
        $this->seedEvent($landlordB->id, $landlordB->id, $lastMonth->copy()->addDay()->toDateTimeString());

        $this->artisan('product:cold-storage-rollover')->assertExitCode(0);

        $monthLabel = now()->subMonth()->format('Y-m');
        Storage::disk('archive')->assertExists("product-events/{$landlordA->id}/{$monthLabel}/events.jsonl.gz");
        Storage::disk('archive')->assertExists("product-events/{$landlordB->id}/{$monthLabel}/events.jsonl.gz");

        $contents = gzdecode(Storage::disk('archive')->get("product-events/{$landlordA->id}/{$monthLabel}/events.jsonl.gz"));
        $lines = array_filter(explode("\n", $contents));
        $this->assertCount(2, $lines);
        $decoded = json_decode($lines[0], true);
        $this->assertSame($landlordA->id, $decoded['landlord_id']);
    }

    public function test_cold_storage_rollover_idempotent_for_same_month(): void
    {
        Storage::fake('archive');
        $user = User::factory()->create(['role' => 'landlord']);
        $lastMonth = now()->subMonth()->startOfMonth()->addDay();
        $this->seedEvent($user->id, $user->id, $lastMonth->toDateTimeString());

        $this->artisan('product:cold-storage-rollover')->assertExitCode(0);
        $monthLabel = now()->subMonth()->format('Y-m');
        $firstSize = strlen(Storage::disk('archive')->get("product-events/{$user->id}/{$monthLabel}/events.jsonl.gz"));

        $this->seedEvent($user->id, $user->id, $lastMonth->copy()->addHour()->toDateTimeString());
        $this->artisan('product:cold-storage-rollover')->assertExitCode(0);

        $this->assertSame(
            $firstSize,
            strlen(Storage::disk('archive')->get("product-events/{$user->id}/{$monthLabel}/events.jsonl.gz")),
            'idempotent — second run must not overwrite existing archive',
        );
    }

    public function test_significance_returns_significant_for_classic_scenario(): void
    {
        $experiment = Experiment::create([
            'experiment_key' => 'sig_classic',
            'name' => 'Classic z-test',
            'status' => Experiment::STATUS_RUNNING,
            'variants' => [
                ['key' => 'control', 'weight' => 50],
                ['key' => 'treatment', 'weight' => 50],
            ],
        ]);

        // 47 of 1000 control conversions, 71 of 1000 treatment.
        // Expected: z ≈ 2.187, p ≈ 0.0287 (significant).
        $userId = 1;
        $controlSuccess = [];
        $treatmentSuccess = [];
        for ($i = 0; $i < 1000; $i++) {
            ExperimentExposure::create([
                'user_id' => $userId,
                'experiment_key' => 'sig_classic',
                'variant_key' => 'control',
                'fired_at' => now(),
            ]);
            if ($i < 47) {
                $controlSuccess[] = $userId;
            }
            $userId++;
        }
        for ($i = 0; $i < 1000; $i++) {
            ExperimentExposure::create([
                'user_id' => $userId,
                'experiment_key' => 'sig_classic',
                'variant_key' => 'treatment',
                'fired_at' => now(),
            ]);
            if ($i < 71) {
                $treatmentSuccess[] = $userId;
            }
            $userId++;
        }

        $controlSet = array_flip($controlSuccess);
        $treatmentSet = array_flip($treatmentSuccess);
        $isSuccess = function (int $uid) use ($controlSet, $treatmentSet): bool {
            return isset($controlSet[$uid]) || isset($treatmentSet[$uid]);
        };

        $result = app(ExperimentService::class)->computeSignificance('sig_classic', $isSuccess);

        $this->assertSame(47, $result['conversions_a']);
        $this->assertSame(71, $result['conversions_b']);
        // Pooled two-proportion z: z = (0.047 - 0.071) / sqrt(0.059
        // * 0.941 * (2/1000)) ≈ -2.278. p-value ≈ 0.023 (two-tailed).
        $this->assertEqualsWithDelta(-2.278, $result['z_score'], 0.05);
        $this->assertEqualsWithDelta(0.023, $result['p_value'], 0.01);
        $this->assertTrue($result['is_significant']);
    }

    public function test_significance_returns_not_significant_for_tied_conversions(): void
    {
        $experiment = Experiment::create([
            'experiment_key' => 'sig_tie',
            'name' => 'Tied z-test',
            'status' => Experiment::STATUS_RUNNING,
            'variants' => [
                ['key' => 'control', 'weight' => 50],
                ['key' => 'treatment', 'weight' => 50],
            ],
        ]);

        $userId = 10000;
        for ($i = 0; $i < 1000; $i++) {
            ExperimentExposure::create([
                'user_id' => $userId++,
                'experiment_key' => 'sig_tie',
                'variant_key' => 'control',
                'fired_at' => now(),
            ]);
        }
        for ($i = 0; $i < 1000; $i++) {
            ExperimentExposure::create([
                'user_id' => $userId++,
                'experiment_key' => 'sig_tie',
                'variant_key' => 'treatment',
                'fired_at' => now(),
            ]);
        }

        // Identical 50 conversions out of 1000 each.
        $controlIds = range(10000, 10049);
        $treatmentIds = range(11000, 11049);
        $set = array_flip(array_merge($controlIds, $treatmentIds));
        $isSuccess = fn (int $uid) => isset($set[$uid]);

        $result = app(ExperimentService::class)->computeSignificance('sig_tie', $isSuccess);

        $this->assertEqualsWithDelta(0.0, $result['z_score'], 0.001);
        $this->assertEqualsWithDelta(1.0, $result['p_value'], 0.001);
        $this->assertFalse($result['is_significant']);
    }

    public function test_significance_throws_for_non_2_variant_experiment(): void
    {
        Experiment::create([
            'experiment_key' => 'three_way',
            'name' => 'Three-way',
            'status' => Experiment::STATUS_RUNNING,
            'variants' => [
                ['key' => 'a', 'weight' => 33],
                ['key' => 'b', 'weight' => 33],
                ['key' => 'c', 'weight' => 34],
            ],
        ]);

        $this->expectException(\InvalidArgumentException::class);
        app(ExperimentService::class)->computeSignificance('three_way', fn () => false);
    }
}
