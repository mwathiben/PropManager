<?php

namespace Tests\Browser;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class ExampleTest extends DuskTestCase
{
    use DatabaseMigrations;

    public function test_homepage_shows_welcome_page(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/')
                ->assertSee('Log in');
        });
    }

    public function test_authenticated_user_sees_dashboard_link(): void
    {
        ['landlord' => $landlord] = $this->createLandlordWithProperty();

        $this->browse(function (Browser $browser) use ($landlord) {
            $browser->loginAs($landlord)
                ->visit('/')
                ->assertSee('Dashboard');
        });
    }
}
