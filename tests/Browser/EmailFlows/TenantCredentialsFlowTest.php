<?php

declare(strict_types=1);

namespace Tests\Browser\EmailFlows;

use App\Enums\Currency;
use App\Models\Building;
use App\Models\Property;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;
use Tests\Traits\InteractsWithMailpit;

class TenantCredentialsFlowTest extends DuskTestCase
{
    use InteractsWithMailpit, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMailpit();
        config(['app.name' => 'PropManager']);
    }

    public function test_creating_lease_sends_tenant_credentials_email(): void
    {
        $scenario = $this->createLeaseScenario();
        $landlord = $scenario['landlord'];
        $property = $scenario['property'];
        $building = $scenario['building'];
        $unit = $scenario['unit'];

        $tenantEmail = 'newtenant-'.uniqid().'@example.com';
        $tenantName = 'Jane Doe';

        $response = $this->actingAs($landlord)->post(
            route('leases.store', $unit),
            [
                'name' => $tenantName,
                'email' => $tenantEmail,
                'phone' => '0712345678',
                'id_number' => 'ID123456',
                'rent_amount' => 25000,
                'deposit_amount' => 25000,
                'start_date' => now()->addDays(1)->toDateString(),
            ]
        );

        $response->assertRedirect(route('dashboard'));

        $this->assertEmailSentTo($tenantEmail, 'Welcome to');
        $this->assertEmailCount(1);

        $html = $this->getLatestEmailHtml();
        $decodedHtml = html_entity_decode($html, ENT_QUOTES, 'UTF-8');

        $this->assertStringContainsString($tenantName, $decodedHtml);
        $this->assertStringContainsString($tenantEmail, $decodedHtml);
        $this->assertStringContainsString($property->name, $decodedHtml);
        $this->assertStringContainsString($building->name, $decodedHtml);
        $this->assertStringContainsString($unit->unit_number, $decodedHtml);
        $this->assertStringContainsString(number_format(25000, 2), $decodedHtml);
        $this->assertStringContainsString('KSh', $decodedHtml);
        $this->assertStringContainsString('Temporary Password', $decodedHtml);
        $this->assertStringContainsString('Log In Now', $decodedHtml);
        $this->assertStringContainsString($landlord->name, $decodedHtml);
        $this->assertStringContainsString('PropManager', $decodedHtml);

        $links = $this->getLatestEmailLinks();
        $this->assertLoginLinkPresent($links);
        $this->assertSignedUnsubscribeLinkPresent($links);

        $this->assertStringNotContainsString('secret_key', strtolower($decodedHtml));
        $this->assertStringNotContainsString('APP_KEY', $decodedHtml);
        $this->assertStringNotContainsString(config('app.key'), $decodedHtml);

        $this->browse(function (Browser $browser) {
            $this->screenshotEmail($browser, 'tenant-credentials-flow');
        });

        $this->assertFileExists(
            base_path('e2e-screenshots/emails/tenant-credentials-flow.png')
        );
    }

    private function createLeaseScenario(): array
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        $property = Property::factory()->create(['landlord_id' => $landlord->id]);
        $building = Building::factory()
            ->forProperty($property)
            ->withCurrency(Currency::KES)
            ->create();
        $unit = Unit::factory()->forBuilding($building)->vacant()->create();

        return compact('landlord', 'property', 'building', 'unit');
    }

    private function assertLoginLinkPresent(array $links): void
    {
        $found = false;
        foreach ($links as $link) {
            if (str_contains($link, '/login')) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, 'Login link not found in email');
    }

    private function assertSignedUnsubscribeLinkPresent(array $links): void
    {
        $found = false;
        foreach ($links as $link) {
            if (str_contains($link, 'email/preferences') && str_contains($link, 'signature=')) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, 'Signed unsubscribe URL not found in email');
    }
}
