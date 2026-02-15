<?php

namespace Tests\Feature\Controllers;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

class DashboardStatsControllerTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    public function test_landlord_can_fetch_dashboard_stats(): void
    {
        $setup = $this->createLandlordWithFullSetup();

        $response = $this->actingAs($setup['landlord'])
            ->getJson(route('dashboard.stats'));

        $response->assertOk()
            ->assertJsonStructure([
                'financial' => ['monthly_revenue', 'expected_revenue', 'collection_rate', 'total_arrears'],
                'arrears_aging' => ['0_30', '31_60', '61_90', '90_plus'],
                'action_items' => ['overdue_invoices', 'overdue_amount', 'open_tickets'],
            ]);
    }

    public function test_unauthenticated_user_cannot_fetch_stats(): void
    {
        $response = $this->getJson('/dashboard/stats');

        $response->assertUnauthorized();
    }

    public function test_tenant_cannot_fetch_landlord_stats(): void
    {
        $setup = $this->createLandlordWithFullSetup();
        $unit = $setup['units']->first();
        $tenantData = $this->createTenantWithActiveLease($setup['landlord'], $unit);

        $response = $this->actingAs($tenantData['tenant'])
            ->getJson(route('dashboard.stats'));

        $response->assertForbidden();
    }

    public function test_caretaker_can_fetch_stats_for_their_landlord(): void
    {
        $setup = $this->createLandlordWithFullSetup();
        $caretaker = $this->createCaretakerForLandlord($setup['landlord'], $setup['building']);

        $response = $this->actingAs($caretaker)
            ->getJson(route('dashboard.stats'));

        $response->assertOk()
            ->assertJsonStructure([
                'financial' => ['monthly_revenue', 'expected_revenue', 'collection_rate', 'total_arrears'],
                'arrears_aging',
                'action_items',
            ]);
    }

    public function test_super_admin_gets_403(): void
    {
        $superAdmin = User::factory()->create(['role' => 'super_admin']);

        $response = $this->actingAs($superAdmin)
            ->getJson(route('dashboard.stats'));

        $response->assertForbidden();
    }

    public function test_stats_reflect_overdue_invoice_data(): void
    {
        $setup = $this->createLandlordWithFullSetup();
        $unit = $setup['units']->first();
        $tenantData = $this->createTenantWithActiveLease($setup['landlord'], $unit);

        $invoice = $this->createInvoiceForLease($tenantData['lease'], 'overdue');
        $invoice->update([
            'due_date' => now()->subDays(10),
        ]);

        $response = $this->actingAs($setup['landlord'])
            ->getJson(route('dashboard.stats'));

        $response->assertOk()
            ->assertJsonPath('action_items.overdue_invoices', 1);

        $this->assertEquals(
            $tenantData['lease']->rent_amount,
            $response->json('action_items.overdue_amount'),
        );
    }

    public function test_stats_endpoint_is_rate_limited(): void
    {
        $setup = $this->createLandlordWithFullSetup();

        for ($i = 0; $i < 31; $i++) {
            $response = $this->actingAs($setup['landlord'])
                ->getJson(route('dashboard.stats'));
        }

        $response->assertStatus(429);
    }
}
