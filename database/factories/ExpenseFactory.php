<?php

namespace Database\Factories;

use App\Models\Building;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Property;
use App\Models\Unit;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\Factory;

class ExpenseFactory extends Factory
{
    protected $model = Expense::class;

    public function definition(): array
    {
        $landlord = User::factory()->state(['role' => 'landlord'])->create();

        return [
            'landlord_id' => $landlord->id,
            'category_id' => ExpenseCategory::factory()->forLandlord($landlord),
            'vendor_id' => Vendor::factory()->forLandlord($landlord),
            'property_id' => null,
            'building_id' => null,
            'unit_id' => null,
            'description' => fake()->sentence(),
            'amount' => fake()->numberBetween(1000, 50000),
            'expense_date' => fake()->dateTimeBetween('-3 months', 'now'),
            'payment_method' => fake()->randomElement(['cash', 'bank_transfer', 'mobile_money', 'cheque']),
            'reference' => 'EXP-'.strtoupper(fake()->unique()->bothify('??######')),
            'receipt_path' => null,
            'notes' => fake()->optional(0.3)->sentence(),
            'is_recurring' => false,
            'recurring_frequency' => null,
        ];
    }

    public function recurring(string $frequency = 'monthly'): static
    {
        return $this->state([
            'is_recurring' => true,
            'recurring_frequency' => $frequency,
        ]);
    }

    public function forProperty(Property $property): static
    {
        return $this->state([
            'property_id' => $property->id,
            'landlord_id' => $property->landlord_id,
            'building_id' => null,
            'unit_id' => null,
        ]);
    }

    public function forBuilding(Building $building): static
    {
        return $this->state([
            'building_id' => $building->id,
            'property_id' => $building->property_id,
            'landlord_id' => $building->landlord_id,
            'unit_id' => null,
        ]);
    }

    public function forUnit(Unit $unit): static
    {
        return $this->state([
            'unit_id' => $unit->id,
            'building_id' => $unit->building_id,
            'property_id' => $unit->building->property_id,
            'landlord_id' => $unit->landlord_id,
        ]);
    }

    public function forLandlord(User $landlord): static
    {
        return $this->state(['landlord_id' => $landlord->id]);
    }

    public function withCategory(ExpenseCategory $category): static
    {
        return $this->state([
            'category_id' => $category->id,
            'landlord_id' => $category->landlord_id,
        ]);
    }

    public function withVendor(Vendor $vendor): static
    {
        return $this->state([
            'vendor_id' => $vendor->id,
            'landlord_id' => $vendor->landlord_id,
        ]);
    }
}
