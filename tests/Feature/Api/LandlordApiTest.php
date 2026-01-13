<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * API tests for landlord dashboard endpoints.
 * These tests document expected API behavior for future implementation.
 */
#[Group('api')]
class LandlordApiTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    public function test_landlord_can_list_properties(): void
    {
        $this->markTestSkipped('API routes not yet implemented - tests document expected behavior');
    }

    public function test_landlord_can_view_single_property(): void
    {
        $this->markTestSkipped('API routes not yet implemented');
    }

    public function test_landlord_can_list_buildings(): void
    {
        $this->markTestSkipped('API routes not yet implemented');
    }

    public function test_landlord_can_view_building_units(): void
    {
        $this->markTestSkipped('API routes not yet implemented');
    }

    public function test_landlord_can_list_units(): void
    {
        $this->markTestSkipped('API routes not yet implemented');
    }

    public function test_landlord_can_update_unit_status(): void
    {
        $this->markTestSkipped('API routes not yet implemented');
    }

    public function test_landlord_can_list_invoices(): void
    {
        $this->markTestSkipped('API routes not yet implemented');
    }

    public function test_landlord_can_list_payments(): void
    {
        $this->markTestSkipped('API routes not yet implemented');
    }

    public function test_landlord_can_get_occupancy_report(): void
    {
        $this->markTestSkipped('API routes not yet implemented');
    }

    public function test_landlord_can_get_revenue_report(): void
    {
        $this->markTestSkipped('API routes not yet implemented');
    }

    public function test_landlord_can_get_arrears_report(): void
    {
        $this->markTestSkipped('API routes not yet implemented');
    }

    public function test_caretaker_has_landlord_manage_access(): void
    {
        $this->markTestSkipped('API routes not yet implemented');
    }

    public function test_landlord_cannot_access_other_landlords_data(): void
    {
        $this->markTestSkipped('API routes not yet implemented');
    }

    public function test_landlord_cannot_update_other_landlords_unit(): void
    {
        $this->markTestSkipped('API routes not yet implemented');
    }

    public function test_unit_status_validation(): void
    {
        $this->markTestSkipped('API routes not yet implemented');
    }

    public function test_pagination_works_for_units(): void
    {
        $this->markTestSkipped('API routes not yet implemented');
    }
}
