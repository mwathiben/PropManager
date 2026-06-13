<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use App\Models\PropertyOwner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 1c: the evidence-based reclassification of existing `landlord` accounts
 * that manage on owners' behalf (have PropertyOwner links) into `manager`.
 */
class ManagerReclassificationMigrationTest extends TestCase
{
    use RefreshDatabase;

    private function runReclassification(): object
    {
        $migration = require database_path('migrations/2026_06_14_023146_phase1c_reclassify_managing_landlords_to_manager.php');
        $migration->up();

        return $migration;
    }

    public function test_a_landlord_that_manages_owners_is_reclassified_to_manager(): void
    {
        $managingLandlord = User::factory()->create(['role' => 'landlord']);
        PropertyOwner::factory()->create(['landlord_id' => $managingLandlord->id]);

        $this->runReclassification();

        $managingLandlord->refresh();
        $this->assertSame('manager', $managingLandlord->role);
        // The scope-owner invariant must be established.
        $this->assertSame((int) $managingLandlord->id, (int) $managingLandlord->landlord_id);
    }

    public function test_a_self_managing_landlord_with_no_owners_stays_a_landlord(): void
    {
        $selfManager = User::factory()->create(['role' => 'landlord']);

        $this->runReclassification();

        $this->assertSame('landlord', $selfManager->refresh()->role);
    }

    public function test_reclassification_is_reversible(): void
    {
        $managingLandlord = User::factory()->create(['role' => 'landlord']);
        PropertyOwner::factory()->create(['landlord_id' => $managingLandlord->id]);

        $migration = $this->runReclassification();
        $this->assertSame('manager', $managingLandlord->refresh()->role);

        $migration->down();
        $this->assertSame('landlord', $managingLandlord->refresh()->role);
    }
}
