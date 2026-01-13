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

class ExpenseManagementTest extends TestCase
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

    public function test_landlord_can_view_expenses_tab(): void
    {
        $this->actingAs($this->landlord);

        $response = $this->get(route('finances.expenses'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Finances/Index')
            ->has('expenses')
            ->has('categories')
            ->has('vendors')
            ->has('stats')
        );
    }

    public function test_landlord_can_create_expense(): void
    {
        $this->actingAs($this->landlord);

        $response = $this->post(route('finances.expenses.store'), [
            'description' => 'Plumbing repair',
            'amount' => 5000,
            'expense_date' => now()->format('Y-m-d'),
            'payment_method' => 'cash',
            'building_id' => $this->building->id,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('expenses', [
            'landlord_id' => $this->landlord->id,
            'description' => 'Plumbing repair',
            'amount' => 5000,
        ]);
    }

    public function test_landlord_can_update_expense(): void
    {
        $this->actingAs($this->landlord);

        $expense = Expense::create([
            'landlord_id' => $this->landlord->id,
            'description' => 'Original expense',
            'amount' => 1000,
            'expense_date' => now(),
        ]);

        $response = $this->put(route('finances.expenses.update', $expense), [
            'description' => 'Updated expense',
            'amount' => 1500,
            'expense_date' => now()->format('Y-m-d'),
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $expense->refresh();
        $this->assertEquals('Updated expense', $expense->description);
        $this->assertEquals(1500, $expense->amount);
    }

    public function test_landlord_can_delete_expense(): void
    {
        $this->actingAs($this->landlord);

        $expense = Expense::create([
            'landlord_id' => $this->landlord->id,
            'description' => 'Test expense',
            'amount' => 1000,
            'expense_date' => now(),
        ]);

        $response = $this->delete(route('finances.expenses.destroy', $expense));

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertDatabaseMissing('expenses', ['id' => $expense->id]);
    }

    public function test_landlord_can_create_expense_category(): void
    {
        $this->actingAs($this->landlord);

        $response = $this->post(route('finances.expense-categories.store'), [
            'name' => 'Maintenance',
            'description' => 'Regular maintenance costs',
            'color' => '#10B981',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('expense_categories', [
            'landlord_id' => $this->landlord->id,
            'name' => 'Maintenance',
            'color' => '#10B981',
        ]);
    }

    public function test_landlord_can_update_expense_category(): void
    {
        $this->actingAs($this->landlord);

        $category = ExpenseCategory::create([
            'landlord_id' => $this->landlord->id,
            'name' => 'Original',
            'color' => '#6B7280',
        ]);

        $response = $this->put(route('finances.expense-categories.update', $category), [
            'name' => 'Updated',
            'color' => '#EF4444',
        ]);

        $response->assertRedirect();

        $category->refresh();
        $this->assertEquals('Updated', $category->name);
        $this->assertEquals('#EF4444', $category->color);
    }

    public function test_cannot_delete_category_with_expenses(): void
    {
        $this->actingAs($this->landlord);

        $category = ExpenseCategory::create([
            'landlord_id' => $this->landlord->id,
            'name' => 'Test Category',
            'color' => '#6B7280',
        ]);

        Expense::create([
            'landlord_id' => $this->landlord->id,
            'category_id' => $category->id,
            'description' => 'Test expense',
            'amount' => 1000,
            'expense_date' => now(),
        ]);

        $response = $this->delete(route('finances.expense-categories.destroy', $category));

        $response->assertSessionHasErrors();
        $this->assertDatabaseHas('expense_categories', ['id' => $category->id]);
    }

    public function test_landlord_can_create_vendor(): void
    {
        $this->actingAs($this->landlord);

        $response = $this->post(route('finances.vendors.store'), [
            'name' => 'ABC Plumbers',
            'contact_person' => 'John Doe',
            'email' => 'john@abcplumbers.com',
            'phone' => '0712345678',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('vendors', [
            'landlord_id' => $this->landlord->id,
            'name' => 'ABC Plumbers',
            'contact_person' => 'John Doe',
        ]);
    }

    public function test_landlord_can_update_vendor(): void
    {
        $this->actingAs($this->landlord);

        $vendor = Vendor::create([
            'landlord_id' => $this->landlord->id,
            'name' => 'Original Vendor',
        ]);

        $response = $this->put(route('finances.vendors.update', $vendor), [
            'name' => 'Updated Vendor',
            'phone' => '0798765432',
        ]);

        $response->assertRedirect();

        $vendor->refresh();
        $this->assertEquals('Updated Vendor', $vendor->name);
        $this->assertEquals('0798765432', $vendor->phone);
    }

    public function test_cannot_delete_vendor_with_expenses(): void
    {
        $this->actingAs($this->landlord);

        $vendor = Vendor::create([
            'landlord_id' => $this->landlord->id,
            'name' => 'Test Vendor',
        ]);

        Expense::create([
            'landlord_id' => $this->landlord->id,
            'vendor_id' => $vendor->id,
            'description' => 'Test expense',
            'amount' => 1000,
            'expense_date' => now(),
        ]);

        $response = $this->delete(route('finances.vendors.destroy', $vendor));

        $response->assertSessionHasErrors();
        $this->assertDatabaseHas('vendors', ['id' => $vendor->id]);
    }

    public function test_expense_linked_to_category_and_vendor(): void
    {
        $this->actingAs($this->landlord);

        $category = ExpenseCategory::create([
            'landlord_id' => $this->landlord->id,
            'name' => 'Repairs',
            'color' => '#EF4444',
        ]);

        $vendor = Vendor::create([
            'landlord_id' => $this->landlord->id,
            'name' => 'Test Vendor',
        ]);

        $response = $this->post(route('finances.expenses.store'), [
            'description' => 'Full repair',
            'amount' => 10000,
            'expense_date' => now()->format('Y-m-d'),
            'category_id' => $category->id,
            'vendor_id' => $vendor->id,
        ]);

        $response->assertRedirect();

        $expense = Expense::where('description', 'Full repair')->first();
        $this->assertEquals($category->id, $expense->category_id);
        $this->assertEquals($vendor->id, $expense->vendor_id);
    }

    public function test_tenant_isolation_for_expenses(): void
    {
        $otherLandlord = User::factory()->create(['role' => 'landlord']);

        $expense = Expense::create([
            'landlord_id' => $this->landlord->id,
            'description' => 'Private expense',
            'amount' => 5000,
            'expense_date' => now(),
        ]);

        $this->actingAs($otherLandlord);

        $response = $this->put(route('finances.expenses.update', $expense), [
            'description' => 'Hacked',
            'amount' => 1,
            'expense_date' => now()->format('Y-m-d'),
        ]);

        $response->assertStatus(403);

        $expense->refresh();
        $this->assertEquals('Private expense', $expense->description);
    }

    public function test_expense_stats_calculated_correctly(): void
    {
        $this->actingAs($this->landlord);

        Expense::create([
            'landlord_id' => $this->landlord->id,
            'description' => 'Expense 1',
            'amount' => 5000,
            'expense_date' => now(),
        ]);

        Expense::create([
            'landlord_id' => $this->landlord->id,
            'description' => 'Expense 2',
            'amount' => 3000,
            'expense_date' => now(),
        ]);

        $response = $this->get(route('finances.expenses'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('stats.this_month', 8000)
        );
    }

    public function test_expense_requires_authentication(): void
    {
        $response = $this->get(route('finances.expenses'));

        $response->assertRedirect(route('login'));
    }
}
