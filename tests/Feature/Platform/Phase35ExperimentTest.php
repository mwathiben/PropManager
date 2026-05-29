<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Models\Experiment;
use App\Models\ExperimentExposure;
use App\Models\User;
use App\Services\Platform\ExperimentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class Phase35ExperimentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    private function makeExperiment(string $status = Experiment::STATUS_RUNNING, ?array $variants = null): Experiment
    {
        return Experiment::create([
            'experiment_key' => 'plan-upgrade-flow-test',
            'name' => 'Plan upgrade flow test',
            'status' => $status,
            'variants' => $variants ?? [
                ['key' => 'control', 'weight' => 50],
                ['key' => 'variant-b', 'weight' => 50],
            ],
        ]);
    }

    public function test_variant_for_returns_null_when_experiment_missing(): void
    {
        $user = User::factory()->create(['role' => 'landlord']);
        $variant = app(ExperimentService::class)->variantFor($user, 'nonexistent-key');
        $this->assertNull($variant);
    }

    public function test_variant_for_returns_control_when_paused(): void
    {
        $this->makeExperiment(Experiment::STATUS_PAUSED);
        $user = User::factory()->create(['role' => 'landlord']);
        $variant = app(ExperimentService::class)->variantFor($user, 'plan-upgrade-flow-test');
        $this->assertSame('control', $variant);
    }

    public function test_variant_for_returns_winning_when_concluded(): void
    {
        $exp = $this->makeExperiment(Experiment::STATUS_CONCLUDED);
        $exp->update(['winning_variant_key' => 'variant-b']);
        $user = User::factory()->create(['role' => 'landlord']);
        $variant = app(ExperimentService::class)->variantFor($user, 'plan-upgrade-flow-test');
        $this->assertSame('variant-b', $variant);
    }

    public function test_variant_for_writes_exposure_on_first_call(): void
    {
        $this->makeExperiment();
        $user = User::factory()->create(['role' => 'landlord']);

        app(ExperimentService::class)->variantFor($user, 'plan-upgrade-flow-test');

        $this->assertDatabaseHas('experiment_exposures', [
            'user_id' => $user->id,
            'experiment_key' => 'plan-upgrade-flow-test',
        ]);
    }

    public function test_variant_is_sticky_across_calls(): void
    {
        $this->makeExperiment();
        $user = User::factory()->create(['role' => 'landlord']);

        $first = app(ExperimentService::class)->variantFor($user, 'plan-upgrade-flow-test');
        $second = app(ExperimentService::class)->variantFor($user, 'plan-upgrade-flow-test');
        $third = app(ExperimentService::class)->variantFor($user, 'plan-upgrade-flow-test');

        $this->assertSame($first, $second);
        $this->assertSame($first, $third);
        $this->assertSame(1, ExperimentExposure::query()
            ->where('user_id', $user->id)
            ->where('experiment_key', 'plan-upgrade-flow-test')
            ->count());
    }

    public function test_variant_distribution_respects_weights(): void
    {
        $this->makeExperiment(Experiment::STATUS_RUNNING, [
            ['key' => 'control', 'weight' => 100],
            ['key' => 'variant-b', 'weight' => 0],
        ]);

        $controlCount = 0;
        for ($i = 0; $i < 20; $i++) {
            $user = User::factory()->create(['role' => 'landlord']);
            if (app(ExperimentService::class)->variantFor($user, 'plan-upgrade-flow-test') === 'control') {
                $controlCount++;
            }
        }
        $this->assertSame(20, $controlCount);
    }

    public function test_variant_for_returns_null_on_db_failure(): void
    {
        // Simulate failure by passing a closed user (no id).
        $u = new User;
        $variant = app(ExperimentService::class)->variantFor($u, 'plan-upgrade-flow-test');
        $this->assertNull($variant);
    }

    public function test_active_for_returns_map_of_running_experiments(): void
    {
        $this->makeExperiment();
        Experiment::create([
            'experiment_key' => 'second-test',
            'name' => 'Second',
            'status' => Experiment::STATUS_PAUSED,
            'variants' => [['key' => 'control', 'weight' => 100]],
        ]);
        $user = User::factory()->create(['role' => 'landlord']);

        $active = app(ExperimentService::class)->activeFor($user);
        $this->assertArrayHasKey('plan-upgrade-flow-test', $active);
        $this->assertArrayNotHasKey('second-test', $active);
    }

    public function test_inertia_share_includes_experiments_key(): void
    {
        $this->makeExperiment(Experiment::STATUS_RUNNING, [
            ['key' => 'control', 'weight' => 100],
        ]);
        $user = User::factory()->create(['role' => 'landlord']);

        $middleware = app(\App\Http\Middleware\HandleInertiaRequests::class);
        $request = \Illuminate\Http\Request::create('/');
        $request->setUserResolver(fn () => $user);

        $shared = $middleware->share($request);
        $this->assertArrayHasKey('experiments', $shared);
        $resolved = is_callable($shared['experiments']) ? ($shared['experiments'])() : $shared['experiments'];
        $this->assertIsArray($resolved);
        $this->assertSame('control', $resolved['plan-upgrade-flow-test']);
    }
}
