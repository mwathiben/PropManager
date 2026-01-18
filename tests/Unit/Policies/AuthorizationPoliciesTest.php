<?php

namespace Tests\Unit\Policies;

use App\Models\Building;
use App\Models\DepositTransaction;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\LateFeePolicy;
use App\Models\Lease;
use App\Models\Property;
use App\Models\Unit;
use App\Models\User;
use App\Models\Vendor;
use App\Models\WaterSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthorizationPoliciesTest extends TestCase
{
    use RefreshDatabase;

    private User $landlord;

    private User $caretaker;

    private User $tenant;

    private User $otherLandlord;

    private Property $property;

    private Building $building;

    private Unit $unit;

    protected function setUp(): void
    {
        parent::setUp();

        $this->landlord = User::factory()->create(['role' => 'landlord']);
        $this->otherLandlord = User::factory()->create(['role' => 'landlord']);

        $this->caretaker = User::factory()->create([
            'role' => 'caretaker',
            'landlord_id' => $this->landlord->id,
        ]);

        $this->tenant = User::factory()->create([
            'role' => 'tenant',
            'landlord_id' => $this->landlord->id,
        ]);

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

        $this->unit = Unit::create([
            'building_id' => $this->building->id,
            'unit_number' => 'A1',
            'floor_number' => 1,
            'target_rent' => 10000,
            'status' => 'vacant',
            'landlord_id' => $this->landlord->id,
        ]);
    }

    public function test_expense_policy_landlord_can_manage_own_expenses(): void
    {
        $expense = Expense::create([
            'landlord_id' => $this->landlord->id,
            'description' => 'Test expense',
            'amount' => 1000,
            'expense_date' => now(),
        ]);

        $this->assertTrue($this->landlord->can('view', $expense));
        $this->assertTrue($this->landlord->can('update', $expense));
        $this->assertTrue($this->landlord->can('delete', $expense));
    }

    public function test_expense_policy_landlord_cannot_manage_other_expenses(): void
    {
        $expense = Expense::create([
            'landlord_id' => $this->otherLandlord->id,
            'description' => 'Other landlord expense',
            'amount' => 1000,
            'expense_date' => now(),
        ]);

        $this->assertFalse($this->landlord->can('view', $expense));
        $this->assertFalse($this->landlord->can('update', $expense));
        $this->assertFalse($this->landlord->can('delete', $expense));
    }

    public function test_expense_policy_caretaker_can_view_landlords_expenses(): void
    {
        $expense = Expense::create([
            'landlord_id' => $this->landlord->id,
            'description' => 'Test expense',
            'amount' => 1000,
            'expense_date' => now(),
        ]);

        $this->assertTrue($this->caretaker->can('view', $expense));
        $this->assertTrue($this->caretaker->can('update', $expense));
    }

    public function test_expense_policy_tenant_cannot_access_expenses(): void
    {
        $expense = Expense::create([
            'landlord_id' => $this->landlord->id,
            'description' => 'Test expense',
            'amount' => 1000,
            'expense_date' => now(),
        ]);

        $this->assertFalse($this->tenant->can('viewAny', Expense::class));
        $this->assertFalse($this->tenant->can('view', $expense));
    }

    public function test_vendor_policy_landlord_can_manage_own_vendors(): void
    {
        $vendor = Vendor::create([
            'landlord_id' => $this->landlord->id,
            'name' => 'Test Vendor',
        ]);

        $this->assertTrue($this->landlord->can('view', $vendor));
        $this->assertTrue($this->landlord->can('update', $vendor));
        $this->assertTrue($this->landlord->can('delete', $vendor));
    }

    public function test_vendor_policy_caretaker_can_view_and_update(): void
    {
        $vendor = Vendor::create([
            'landlord_id' => $this->landlord->id,
            'name' => 'Test Vendor',
        ]);

        $this->assertTrue($this->caretaker->can('view', $vendor));
        $this->assertTrue($this->caretaker->can('update', $vendor));
        $this->assertFalse($this->caretaker->can('delete', $vendor));
    }

    public function test_expense_category_policy_landlord_can_manage(): void
    {
        $category = ExpenseCategory::create([
            'landlord_id' => $this->landlord->id,
            'name' => 'Test Category',
        ]);

        $this->assertTrue($this->landlord->can('view', $category));
        $this->assertTrue($this->landlord->can('update', $category));
        $this->assertTrue($this->landlord->can('delete', $category));
    }

    public function test_expense_category_policy_caretaker_can_only_view(): void
    {
        $category = ExpenseCategory::create([
            'landlord_id' => $this->landlord->id,
            'name' => 'Test Category',
        ]);

        $this->assertTrue($this->caretaker->can('view', $category));
        $this->assertFalse($this->caretaker->can('create', ExpenseCategory::class));
    }

    public function test_late_fee_rule_policy_landlord_can_manage(): void
    {
        $policy = LateFeePolicy::create([
            'landlord_id' => $this->landlord->id,
            'name' => 'Default Late Fee',
            'grace_period_days' => 5,
            'fee_type' => 'percentage',
            'fee_percentage' => 5,
            'is_active' => true,
        ]);

        $this->assertTrue($this->landlord->can('view', $policy));
        $this->assertTrue($this->landlord->can('update', $policy));
        $this->assertTrue($this->landlord->can('delete', $policy));
    }

    public function test_late_fee_rule_policy_caretaker_can_only_view(): void
    {
        $policy = LateFeePolicy::create([
            'landlord_id' => $this->landlord->id,
            'name' => 'Default Late Fee',
            'grace_period_days' => 5,
            'fee_type' => 'percentage',
            'fee_percentage' => 5,
            'is_active' => true,
        ]);

        $this->assertTrue($this->caretaker->can('view', $policy));
        $this->assertFalse($this->caretaker->can('create', LateFeePolicy::class));
        $this->assertFalse($this->caretaker->can('update', $policy));
    }

    public function test_water_setting_policy_landlord_can_manage(): void
    {
        $setting = WaterSetting::create([
            'landlord_id' => $this->landlord->id,
            'rate_per_unit' => 150,
            'billing_day' => 1,
            'is_enabled' => true,
        ]);

        $this->assertTrue($this->landlord->can('view', $setting));
        $this->assertTrue($this->landlord->can('update', $setting));
    }

    public function test_water_setting_policy_caretaker_can_only_view(): void
    {
        $setting = WaterSetting::create([
            'landlord_id' => $this->landlord->id,
            'rate_per_unit' => 150,
            'billing_day' => 1,
            'is_enabled' => true,
        ]);

        $this->assertTrue($this->caretaker->can('view', $setting));
        $this->assertFalse($this->caretaker->can('update', $setting));
    }

    public function test_deposit_transaction_policy_landlord_can_manage(): void
    {
        $lease = Lease::create([
            'unit_id' => $this->unit->id,
            'tenant_id' => $this->tenant->id,
            'landlord_id' => $this->landlord->id,
            'start_date' => now(),
            'rent_amount' => 10000,
            'deposit_amount' => 20000,
            'is_active' => true,
        ]);

        $transaction = DepositTransaction::create([
            'lease_id' => $lease->id,
            'landlord_id' => $this->landlord->id,
            'processed_by' => $this->landlord->id,
            'type' => DepositTransaction::TYPE_RECEIVED,
            'amount' => 10000,
            'balance_after' => 10000,
            'reason' => 'Initial deposit',
        ]);

        $this->assertTrue($this->landlord->can('view', $transaction));
        $this->assertTrue($this->landlord->can('update', $transaction));
    }

    public function test_deposit_transaction_policy_tenant_can_view_own(): void
    {
        $lease = Lease::create([
            'unit_id' => $this->unit->id,
            'tenant_id' => $this->tenant->id,
            'landlord_id' => $this->landlord->id,
            'start_date' => now(),
            'rent_amount' => 10000,
            'deposit_amount' => 20000,
            'is_active' => true,
        ]);

        $transaction = DepositTransaction::create([
            'lease_id' => $lease->id,
            'landlord_id' => $this->landlord->id,
            'processed_by' => $this->landlord->id,
            'type' => DepositTransaction::TYPE_RECEIVED,
            'amount' => 10000,
            'balance_after' => 10000,
            'reason' => 'Initial deposit',
        ]);

        $this->assertTrue($this->tenant->can('view', $transaction));
        $this->assertFalse($this->tenant->can('update', $transaction));
    }
}
