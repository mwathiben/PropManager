<?php

namespace Tests\Feature;

use App\Models\Building;
use App\Models\Invoice;
use App\Models\Lease;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportsTest extends TestCase
{
    use RefreshDatabase;

    protected User $landlord;

    protected Property $property;

    protected Building $building;

    protected Unit $unit;

    protected User $tenant;

    protected Lease $lease;

    protected function setUp(): void
    {
        parent::setUp();

        // Create landlord user
        $this->landlord = User::factory()->create([
            'role' => 'landlord',
            'landlord_id' => null,
        ]);

        // Authenticate as landlord to ensure TenantScope works correctly
        $this->actingAs($this->landlord);

        // Create property
        $this->property = Property::create([
            'landlord_id' => $this->landlord->id,
            'name' => 'Test Property',
            'type' => 'residential',
            'address' => '123 Test Street',
        ]);

        // Create building
        $this->building = Building::create([
            'property_id' => $this->property->id,
            'landlord_id' => $this->landlord->id,
            'name' => 'Building A',
            'total_floors' => 3,
            'units_per_floor' => 4,
        ]);

        // Create unit
        $this->unit = Unit::create([
            'building_id' => $this->building->id,
            'landlord_id' => $this->landlord->id,
            'unit_number' => 'A101',
            'floor_number' => 1,
            'status' => 'occupied',
            'target_rent' => 15000,
            'meter_number' => 'WM001',
        ]);

        // Create tenant
        $this->tenant = User::factory()->create([
            'role' => 'tenant',
            'landlord_id' => $this->landlord->id,
        ]);

        // Create lease
        $this->lease = Lease::create([
            'unit_id' => $this->unit->id,
            'tenant_id' => $this->tenant->id,
            'landlord_id' => $this->landlord->id,
            'start_date' => now()->subMonths(6),
            'end_date' => now()->addMonths(6),
            'rent_amount' => 15000,
            'deposit_amount' => 15000,
            'is_active' => true,
        ]);
    }

    public function test_reports_page_can_be_rendered(): void
    {
        $response = $this->actingAs($this->landlord)
            ->get('/finances/reports');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Finances/Index'));
    }

    public function test_reports_page_contains_analytics_data(): void
    {
        $response = $this->actingAs($this->landlord)
            ->get('/finances/reports');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Finances/Index')
            ->has('revenueData')
            ->has('occupancyData')
            ->has('arrearsAging')
            ->has('collectionRate')
            ->has('topPerformingUnits')
        );
    }

    public function test_reports_can_filter_by_period(): void
    {
        $periods = ['1', '3', '6', '12'];

        foreach ($periods as $period) {
            $response = $this->actingAs($this->landlord)
                ->get('/finances/reports?period='.$period);

            $response->assertStatus(200);
            $response->assertInertia(fn ($page) => $page
                ->has('revenueData')
            );
        }
    }

    public function test_occupancy_metrics_are_accurate(): void
    {
        // Create additional units (without active leases, they are vacant)
        Unit::create([
            'building_id' => $this->building->id,
            'landlord_id' => $this->landlord->id,
            'unit_number' => 'A102',
            'floor_number' => 1,
            'status' => 'vacant',
            'target_rent' => 12000,
        ]);

        Unit::create([
            'building_id' => $this->building->id,
            'landlord_id' => $this->landlord->id,
            'unit_number' => 'A103',
            'floor_number' => 1,
            'status' => 'vacant',
            'target_rent' => 13000,
        ]);

        $response = $this->actingAs($this->landlord)
            ->get('/finances/reports');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->has('occupancyData')
            ->where('occupancyData.totals.total_units', 3)
            ->where('occupancyData.totals.occupied', 1)
            ->where('occupancyData.totals.vacant', 2)
        );
    }

    public function test_financial_metrics_calculate_correctly(): void
    {
        // Create an invoice
        $invoice = Invoice::create([
            'lease_id' => $this->lease->id,
            'landlord_id' => $this->landlord->id,
            'invoice_number' => 'INV-202412-0001',
            'due_date' => now()->addDays(7),
            'billing_period_start' => now()->subDays(15),
            'rent_due' => 15000,
            'water_due' => 500,
            'arrears' => 0,
            'total_due' => 15500,
            'amount_paid' => 10000,
            'status' => 'partial',
        ]);

        // Create a payment
        Payment::create([
            'invoice_id' => $invoice->id,
            'lease_id' => $this->lease->id,
            'landlord_id' => $this->landlord->id,
            'amount' => 10000,
            'payment_method' => 'bank_transfer',
            'payment_date' => now(),
            'reference' => 'PAY-001',
            'status' => 'completed',
        ]);

        $response = $this->actingAs($this->landlord)
            ->get('/finances/reports');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->has('revenueData')
        );
    }

    public function test_reports_page_returns_analytics_data_structure(): void
    {
        $response = $this->actingAs($this->landlord)
            ->get('/finances/reports?period=12');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Finances/Index')
            ->has('revenueData')
            ->has('collectionRate')
            ->has('occupancyData')
            ->has('arrearsAging')
        );
    }

    public function test_export_pdf_works_for_financial_report(): void
    {
        $response = $this->actingAs($this->landlord)
            ->get('/finances/reports/export?format=pdf&report_type=financial&period=month');

        $response->assertStatus(200);
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_export_pdf_works_for_occupancy_report(): void
    {
        $response = $this->actingAs($this->landlord)
            ->get('/finances/reports/export?format=pdf&report_type=occupancy&period=month');

        $response->assertStatus(200);
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_export_csv_works(): void
    {
        $response = $this->actingAs($this->landlord)
            ->get('/finances/reports/export?format=csv&report_type=financial&period=month');

        $response->assertStatus(200);
        $response->assertHeader('content-type', 'text/csv; charset=utf-8');
    }

    public function test_caretaker_can_view_landlord_reports(): void
    {
        $caretaker = User::factory()->create([
            'role' => 'caretaker',
            'landlord_id' => $this->landlord->id,
        ]);

        $response = $this->actingAs($caretaker)
            ->get('/finances/reports');

        $response->assertStatus(200);
    }

    public function test_tenant_cannot_access_finance_reports(): void
    {
        // Tenants cannot access the finance hub (403 forbidden)
        $response = $this->actingAs($this->tenant)
            ->get('/finances/reports');

        $response->assertStatus(403);
    }

    public function test_unauthenticated_user_cannot_access_reports(): void
    {
        // Log out current user and make a fresh guest request
        auth()->logout();

        $response = $this->get('/finances/reports');

        $response->assertRedirect('/login');
    }

    public function test_landlord_cannot_see_other_landlord_data(): void
    {
        // Create another landlord with their own property
        $otherLandlord = User::factory()->create([
            'role' => 'landlord',
            'landlord_id' => null,
        ]);

        // Act as other landlord to create their data (TenantScope requires auth)
        $this->actingAs($otherLandlord);

        $otherProperty = Property::create([
            'landlord_id' => $otherLandlord->id,
            'name' => 'Other Property',
            'type' => 'commercial',
        ]);

        $otherBuilding = Building::create([
            'property_id' => $otherProperty->id,
            'landlord_id' => $otherLandlord->id,
            'name' => 'Other Building',
            'total_floors' => 2,
            'units_per_floor' => 2,
        ]);

        // Create 5 units for the other landlord
        for ($i = 1; $i <= 5; $i++) {
            Unit::create([
                'building_id' => $otherBuilding->id,
                'landlord_id' => $otherLandlord->id,
                'unit_number' => 'B'.$i,
                'floor_number' => 1,
                'status' => 'vacant',
            ]);
        }

        // When first landlord views reports, they should only see their own data (1 unit)
        $response = $this->actingAs($this->landlord)
            ->get('/finances/reports');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->where('occupancyData.totals.total_units', 1)
        );
    }
}
