<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use App\Models\Building;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * Characterization of TenantScope BEFORE the `manager` role lands. These pin
 * the current data-isolation contract so that adding `manager` as a new
 * scope-owner provably changes nothing for the existing roles. Do not relax
 * these — they are the multi-tenancy safety net.
 *
 * Note: TenantScope registers its global scope at model-boot time, gated on
 * Auth::check(). A real web request boots models *under* the authenticated
 * user; in-process tests must replicate that, so we clearBootedModels() after
 * acting so the scope registers for the role under test.
 */
class TenantScopeCharacterizationTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    private User $landlordA;

    private User $landlordB;

    private Unit $unitA;

    protected function setUp(): void
    {
        parent::setUp();
        ['landlord' => $this->landlordA, 'units' => $unitsA] = $this->createLandlordWithFullSetup();
        ['landlord' => $this->landlordB] = $this->createLandlordWithFullSetup();
        $this->unitA = $unitsA->first();
    }

    private function actingAsBooted(User $user): void
    {
        $this->actingAs($user);
        Model::clearBootedModels();
    }

    public function test_landlord_sees_only_their_own_buildings(): void
    {
        $this->actingAsBooted($this->landlordA);

        $this->assertSame([$this->landlordA->id], Building::query()->pluck('landlord_id')->unique()->values()->all());
    }

    public function test_caretaker_is_scoped_to_their_landlord(): void
    {
        $caretaker = $this->createCaretakerForLandlord($this->landlordA);
        $this->actingAsBooted($caretaker);

        $this->assertSame([$this->landlordA->id], Building::query()->pluck('landlord_id')->unique()->values()->all());
    }

    public function test_tenant_is_scoped_to_their_landlord(): void
    {
        ['tenant' => $tenant] = $this->createTenantWithActiveLease($this->landlordA, $this->unitA);
        $this->actingAsBooted($tenant);

        $this->assertSame([$this->landlordA->id], Building::query()->pluck('landlord_id')->unique()->values()->all());
    }

    public function test_owner_is_scoped_to_their_landlord(): void
    {
        $owner = User::factory()->create(['role' => 'owner', 'landlord_id' => $this->landlordA->id]);
        $this->actingAsBooted($owner);

        $this->assertSame([$this->landlordA->id], Building::query()->pluck('landlord_id')->unique()->values()->all());
    }

    public function test_super_admin_sees_every_landlords_data(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);
        $this->actingAsBooted($admin);

        $this->assertEqualsCanonicalizing(
            [$this->landlordA->id, $this->landlordB->id],
            Building::query()->pluck('landlord_id')->unique()->values()->all()
        );
    }

    public function test_non_scope_owner_with_null_landlord_id_fails_closed_to_nothing(): void
    {
        $orphan = User::factory()->create(['role' => 'caretaker', 'landlord_id' => null]);
        $this->actingAsBooted($orphan);

        $this->assertSame(0, Building::query()->count());
    }
}
