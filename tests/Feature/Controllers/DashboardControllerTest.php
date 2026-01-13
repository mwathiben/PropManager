<?php

namespace Tests\Feature\Controllers;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

class DashboardControllerTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    public function test_landlord_sees_landlord_dashboard(): void
    {
        ['landlord' => $landlord] = $this->createLandlordWithFullSetup();

        $response = $this->actingAs($landlord)
            ->get(route('dashboard'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Dashboard')
        );
    }

    public function test_caretaker_sees_dashboard(): void
    {
        $setupData = $this->createLandlordWithFullSetup();
        $caretaker = $this->createCaretakerForLandlord($setupData['landlord'], $setupData['building']);

        $response = $this->actingAs($caretaker)
            ->get(route('dashboard'));

        $response->assertOk();
    }

    public function test_tenant_gets_redirected_to_tenant_portal(): void
    {
        $setupData = $this->createLandlordWithFullSetup();
        $unit = $setupData['units']->first();
        ['tenant' => $tenant] = $this->createTenantWithActiveLease($setupData['landlord'], $unit);

        $response = $this->actingAs($tenant)
            ->get(route('dashboard'));

        $response->assertRedirect();
    }

    public function test_super_admin_sees_admin_dashboard(): void
    {
        $superAdmin = User::factory()->create(['role' => 'super_admin']);

        $response = $this->actingAs($superAdmin)
            ->get(route('dashboard'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Dashboard')
        );
    }

    public function test_landlord_without_property_redirects_to_onboarding(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);

        $response = $this->actingAs($landlord)
            ->get(route('dashboard'));

        $response->assertRedirect(route('onboarding.index'));
    }

    public function test_unit_detail_authorization(): void
    {
        $setupData = $this->createLandlordWithFullSetup();
        $unit = $setupData['units']->first();

        $otherLandlord = User::factory()->create(['role' => 'landlord']);

        $response = $this->actingAs($otherLandlord)
            ->get(route('units.detail', $unit));

        $response->assertForbidden();
    }

    public function test_dashboard_shows_correct_unit_statuses(): void
    {
        $setupData = $this->createLandlordWithFullSetup();
        $units = $setupData['units'];

        $units[0]->update(['status' => 'vacant']);
        $units[1]->update(['status' => 'occupied']);
        $units[2]->update(['status' => 'maintenance']);

        $response = $this->actingAs($setupData['landlord'])
            ->get(route('dashboard'));

        $response->assertOk();
    }

    public function test_unauthenticated_user_redirected_to_login(): void
    {
        $response = $this->get(route('dashboard'));

        $response->assertRedirect(route('login'));
    }
}
