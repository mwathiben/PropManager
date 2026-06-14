<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers;

use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * Regression: the super-admin "view landlord" page summed a non-existent
 * invoices column (`total_amount`), 500ing on every landlord. The correct
 * column is `total_due`.
 */
class AdminLandlordShowTest extends TestCase
{
    use CreatesTestData;
    use RefreshDatabase;

    public function test_super_admin_can_view_a_landlord_show_page(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);
        $landlord = $this->createLandlordWithFullSetup()['landlord'];

        $this->actingAs($admin)
            ->get(route('admin.landlords.show', $landlord))
            ->assertOk();
    }

    public function test_total_invoiced_stat_sums_the_correct_column(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);
        $setup = $this->createLandlordWithFullSetup();
        $landlord = $setup['landlord'];
        $lease = $this->createTenantWithActiveLease($landlord, $setup['units'][0]);

        Invoice::factory()->create([
            'lease_id' => $lease->id,
            'landlord_id' => $landlord->id,
            'total_due' => 7500,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.landlords.show', $landlord))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('stats.total_invoiced', '7500.00'));
    }
}
