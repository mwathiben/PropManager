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

class PaymentReceivedFlowTest extends DuskTestCase
{
    use InteractsWithMailpit, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMailpit();
        config(['app.name' => 'PropManager']);
    }

    public function test_recording_payment_sends_email_to_tenant_via_mailpit(): void
    {
        $scenario = $this->createPaymentScenario();
        $landlord = $scenario['landlord'];
        $tenant = $scenario['tenant'];
        $invoice = $scenario['invoice'];
        $unit = $scenario['unit'];
        $building = $scenario['building'];

        $paymentAmount = (float) $invoice->total_due;
        $paymentReference = 'TEST-REF-'.uniqid();

        $response = $this->actingAs($landlord)->post(
            route('invoices.recordPayment', $invoice),
            [
                'amount' => $paymentAmount,
                'payment_method' => 'bank_transfer',
                'reference' => $paymentReference,
            ]
        );

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertEmailSentTo($tenant->email, 'Payment Received');
        $this->assertEmailCount(1);

        $html = $this->getLatestEmailHtml();

        $this->assertStringContainsString(number_format($paymentAmount, 2), $html);
        $this->assertStringContainsString($paymentReference, $html);
        $this->assertStringContainsString($invoice->invoice_number, $html);
        $this->assertStringContainsString($unit->unit_number, $html);
        $this->assertStringContainsString($building->name, $html);
        $this->assertStringContainsString('KSh', $html);
        $this->assertStringContainsString('PropManager', $html);

        $links = $this->getLatestEmailLinks();
        $this->assertReceiptLinkPresent($links);
        $this->assertSignedUnsubscribeLinkPresent($links);

        $this->assertStringNotContainsString('secret_key', strtolower($html));
        $this->assertStringNotContainsString('APP_KEY', $html);
        $this->assertStringNotContainsString(config('app.key'), $html);

        $this->browse(function (Browser $browser) {
            $this->screenshotEmail($browser, 'payment-received-flow');
        });

        $this->assertFileExists(
            base_path('e2e-screenshots/emails/payment-received-flow.png')
        );
    }

    private function createPaymentScenario(): array
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
        $invoice = Invoice::factory()->forLease($lease)->sent()->create();

        return compact('landlord', 'tenant', 'building', 'unit', 'lease', 'invoice');
    }

    private function assertReceiptLinkPresent(array $links): void
    {
        $found = false;
        foreach ($links as $link) {
            if (str_contains($link, '/payments/') && str_contains($link, '/receipt')) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, 'Download Receipt link not found in email');
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
