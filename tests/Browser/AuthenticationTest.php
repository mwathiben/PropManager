<?php

namespace Tests\Browser;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\Browser\Pages\LoginPage;
use Tests\DuskTestCase;

class AuthenticationTest extends DuskTestCase
{
    use DatabaseMigrations;

    public function test_user_can_login(): void
    {
        $user = User::factory()->create([
            'role' => 'landlord',
            'password' => bcrypt('password'),
        ]);

        $this->createLandlordWithProperty();

        $this->browse(function (Browser $browser) use ($user) {
            $browser->visit(new LoginPage)
                ->loginAs($user->email, 'password')
                ->waitForLocation('/dashboard')
                ->assertPathIs('/dashboard');
        });
    }

    public function test_invalid_credentials_show_error(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit(new LoginPage)
                ->loginAs('invalid@example.com', 'wrongpassword')
                ->waitForText('credentials')
                ->assertSee('credentials');
        });
    }

    public function test_user_can_logout(): void
    {
        ['landlord' => $landlord] = $this->createLandlordWithProperty();

        $this->browse(function (Browser $browser) use ($landlord) {
            $browser->loginAs($landlord)
                ->visit('/dashboard')
                ->waitFor('.border-t.border-gray-200.p-4 button')
                ->click('.border-t.border-gray-200.p-4 button')
                ->waitForText('Log Out')
                ->clickLink('Log Out')
                ->waitForLocation('/login')
                ->assertPathIs('/login');
        });
    }

    public function test_login_page_redirects_authenticated_users(): void
    {
        ['landlord' => $landlord] = $this->createLandlordWithProperty();

        $this->browse(function (Browser $browser) use ($landlord) {
            $browser->loginAs($landlord)
                ->visit('/login')
                ->assertPathIs('/dashboard');
        });
    }

    public function test_remember_me_checkbox_works(): void
    {
        $user = User::factory()->create([
            'role' => 'landlord',
            'password' => bcrypt('password'),
        ]);

        $this->createLandlordWithProperty();

        $this->browse(function (Browser $browser) use ($user) {
            $browser->visit(new LoginPage)
                ->type('@email', $user->email)
                ->type('@password', 'password')
                ->check('@remember')
                ->press('@submit')
                ->waitForLocation('/dashboard');
        });
    }
}
