<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;
use Tests\Traits\MocksExternalServices;

/**
 * API tests for tenant mobile app endpoints.
 * These tests document expected API behavior for future implementation.
 */
#[Group('api')]
class TenantApiTest extends TestCase
{
    use CreatesTestData, MocksExternalServices, RefreshDatabase;

    public function test_tenant_can_get_current_lease(): void
    {
        $this->markTestSkipped('API routes not yet implemented - tests document expected behavior');
    }

    public function test_tenant_can_list_invoices(): void
    {
        $this->markTestSkipped('API routes not yet implemented');
    }

    public function test_tenant_can_view_single_invoice(): void
    {
        $this->markTestSkipped('API routes not yet implemented');
    }

    public function test_tenant_can_list_payments(): void
    {
        $this->markTestSkipped('API routes not yet implemented');
    }

    public function test_tenant_can_initiate_mpesa_payment(): void
    {
        $this->markTestSkipped('API routes not yet implemented');
    }

    public function test_tenant_can_initiate_paystack_payment(): void
    {
        $this->markTestSkipped('API routes not yet implemented');
    }

    public function test_tenant_cannot_access_other_tenants_data(): void
    {
        $this->markTestSkipped('API routes not yet implemented');
    }

    public function test_tenant_cannot_access_landlord_endpoints(): void
    {
        $this->markTestSkipped('API routes not yet implemented');
    }

    public function test_tenant_notifications_endpoint(): void
    {
        $this->markTestSkipped('API routes not yet implemented');
    }

    public function test_tenant_can_mark_notification_as_read(): void
    {
        $this->markTestSkipped('API routes not yet implemented');
    }

    public function test_tenant_can_get_payment_receipt(): void
    {
        $this->markTestSkipped('API routes not yet implemented');
    }

    public function test_tenant_lease_history(): void
    {
        $this->markTestSkipped('API routes not yet implemented');
    }

    public function test_invoice_download(): void
    {
        $this->markTestSkipped('API routes not yet implemented');
    }
}
