<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * Regression tests for SCOPE-S6: /api/v1/integrations/reports endpoints
 * resolved $landlordId from $request->user()->id, which for a super admin
 * is their own (non-landlord) user ID — silently returning empty data
 * with no way to specify a target tenant.
 */
class ReportControllerScopeTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    public function test_landlord_token_scopes_occupancy_to_own_tenant(): void
    {
        $setup = $this->createLandlordWithFullSetup();
        $landlord = $setup['landlord'];

        Sanctum::actingAs($landlord, ['landlord:manage']);
        $response = $this->getJson('/api/v1/landlord/reports/occupancy');

        $response->assertOk()
            ->assertJsonStructure(['total_units', 'occupied', 'vacant', 'occupancy_rate']);
    }

    public function test_integration_token_must_specify_landlord_id(): void
    {
        $superAdmin = User::factory()->create(['role' => 'super_admin']);

        Sanctum::actingAs($superAdmin, ['integration:webhook']);
        $response = $this->getJson('/api/v1/integrations/reports/occupancy');

        // Without ?landlord_id=X the request is now rejected with 422
        // rather than silently returning the super-admin's empty tenant.
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['landlord_id']);
    }

    public function test_integration_token_can_query_any_landlord_with_explicit_id(): void
    {
        $superAdmin = User::factory()->create(['role' => 'super_admin']);
        $targetLandlordSetup = $this->createLandlordWithFullSetup();
        $targetLandlord = $targetLandlordSetup['landlord'];

        Sanctum::actingAs($superAdmin, ['integration:webhook']);
        $response = $this->getJson(
            '/api/v1/integrations/reports/occupancy?landlord_id='.$targetLandlord->id
        );

        $response->assertOk()
            ->assertJsonStructure(['total_units', 'occupied', 'vacant']);
    }

    public function test_integration_token_rejects_non_landlord_target(): void
    {
        $superAdmin = User::factory()->create(['role' => 'super_admin']);
        $tenantSetup = $this->createTenantWithActiveLease(
            $this->createLandlordWithFullSetup()['landlord'],
            $this->createLandlordWithFullSetup()['units']->first()
        );
        $tenant = $tenantSetup['tenant'];

        Sanctum::actingAs($superAdmin, ['integration:webhook']);
        $response = $this->getJson(
            '/api/v1/integrations/reports/occupancy?landlord_id='.$tenant->id
        );

        // Tenant is not a landlord — abort with 422 explaining why.
        $response->assertStatus(422);
    }
}
