<?php

namespace Tests\Feature\Controllers;

use App\Policies\InvoicePolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

class InvoicePolicyEnumTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    private InvoicePolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new InvoicePolicy;
    }

    public function test_delete_policy_allows_draft_invoice_deletion(): void
    {
        ['landlord' => $landlord, 'units' => $units] = $this->createLandlordWithFullSetup();
        ['lease' => $lease] = $this->createTenantWithActiveLease($landlord, $units->first());
        $invoice = $this->createInvoiceForLease($lease, 'draft');

        $this->assertTrue($this->policy->delete($landlord, $invoice));
    }

    public function test_delete_policy_denies_sent_invoice_deletion(): void
    {
        ['landlord' => $landlord, 'units' => $units] = $this->createLandlordWithFullSetup();
        ['lease' => $lease] = $this->createTenantWithActiveLease($landlord, $units->first());
        $invoice = $this->createInvoiceForLease($lease, 'sent');

        $this->assertFalse($this->policy->delete($landlord, $invoice));
    }

    public function test_delete_policy_denies_paid_invoice_deletion(): void
    {
        ['landlord' => $landlord, 'units' => $units] = $this->createLandlordWithFullSetup();
        ['lease' => $lease] = $this->createTenantWithActiveLease($landlord, $units->first());
        $invoice = $this->createInvoiceForLease($lease, 'paid');

        $this->assertFalse($this->policy->delete($landlord, $invoice));
    }

    public function test_send_policy_allows_draft_invoice(): void
    {
        ['landlord' => $landlord, 'units' => $units] = $this->createLandlordWithFullSetup();
        ['lease' => $lease] = $this->createTenantWithActiveLease($landlord, $units->first());
        $invoice = $this->createInvoiceForLease($lease, 'draft');

        $this->assertTrue($this->policy->send($landlord, $invoice));
    }

    public function test_send_policy_denies_sent_invoice(): void
    {
        ['landlord' => $landlord, 'units' => $units] = $this->createLandlordWithFullSetup();
        ['lease' => $lease] = $this->createTenantWithActiveLease($landlord, $units->first());
        $invoice = $this->createInvoiceForLease($lease, 'sent');

        $this->assertFalse($this->policy->send($landlord, $invoice));
    }

    public function test_pay_policy_allows_sent_invoice(): void
    {
        ['landlord' => $landlord, 'units' => $units] = $this->createLandlordWithFullSetup();
        ['tenant' => $tenant, 'lease' => $lease] = $this->createTenantWithActiveLease($landlord, $units->first());
        $invoice = $this->createInvoiceForLease($lease, 'sent');

        $this->assertTrue($this->policy->pay($tenant, $invoice));
    }

    public function test_pay_policy_allows_partial_invoice(): void
    {
        ['landlord' => $landlord, 'units' => $units] = $this->createLandlordWithFullSetup();
        ['tenant' => $tenant, 'lease' => $lease] = $this->createTenantWithActiveLease($landlord, $units->first());
        $invoice = $this->createInvoiceForLease($lease, 'partial');

        $this->assertTrue($this->policy->pay($tenant, $invoice));
    }

    public function test_pay_policy_allows_overdue_invoice(): void
    {
        ['landlord' => $landlord, 'units' => $units] = $this->createLandlordWithFullSetup();
        ['tenant' => $tenant, 'lease' => $lease] = $this->createTenantWithActiveLease($landlord, $units->first());
        $invoice = $this->createInvoiceForLease($lease, 'overdue');

        $this->assertTrue($this->policy->pay($tenant, $invoice));
    }

    public function test_pay_policy_denies_paid_invoice(): void
    {
        ['landlord' => $landlord, 'units' => $units] = $this->createLandlordWithFullSetup();
        ['tenant' => $tenant, 'lease' => $lease] = $this->createTenantWithActiveLease($landlord, $units->first());
        $invoice = $this->createInvoiceForLease($lease, 'paid');

        $this->assertFalse($this->policy->pay($tenant, $invoice));
    }

    public function test_pay_policy_denies_draft_invoice(): void
    {
        ['landlord' => $landlord, 'units' => $units] = $this->createLandlordWithFullSetup();
        ['tenant' => $tenant, 'lease' => $lease] = $this->createTenantWithActiveLease($landlord, $units->first());
        $invoice = $this->createInvoiceForLease($lease, 'draft');

        $this->assertFalse($this->policy->pay($tenant, $invoice));
    }
}
