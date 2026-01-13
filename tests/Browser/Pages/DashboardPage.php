<?php

namespace Tests\Browser\Pages;

use Laravel\Dusk\Browser;
use Laravel\Dusk\Page;

class DashboardPage extends Page
{
    public function url(): string
    {
        return '/dashboard';
    }

    public function assert(Browser $browser): void
    {
        $browser->assertPathIs($this->url());
    }

    public function elements(): array
    {
        return [
            '@occupancy-map' => '#occupancy-map',
            '@unit-button' => 'button.aspect-square',
            '@vacant-unit' => 'button.bg-gray-50',
            '@occupied-unit' => 'button.bg-green-50',
        ];
    }

    public function clickUnit(Browser $browser, string $unitNumber): void
    {
        $browser->click("button.aspect-square:contains('{$unitNumber}')");
    }
}
