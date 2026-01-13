<?php

namespace Tests\Browser\Pages;

use Laravel\Dusk\Browser;
use Laravel\Dusk\Page;

class LeaseCreatePage extends Page
{
    protected int $unitId;

    public function __construct(int $unitId)
    {
        $this->unitId = $unitId;
    }

    public function url(): string
    {
        return "/units/{$this->unitId}/lease/create";
    }

    public function assert(Browser $browser): void
    {
        $browser->assertPathIs($this->url());
    }

    public function elements(): array
    {
        return [
            '@email-input' => 'input[type="email"]',
            '@rent-input' => 'input[type="number"]:first-of-type',
            '@date-input' => 'input[type="date"]:first-of-type',
            '@submit' => 'button[type="submit"]',
        ];
    }

    public function fillInvitationForm(Browser $browser, array $data): void
    {
        $browser->type('@email-input', $data['email']);
    }

    public function submit(Browser $browser): void
    {
        $browser->press('@submit');
    }
}
