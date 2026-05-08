<?php

declare(strict_types=1);

namespace Tests\Browser\EmailFlows;

use App\Models\Property;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;
use Tests\Traits\InteractsWithMailpit;

class CaretakerInvitationFlowTest extends DuskTestCase
{
    use InteractsWithMailpit, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMailpit();
        config(['app.name' => 'PropManager']);
    }

    public function test_sending_caretaker_invitation_dispatches_email(): void
    {
        $scenario = $this->createInvitationScenario();
        $landlord = $scenario['landlord'];
        $property = $scenario['property'];
        $caretakerEmail = 'caretaker-'.uniqid().'@example.com';

        $response = $this->actingAs($landlord)->post(
            route('invitations.store'),
            [
                'email' => $caretakerEmail,
                'property_id' => $property->id,
            ]
        );

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertEmailSentTo($caretakerEmail, 'Invitation');
        $this->assertEmailCount(1);

        $html = $this->getLatestEmailHtml();
        $decodedHtml = html_entity_decode($html, ENT_QUOTES, 'UTF-8');

        $this->assertStringContainsString("You're Invited!", $decodedHtml);
        $this->assertStringContainsString($landlord->name, $decodedHtml);
        $this->assertStringContainsString($property->name, $decodedHtml);
        $this->assertStringContainsString('Accept Invitation', $decodedHtml);
        $this->assertStringContainsString('caretaker', $decodedHtml);
        $this->assertStringContainsString('expire', strtolower($decodedHtml));
        $this->assertStringContainsString('PropManager', $decodedHtml);

        $links = $this->getLatestEmailLinks();
        $this->assertAcceptLinkPresent($links);

        $this->assertStringNotContainsString('secret_key', strtolower($decodedHtml));
        $this->assertStringNotContainsString('APP_KEY', $decodedHtml);
        $this->assertNotEmpty(config('app.key'), 'app.key must be set for this assertion to be meaningful');
        $this->assertStringNotContainsString(config('app.key'), $decodedHtml);

        $this->browse(function (Browser $browser) {
            $this->screenshotEmail($browser, 'caretaker-invitation-flow');
        });

        $this->assertFileExists(
            base_path('e2e-screenshots/emails/caretaker-invitation-flow.png')
        );
    }

    public function test_accept_link_in_email_loads_registration_page(): void
    {
        $scenario = $this->createInvitationScenario();
        $landlord = $scenario['landlord'];
        $property = $scenario['property'];
        $caretakerEmail = 'caretaker-'.uniqid().'@example.com';

        $this->actingAs($landlord)->post(
            route('invitations.store'),
            [
                'email' => $caretakerEmail,
                'property_id' => $property->id,
            ]
        );

        $links = $this->getLatestEmailLinks();
        $acceptUrl = $this->extractAcceptUrl($links);
        $this->assertNotNull($acceptUrl, 'Accept URL not found in email links');

        auth()->logout();

        $response = $this->get($acceptUrl);

        $response->assertOk();
        $response->assertInertia(
            fn ($page) => $page
                ->component('Invitations/Accept')
                ->has('invitation')
                ->where('invitation.email', $caretakerEmail)
                ->where('invitation.landlord_name', $landlord->name)
                ->where('invitation.property_name', $property->name)
                ->where('error', null)
        );
    }

    private function createInvitationScenario(): array
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        $property = Property::factory()->create(['landlord_id' => $landlord->id]);

        return compact('landlord', 'property');
    }

    private function assertAcceptLinkPresent(array $links): void
    {
        $found = false;
        foreach ($links as $link) {
            if (str_contains($link, '/invitations/')) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, 'Accept invitation link not found in email');
    }

    private function extractAcceptUrl(array $links): ?string
    {
        foreach ($links as $link) {
            if (str_contains($link, '/invitations/')) {
                return $link;
            }
        }

        return null;
    }
}
