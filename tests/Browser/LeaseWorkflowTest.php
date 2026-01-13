<?php

namespace Tests\Browser;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\Browser\Pages\LeaseCreatePage;
use Tests\DuskTestCase;

class LeaseWorkflowTest extends DuskTestCase
{
    use DatabaseMigrations;

    public function test_landlord_can_access_invite_tenant_page(): void
    {
        ['landlord' => $landlord, 'units' => $units] = $this->createLandlordWithProperty();

        $unit = $units->first();

        $this->browse(function (Browser $browser) use ($landlord, $unit) {
            $browser->loginAs($landlord)
                ->visit(new LeaseCreatePage($unit->id))
                ->assertSee('Invite Tenant')
                ->assertSee($unit->unit_number);
        });
    }

    public function test_invite_tenant_page_shows_form_elements(): void
    {
        ['landlord' => $landlord, 'units' => $units] = $this->createLandlordWithProperty();

        $unit = $units->first();

        $this->browse(function (Browser $browser) use ($landlord, $unit) {
            $browser->loginAs($landlord)
                ->visit(new LeaseCreatePage($unit->id))
                ->assertPresent('@email-input')
                ->assertPresent('@submit');
        });
    }

    public function test_tenant_invitation_requires_email(): void
    {
        ['landlord' => $landlord, 'units' => $units] = $this->createLandlordWithProperty();

        $unit = $units->first();

        $this->browse(function (Browser $browser) use ($landlord, $unit) {
            $browser->loginAs($landlord)
                ->visit(new LeaseCreatePage($unit->id))
                ->press('@submit')
                ->pause(500)
                ->assertPresent('input:invalid');
        });
    }
}
