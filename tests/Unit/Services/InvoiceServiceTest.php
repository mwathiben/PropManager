<?php

namespace Tests\Unit\Services;

use App\Models\Building;
use App\Models\Invoice;
use App\Models\Lease;
use App\Models\Property;
use App\Models\Unit;
use App\Models\User;
use App\Models\WaterReading;
use App\Services\InvoiceService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceServiceTest extends TestCase
{
    use RefreshDatabase;

    protected InvoiceService $service;

    protected User $landlord;

    protected Property $property;

    protected Building $building;

    protected Unit $unit;

    protected Lease $lease;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new InvoiceService;

        $this->landlord = User::factory()->create(['role' => 'landlord']);
        $this->property = Property::create([
            'name' => 'Test Property',
            'address' => '123 Test St',
            'type' => 'apartment',
            'landlord_id' => $this->landlord->id,
        ]);
        $this->building = Building::create([
            'property_id' => $this->property->id,
            'name' => 'Block A',
            'total_floors' => 1,
            'units_per_floor' => 1,
            'landlord_id' => $this->landlord->id,
            'building_type' => 'residential_apartment',
        ]);
        $this->unit = Unit::create([
            'building_id' => $this->building->id,
            'unit_number' => 'A101',
            'floor_number' => 1,
            'status' => 'occupied',
            'target_rent' => 25000,
            'landlord_id' => $this->landlord->id,
        ]);
        $tenant = User::factory()->create([
            'role' => 'tenant',
            'landlord_id' => $this->landlord->id,
        ]);
        $this->lease = Lease::create([
            'unit_id' => $this->unit->id,
            'tenant_id' => $tenant->id,
            'landlord_id' => $this->landlord->id,
            'rent_amount' => 25000,
            'deposit_amount' => 25000,
            'start_date' => now(),
            'is_active' => true,
            'wallet_balance' => 0,
        ]);
    }

    public function test_generates_unique_invoice_number(): void
    {
        $billingPeriod = Carbon::now()->startOfMonth();

        $invoice1 = $this->service->generateInvoiceForLease($this->lease, $billingPeriod);
        $invoice2 = $this->service->generateInvoiceForLease($this->lease, $billingPeriod->copy()->addMonth());

        $this->assertNotEquals($invoice1->invoice_number, $invoice2->invoice_number);
        $this->assertMatchesRegularExpression('/^INV-\d{6}-\d{4}$/', $invoice1->invoice_number);
    }

    public function test_invoice_number_format_is_correct(): void
    {
        $billingPeriod = Carbon::now()->startOfMonth();
        $invoice = $this->service->generateInvoiceForLease($this->lease, $billingPeriod);

        $expectedPrefix = 'INV-'.date('Ym');
        $this->assertStringStartsWith($expectedPrefix, $invoice->invoice_number);
    }

    public function test_calculates_water_charges_for_consumption_billing(): void
    {
        $this->building->update([
            'water_billing_type' => 'consumption',
        ]);

        WaterReading::create([
            'unit_id' => $this->unit->id,
            'landlord_id' => $this->landlord->id,
            'reading_date' => now(),
            'previous_reading' => 1000,
            'current_reading' => 1020,
            'consumption' => 20,
            'cost' => 3000,
            'status' => 'approved',
            'is_invoiced' => false,
        ]);

        $billingPeriod = Carbon::now()->startOfMonth();
        $invoice = $this->service->generateInvoiceForLease($this->lease, $billingPeriod);

        $this->assertEquals(3000, $invoice->water_due);
        $this->assertEquals(28000, $invoice->total_due);
    }

    public function test_calculates_water_charges_for_flat_rate_billing(): void
    {
        $this->building->update([
            'water_billing_type' => 'flat_rate',
            'water_flat_rate' => 500,
        ]);

        $billingPeriod = Carbon::now()->startOfMonth();
        $invoice = $this->service->generateInvoiceForLease($this->lease, $billingPeriod);

        $this->assertEquals(500, $invoice->water_due);
        $this->assertEquals(25500, $invoice->total_due);
    }

    public function test_water_disabled_returns_zero_charge(): void
    {
        $this->building->update([
            'water_billing_type' => null,
        ]);

        $billingPeriod = Carbon::now()->startOfMonth();
        $invoice = $this->service->generateInvoiceForLease($this->lease, $billingPeriod);

        $this->assertEquals(0, $invoice->water_due);
        $this->assertEquals(25000, $invoice->total_due);
    }

    public function test_calculates_previous_arrears(): void
    {
        Invoice::create([
            'lease_id' => $this->lease->id,
            'landlord_id' => $this->landlord->id,
            'invoice_number' => 'INV-202401-0001',
            'due_date' => now()->subMonth(),
            'billing_period_start' => now()->subMonth()->startOfMonth(),
            'rent_due' => 25000,
            'water_due' => 0,
            'arrears' => 0,
            'wallet_applied' => 0,
            'total_due' => 25000,
            'amount_paid' => 15000,
            'status' => 'partial',
        ]);

        $billingPeriod = Carbon::now()->startOfMonth();
        $invoice = $this->service->generateInvoiceForLease($this->lease, $billingPeriod);

        $this->assertEquals(10000, $invoice->arrears);
        $this->assertEquals(35000, $invoice->total_due);
    }

    public function test_marks_water_readings_as_invoiced(): void
    {
        $this->building->update([
            'water_billing_type' => 'consumption',
        ]);

        $reading = WaterReading::create([
            'unit_id' => $this->unit->id,
            'landlord_id' => $this->landlord->id,
            'reading_date' => now(),
            'previous_reading' => 1000,
            'current_reading' => 1020,
            'consumption' => 20,
            'cost' => 3000,
            'status' => 'approved',
            'is_invoiced' => false,
        ]);

        $billingPeriod = Carbon::now()->startOfMonth();
        $this->service->generateInvoiceForLease($this->lease, $billingPeriod);

        $reading->refresh();
        $this->assertTrue($reading->is_invoiced);
    }

    public function test_does_not_include_pending_readings(): void
    {
        $this->building->update([
            'water_billing_type' => 'consumption',
        ]);

        WaterReading::create([
            'unit_id' => $this->unit->id,
            'landlord_id' => $this->landlord->id,
            'reading_date' => now(),
            'previous_reading' => 1000,
            'current_reading' => 1020,
            'consumption' => 20,
            'cost' => 3000,
            'status' => 'pending',
            'is_invoiced' => false,
        ]);

        $billingPeriod = Carbon::now()->startOfMonth();
        $invoice = $this->service->generateInvoiceForLease($this->lease, $billingPeriod);

        $this->assertEquals(0, $invoice->water_due);
    }

    public function test_applies_wallet_balance_to_invoice(): void
    {
        $this->lease->update(['wallet_balance' => 5000]);

        $billingPeriod = Carbon::now()->startOfMonth();
        $invoice = $this->service->generateInvoiceForLease($this->lease, $billingPeriod);

        $this->assertEquals(5000, $invoice->wallet_applied);
        $this->assertEquals(20000, $invoice->total_due);

        $this->lease->refresh();
        $this->assertEquals(0, $this->lease->wallet_balance);
    }

    public function test_wallet_balance_exceeding_total_marks_invoice_paid(): void
    {
        $this->lease->update(['wallet_balance' => 30000]);

        $billingPeriod = Carbon::now()->startOfMonth();
        $invoice = $this->service->generateInvoiceForLease($this->lease, $billingPeriod);

        $this->assertEquals('paid', $invoice->status);
        $this->assertEquals(0, $invoice->total_due);

        $this->lease->refresh();
        $this->assertEquals(5000, $this->lease->wallet_balance);
    }

    public function test_invoice_without_arrears_for_new_lease(): void
    {
        $billingPeriod = Carbon::now()->startOfMonth();
        $invoice = $this->service->generateInvoiceForLease($this->lease, $billingPeriod);

        $this->assertEquals(0, $invoice->arrears);
        $this->assertEquals(25000, $invoice->total_due);
    }

    public function test_due_date_is_fifth_of_next_month(): void
    {
        $billingPeriod = Carbon::create(2024, 6, 1);
        $invoice = $this->service->generateInvoiceForLease($this->lease, $billingPeriod);

        $expectedDueDate = Carbon::create(2024, 7, 6);
        $this->assertEquals($expectedDueDate->toDateString(), $invoice->due_date->toDateString());
    }
}
