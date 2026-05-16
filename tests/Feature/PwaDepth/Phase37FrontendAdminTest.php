<?php

declare(strict_types=1);

namespace Tests\Feature\PwaDepth;

use App\Models\Experiment;
use App\Models\ExperimentExposure;
use App\Models\NotificationPreference;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase-37 PWA-FRONTEND-ADMIN-1/2/3: notification preferences page
 * renders for landlord with preference matrix props, experiments
 * admin lists + shows + concludes under super_admin gate.
 */
class Phase37FrontendAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_settings_notifications_page_renders_for_landlord_with_matrix(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord', 'email_verified_at' => now()]);

        $response = $this->actingAs($landlord)->get(route('settings.notifications'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Settings/Notifications')
            ->has('preferences')
            ->has('toggleable_types')
            ->has('transactional_locked')
            ->has('channels')
        );
    }

    public function test_settings_notifications_page_blocks_unauthenticated(): void
    {
        $this->get(route('settings.notifications'))->assertRedirect(route('login'));
    }

    public function test_settings_notifications_page_blocks_non_landlord(): void
    {
        $tenant = User::factory()->create(['role' => 'tenant', 'email_verified_at' => now()]);
        $this->actingAs($tenant)->get(route('settings.notifications'))->assertForbidden();
    }

    public function test_experiments_index_renders_with_exposure_counts_for_super_admin(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'email_verified_at' => now()]);
        Experiment::create([
            'experiment_key' => 'onboarding_cta_color',
            'name' => 'Onboarding CTA color test',
            'status' => Experiment::STATUS_RUNNING,
            'variants' => [
                ['key' => 'control', 'weight' => 50],
                ['key' => 'green', 'weight' => 50],
            ],
            'starts_at' => now()->subWeek(),
        ]);
        $user = User::factory()->create();
        ExperimentExposure::create([
            'user_id' => $user->id,
            'experiment_key' => 'onboarding_cta_color',
            'variant_key' => 'control',
            'fired_at' => now(),
        ]);

        $response = $this->actingAs($admin)->get(route('ops.experiments.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Ops/Experiments/Index')
            ->has('experiments', 1)
            ->where('experiments.0.experiment_key', 'onboarding_cta_color')
            ->where('experiments.0.exposures_total', 1)
        );
    }

    public function test_experiments_index_blocks_landlord(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord', 'email_verified_at' => now()]);
        $this->actingAs($landlord)->get(route('ops.experiments.index'))->assertForbidden();
    }

    public function test_experiment_store_creates_draft_with_validated_variants(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'email_verified_at' => now()]);

        $response = $this->actingAs($admin)->post(route('ops.experiments.store'), [
            'experiment_key' => 'new_test',
            'name' => 'New A/B test',
            'variants' => [
                ['key' => 'control', 'weight' => 50],
                ['key' => 'treatment', 'weight' => 50],
            ],
        ]);

        $response->assertRedirect(route('ops.experiments.index'));
        $this->assertDatabaseHas('experiments', [
            'experiment_key' => 'new_test',
            'status' => Experiment::STATUS_DRAFT,
        ]);
    }

    public function test_experiment_update_flips_status(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'email_verified_at' => now()]);
        $experiment = Experiment::create([
            'experiment_key' => 'flip_test',
            'name' => 'Flip test',
            'status' => Experiment::STATUS_DRAFT,
            'variants' => [['key' => 'control', 'weight' => 100]],
        ]);

        $this->actingAs($admin)->patch(
            route('ops.experiments.update', $experiment->id),
            ['status' => Experiment::STATUS_RUNNING],
        )->assertRedirect();

        $this->assertSame(Experiment::STATUS_RUNNING, $experiment->fresh()->status);
    }

    public function test_experiment_conclude_sets_winning_variant_and_ends_at(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'email_verified_at' => now()]);
        $experiment = Experiment::create([
            'experiment_key' => 'conclude_test',
            'name' => 'Conclude test',
            'status' => Experiment::STATUS_RUNNING,
            'variants' => [
                ['key' => 'control', 'weight' => 50],
                ['key' => 'green', 'weight' => 50],
            ],
        ]);

        $this->actingAs($admin)->post(
            route('ops.experiments.conclude', $experiment->id),
            ['winning_variant_key' => 'green'],
        )->assertRedirect();

        $fresh = $experiment->fresh();
        $this->assertSame(Experiment::STATUS_CONCLUDED, $fresh->status);
        $this->assertSame('green', $fresh->winning_variant_key);
        $this->assertNotNull($fresh->ends_at);
    }
}
