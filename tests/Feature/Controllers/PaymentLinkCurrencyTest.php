<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers;

use App\Enums\Currency;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

class PaymentLinkCurrencyTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    public function test_payment_link_page_includes_default_currency(): void
    {
        $setup = $this->createLandlordWithFullSetup();
        $tenantData = $this->createTenantWithActiveLease($setup['landlord'], $setup['units']->first());
        $invoice = $this->createInvoiceForLease($tenantData['lease']);
        $link = $this->createPaymentLink($invoice);

        $response = $this->get(route('payment.link', $link->token));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('PaymentLink/Show')
            ->where('invoice.currency', 'KES')
            ->where('invoice.currency_symbol', 'KSh')
        );
    }

    public function test_payment_link_page_includes_invoice_currency(): void
    {
        $setup = $this->createLandlordWithFullSetup();
        $tenantData = $this->createTenantWithActiveLease($setup['landlord'], $setup['units']->first());
        $invoice = $this->createInvoiceForLease($tenantData['lease']);
        $invoice->update(['currency' => Currency::USD]);
        $link = $this->createPaymentLink($invoice);

        $response = $this->get(route('payment.link', $link->token));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('PaymentLink/Show')
            ->where('invoice.currency', 'USD')
            ->where('invoice.currency_symbol', '$')
        );
    }
}
