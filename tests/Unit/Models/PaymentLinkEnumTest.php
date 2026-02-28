<?php

namespace Tests\Unit\Models;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

class PaymentLinkEnumTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    public function test_payment_link_invalid_for_paid_invoice(): void
    {
        ['landlord' => $landlord, 'units' => $units] = $this->createLandlordWithFullSetup();
        ['lease' => $lease] = $this->createTenantWithActiveLease($landlord, $units->first());
        $invoice = $this->createInvoiceForLease($lease, 'paid');
        $link = $this->createPaymentLink($invoice);

        $this->assertFalse($link->isValid());
    }

    public function test_payment_link_invalid_for_voided_invoice(): void
    {
        ['landlord' => $landlord, 'units' => $units] = $this->createLandlordWithFullSetup();
        ['lease' => $lease] = $this->createTenantWithActiveLease($landlord, $units->first());
        $invoice = $this->createInvoiceForLease($lease, 'voided');
        $link = $this->createPaymentLink($invoice);

        $this->assertFalse($link->isValid());
    }

    public function test_payment_link_invalid_for_cancelled_invoice(): void
    {
        ['landlord' => $landlord, 'units' => $units] = $this->createLandlordWithFullSetup();
        ['lease' => $lease] = $this->createTenantWithActiveLease($landlord, $units->first());
        $invoice = $this->createInvoiceForLease($lease, 'cancelled');
        $link = $this->createPaymentLink($invoice);

        $this->assertFalse($link->isValid());
    }

    public function test_payment_link_valid_for_sent_invoice(): void
    {
        ['landlord' => $landlord, 'units' => $units] = $this->createLandlordWithFullSetup();
        ['lease' => $lease] = $this->createTenantWithActiveLease($landlord, $units->first());
        $invoice = $this->createInvoiceForLease($lease, 'sent');
        $link = $this->createPaymentLink($invoice);

        $this->assertTrue($link->isValid());
    }
}
