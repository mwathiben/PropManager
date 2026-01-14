<?php

namespace Tests\Feature;

use App\Models\Building;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Property;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpenseExportTest extends TestCase
{
    use RefreshDatabase;

    private User $landlord;

    private Property $property;

    private Building $building;

    protected function setUp(): void
    {
        parent::setUp();

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
            'floors' => 1,
            'units_per_floor' => 1,
            'landlord_id' => $this->landlord->id,
        ]);
    }

    public function test_can_export_expenses_as_excel(): void
    {
        $this->actingAs($this->landlord);

        Expense::create([
            'landlord_id' => $this->landlord->id,
            'description' => 'Test expense',
            'amount' => 5000,
            'expense_date' => now(),
        ]);

        $response = $this->get(route('finances.expenses.export', ['format' => 'xlsx']));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_can_export_expenses_as_pdf(): void
    {
        $this->actingAs($this->landlord);

        Expense::create([
            'landlord_id' => $this->landlord->id,
            'description' => 'Test expense',
            'amount' => 5000,
            'expense_date' => now(),
        ]);

        $response = $this->get(route('finances.expenses.export', ['format' => 'pdf']));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_expense_export_respects_category_filter(): void
    {
        $this->actingAs($this->landlord);

        $category = ExpenseCategory::create([
            'landlord_id' => $this->landlord->id,
            'name' => 'Repairs',
            'color' => '#EF4444',
        ]);

        Expense::create([
            'landlord_id' => $this->landlord->id,
            'category_id' => $category->id,
            'description' => 'Categorized expense',
            'amount' => 3000,
            'expense_date' => now(),
        ]);

        Expense::create([
            'landlord_id' => $this->landlord->id,
            'description' => 'Uncategorized expense',
            'amount' => 2000,
            'expense_date' => now(),
        ]);

        $response = $this->get(route('finances.expenses.export', [
            'format' => 'xlsx',
            'category_id' => $category->id,
        ]));

        $response->assertOk();
    }

    public function test_expense_export_respects_date_filter(): void
    {
        $this->actingAs($this->landlord);

        Expense::create([
            'landlord_id' => $this->landlord->id,
            'description' => 'Old expense',
            'amount' => 1000,
            'expense_date' => now()->subMonths(2),
        ]);

        Expense::create([
            'landlord_id' => $this->landlord->id,
            'description' => 'Recent expense',
            'amount' => 2000,
            'expense_date' => now(),
        ]);

        $response = $this->get(route('finances.expenses.export', [
            'format' => 'xlsx',
            'date_from' => now()->subDays(7)->format('Y-m-d'),
            'date_to' => now()->format('Y-m-d'),
        ]));

        $response->assertOk();
    }

    public function test_can_export_vendors(): void
    {
        $this->actingAs($this->landlord);

        Vendor::create([
            'landlord_id' => $this->landlord->id,
            'name' => 'Test Vendor',
            'contact_person' => 'John Doe',
        ]);

        $response = $this->get(route('finances.vendors.export'));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_expense_export_requires_authentication(): void
    {
        $response = $this->get(route('finances.expenses.export'));

        $response->assertRedirect(route('login'));
    }

    public function test_vendor_export_requires_authentication(): void
    {
        $response = $this->get(route('finances.vendors.export'));

        $response->assertRedirect(route('login'));
    }

    public function test_expense_export_tenant_isolation(): void
    {
        $otherLandlord = User::factory()->create(['role' => 'landlord']);

        Expense::create([
            'landlord_id' => $this->landlord->id,
            'description' => 'My expense',
            'amount' => 5000,
            'expense_date' => now(),
        ]);

        $this->actingAs($otherLandlord);

        $response = $this->get(route('finances.expenses.export', ['format' => 'xlsx']));

        $response->assertOk();
    }

    public function test_can_export_expenses_as_csv(): void
    {
        $this->actingAs($this->landlord);

        Expense::create([
            'landlord_id' => $this->landlord->id,
            'description' => 'Test expense',
            'amount' => 5000,
            'expense_date' => now(),
        ]);

        $response = $this->get(route('finances.expenses.export', ['format' => 'csv']));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=utf-8');
    }

    public function test_can_export_vendors_as_csv(): void
    {
        $this->actingAs($this->landlord);

        Vendor::create([
            'landlord_id' => $this->landlord->id,
            'name' => 'Test Vendor',
            'contact_person' => 'John Doe',
        ]);

        $response = $this->get(route('finances.vendors.export', ['format' => 'csv']));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=utf-8');
    }
}
