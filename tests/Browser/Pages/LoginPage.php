<?php

namespace Tests\Browser\Pages;

use Laravel\Dusk\Browser;
use Laravel\Dusk\Page;

class LoginPage extends Page
{
    public function url(): string
    {
        return '/login';
    }

    public function assert(Browser $browser): void
    {
        $browser->assertPathIs($this->url());
    }

    public function elements(): array
    {
        return [
            '@email' => 'input[name="email"]',
            '@password' => 'input[name="password"]',
            '@submit' => 'button[type="submit"]',
            '@remember' => 'input[name="remember"]',
        ];
    }

    public function loginAs(Browser $browser, string $email, string $password): void
    {
        $browser->type('@email', $email)
            ->type('@password', $password)
            ->press('@submit');
    }
}
