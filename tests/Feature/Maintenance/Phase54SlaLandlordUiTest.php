<?php

declare(strict_types=1);

namespace Tests\Feature\Maintenance;

use App\Models\SlaDefinition;
use App\Models\User;
use App\Services\Maintenance\SlaDefinitionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Phase-54 SLA-LANDLORD-UI-1/2/3 watchdog.
 */
class Phase54SlaLandlordUiTest extends TestCase
{
    use RefreshDatabase;

    public function test_landlord_sees_own_overrides_and_globals_separately(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        $other = User::factory()->create(['role' => 'landlord']);

        SlaDefinition::create([
            'landlord_id' => $landlord->id,
            'category' => 'issue',
            'subcategory' => 'plumbing',
            'priority' => 'urgent',
            'response_seconds' => 1800,
            'resolution_seconds' => 7200,
            'is_active' => true,
        ]);

        SlaDefinition::create([
            'landlord_id' => $other->id,
            'category' => 'issue',
            'subcategory' => 'plumbing',
            'priority' => 'urgent',
            'response_seconds' => 600,
            'resolution_seconds' => 3600,
            'is_active' => true,
        ]);

        $this->actingAs($landlord)
            ->get(route('sla.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Sla/Index')
                ->has('overrides', 1)
                ->has('globals') // Phase 49 seeder rows live here
                ->has('categoryOptions')
                ->has('priorityOptions'));
    }

    public function test_landlord_can_create_their_own_override(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);

        $this->actingAs($landlord)
            ->post(route('sla.store'), [
                'category' => 'issue',
                'subcategory' => 'electrical',
                'priority' => 'urgent',
                'response_seconds' => 900,
                'resolution_seconds' => 3600,
            ])
            ->assertRedirect(route('sla.index'));

        $this->assertDatabaseHas('sla_definitions', [
            'landlord_id' => $landlord->id,
            'category' => 'issue',
            'subcategory' => 'electrical',
            'priority' => 'urgent',
            'response_seconds' => 900,
            'resolution_seconds' => 3600,
        ]);
    }

    public function test_landlord_cannot_edit_another_landlords_override(): void
    {
        $landlordA = User::factory()->create(['role' => 'landlord']);
        $landlordB = User::factory()->create(['role' => 'landlord']);
        $rowB = SlaDefinition::create([
            'landlord_id' => $landlordB->id,
            'category' => 'issue',
            'subcategory' => 'electrical',
            'priority' => 'high',
            'response_seconds' => 3600,
            'resolution_seconds' => 86400,
            'is_active' => true,
        ]);

        $this->actingAs($landlordA)
            ->patch(route('sla.update', $rowB->id), [
                'priority' => 'high',
                'response_seconds' => 60,
                'resolution_seconds' => 120,
            ])
            ->assertForbidden();
    }

    public function test_landlord_cannot_edit_platform_default_row(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        $global = SlaDefinition::create([
            'landlord_id' => null,
            'category' => null,
            'subcategory' => null,
            'priority' => 'high',
            'response_seconds' => 3600,
            'resolution_seconds' => 86400,
            'is_active' => true,
        ]);

        $this->actingAs($landlord)
            ->patch(route('sla.update', $global->id), [
                'priority' => 'high',
                'response_seconds' => 60,
                'resolution_seconds' => 120,
            ])
            ->assertForbidden();
    }

    public function test_non_landlord_blocked_at_middleware(): void
    {
        $tenant = User::factory()->create(['role' => 'tenant']);
        $this->actingAs($tenant)
            ->get(route('sla.index'))
            ->assertForbidden();
    }

    public function test_observer_bumps_cache_version_on_save(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        $svc = app(SlaDefinitionService::class);

        // Prime the version key by issuing a resolve.
        $svc->resolveFor('issue', 'plumbing', 'high', $landlord->id);
        $beforeVersion = (int) Cache::get('sla:ver:'.$landlord->id, 1);

        SlaDefinition::create([
            'landlord_id' => $landlord->id,
            'category' => 'issue',
            'subcategory' => 'plumbing',
            'priority' => 'high',
            'response_seconds' => 1800,
            'resolution_seconds' => 7200,
            'is_active' => true,
        ]);

        $afterVersion = (int) Cache::get('sla:ver:'.$landlord->id, 1);
        $this->assertGreaterThan(
            $beforeVersion,
            $afterVersion,
            'SlaDefinitionObserver::saved must bump the version cache key so resolveFor sees fresh data.',
        );
    }

    public function test_resolve_returns_landlord_override_after_save_no_5min_lag(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        $svc = app(SlaDefinitionService::class);

        // Resolve once to populate the cache with the global cascade.
        $before = $svc->resolveFor('issue', 'electrical', 'urgent', $landlord->id);

        SlaDefinition::create([
            'landlord_id' => $landlord->id,
            'category' => 'issue',
            'subcategory' => 'electrical',
            'priority' => 'urgent',
            'response_seconds' => 600,
            'resolution_seconds' => 1800,
            'is_active' => true,
        ]);

        $after = $svc->resolveFor('issue', 'electrical', 'urgent', $landlord->id);
        $this->assertSame(600, $after['response_seconds'], 'Override must be visible immediately, not after 5 minutes.');
        $this->assertSame(1800, $after['resolution_seconds']);
        $this->assertNotSame($before['response_seconds'], $after['response_seconds']);
    }
}
