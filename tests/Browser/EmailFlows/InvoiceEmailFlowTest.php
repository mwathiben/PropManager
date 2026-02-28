<?php

declare(strict_types=1);

namespace Tests\Browser\EmailFlows;

use App\Enums\Currency;
use App\Models\Building;
use App\Models\Invoice;
use App\Models\Lease;
use App\Models\Property;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;
use Tests\Traits\InteractsWithMailpit;

class InvoiceEmailFlowTest extends DuskTestCase
{
    use InteractsWithMailpit, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMailpit();
        config(['app.name' => 'PropManager']);
    }

    public function test_updating_invoice_to_sent_sends_email_via_mailpit(): void
    {
        $scenario = $this->createInvoiceScenario('draft');
        $landlord = $scenario['landlord'];
        $tenant = $scenario['tenant'];
        $invoice = $scenario['invoice'];
        $unit = $scenario['unit'];
        $building = $scenario['building'];

        $response = $this->actingAs($landlord)->put(
            route('invoices.updateStatus', $invoice),
            ['status' => 'sent']
        );

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertEmailSentTo($tenant->email, 'Invoice');
        $this->assertEmailCount(1);

        $html = $this->getLatestEmailHtml();

        $this->assertStringContainsString($invoice->invoice_number, $html);
        $this->assertStringContainsString(number_format((float) $invoice->total_due, 2), $html);
        $this->assertStringContainsString($unit->unit_number, $html);
        $this->assertStringContainsString($building->name, $html);
        $this->assertStringContainsString('KSh', $html);
        $this->assertStringContainsString('PropManager', $html);
        $this->assertStringContainsString('Due Date', $html);
        $this->assertStringContainsString('View Invoice', $html);

        $links = $this->getLatestEmailLinks();
        $this->assertInvoiceLinkPresent($links);
        $this->assertSignedUnsubscribeLinkPresent($links);

        $this->assertStringNotContainsString('secret_key', strtolower($html));
        $this->assertStringNotContainsString('APP_KEY', $html);
        $this->assertStringNotContainsString(config('app.key'), $html);

        $this->browse(function (Browser $browser) {
            $this->screenshotEmail($browser, 'invoice-sent-flow');
        });

        $this->assertFileExists(
            base_path('e2e-screenshots/emails/invoice-sent-flow.png')
        );
    }

    public function test_sending_reminder_sends_overdue_email_via_mailpit(): void
    {
        $scenario = $this->createInvoiceScenario('overdue');
        $landlord = $scenario['landlord'];
        $tenant = $scenario['tenant'];
        $invoice = $scenario['invoice'];
        $unit = $scenario['unit'];
        $building = $scenario['building'];

        $response = $this->actingAs($landlord)->post(
            route('invoices.send-reminder', $invoice)
        );

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertEmailSentTo($tenant->email, 'Payment Overdue');
        $this->assertEmailCount(1);

        $html = $this->getLatestEmailHtml();

        $this->assertStringContainsString($invoice->invoice_number, $html);
        $this->assertStringContainsString(number_format((float) $invoice->total_due, 2), $html);
        $this->assertStringContainsString($unit->unit_number, $html);
        $this->assertStringContainsString($building->name, $html);
        $this->assertStringContainsString('KSh', $html);
        $this->assertStringContainsString('PropManager', $html);
        $this->assertStringContainsString('Balance Due', $html);

        $links = $this->getLatestEmailLinks();
        $this->assertInvoiceLinkPresent($links);
        $this->assertSignedUnsubscribeLinkPresent($links);

        $this->assertStringNotContainsString('secret_key', strtolower($html));
        $this->assertStringNotContainsString('APP_KEY', $html);
        $this->assertStringNotContainsString(config('app.key'), $html);

        $this->browse(function (Browser $browser) {
            $this->screenshotEmail($browser, 'invoice-reminder-flow');
        });

        $this->assertFileExists(
            base_path('e2e-screenshots/emails/invoice-reminder-flow.png')
        );
    }

    private function createInvoiceScenario(string $invoiceState = 'draft'): array
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        $property = Property::factory()->create(['landlord_id' => $landlord->id]);
        $building = Building::factory()
            ->forProperty($property)
            ->withCurrency(Currency::KES)
            ->create();
        $unit = Unit::factory()->forBuilding($building)->create();
        $lease = Lease::factory()->forUnit($unit)->active()->create();
        $tenant = User::findOrFail($lease->tenant_id);

        $factory = Invoice::factory()->forLease($lease);
        $invoice = match ($invoiceState) {
            'draft' => $factory->create(),
            'overdue' => $factory->overdue()->create(),
            'sent' => $factory->sent()->create(),
            default => $factory->state(['status' => $invoiceState])->create(),
        };

        return compact('landlord', 'tenant', 'building', 'unit', 'lease', 'invoice');
    }

    private function assertInvoiceLinkPresent(array $links): void
    {
        $found = false;
        foreach ($links as $link) {
            if (str_contains($link, '/invoices/')) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, 'Invoice link not found in email');
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
