<?php

namespace Tests\Browser;

use App\Models\Lease;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\Browser\Pages\DashboardPage;
use Tests\DuskTestCase;

class DashboardTest extends DuskTestCase
{
    use DatabaseMigrations;

    public function test_landlord_sees_dashboard(): void
    {
        ['landlord' => $landlord] = $this->createLandlordWithProperty();

        $this->browse(function (Browser $browser) use ($landlord) {
            $browser->loginAs($landlord)
                ->visit(new DashboardPage)
                ->assertPresent('@occupancy-map');
        });
    }

    public function test_dashboard_shows_units(): void
    {
        ['landlord' => $landlord, 'units' => $units] = $this->createLandlordWithProperty();

        $this->browse(function (Browser $browser) use ($landlord) {
            $browser->loginAs($landlord)
                ->visit(new DashboardPage)
                ->assertPresent('@unit-button');
        });
    }

    public function test_unit_card_shows_correct_status_color(): void
    {
        ['landlord' => $landlord, 'units' => $units] = $this->createLandlordWithProperty();

        $units[0]->update(['status' => 'vacant']);
        $units[1]->update(['status' => 'occupied']);
        $units[2]->update(['status' => 'maintenance']);

        $this->browse(function (Browser $browser) use ($landlord) {
            $browser->loginAs($landlord)
                ->visit(new DashboardPage)
                ->assertPresent('@vacant-unit')
                ->assertPresent('@occupied-unit');
        });
    }

    public function test_dashboard_shows_metrics(): void
    {
        ['landlord' => $landlord, 'units' => $units] = $this->createLandlordWithProperty();

        $tenant = User::factory()->create([
            'role' => 'tenant',
            'landlord_id' => $landlord->id,
        ]);

        Lease::create([
            'unit_id' => $units->first()->id,
            'tenant_id' => $tenant->id,
            'landlord_id' => $landlord->id,
            'rent_amount' => 25000,
            'deposit_amount' => 25000,
            'start_date' => now(),
            'is_active' => true,
        ]);

        $units->first()->update(['status' => 'occupied']);

        $this->browse(function (Browser $browser) use ($landlord) {
            $browser->loginAs($landlord)
                ->visit(new DashboardPage)
                ->assertSee('Occupied')
                ->assertSee('Vacant');
        });
    }

    public function test_caretaker_sees_assigned_building(): void
    {
        ['landlord' => $landlord, 'building' => $building] = $this->createLandlordWithProperty();

        $caretaker = User::factory()->create([
            'role' => 'caretaker',
            'landlord_id' => $landlord->id,
        ]);

        $building->update(['caretaker_id' => $caretaker->id]);

        $this->browse(function (Browser $browser) use ($caretaker, $building) {
            $browser->loginAs($caretaker)
                ->visit('/dashboard')
                ->assertSee($building->name);
        });
    }
}
