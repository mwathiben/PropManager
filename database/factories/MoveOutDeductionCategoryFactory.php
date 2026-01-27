<?php

namespace Database\Factories;

use App\Models\Building;
use App\Models\MoveOutDeductionCategory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MoveOutDeductionCategory>
 */
class MoveOutDeductionCategoryFactory extends Factory
{
    protected $model = MoveOutDeductionCategory::class;

    public function definition(): array
    {
        $categories = [
            ['name' => 'Paint Works', 'amount' => 5000],
            ['name' => 'Cleaning Fee', 'amount' => 3000],
            ['name' => 'Key Replacement', 'amount' => 500],
            ['name' => 'Wall Damage Repair', 'amount' => 8000],
            ['name' => 'Floor Repair', 'amount' => 10000],
            ['name' => 'Window Replacement', 'amount' => 12000],
            ['name' => 'Electrical Repairs', 'amount' => 4000],
            ['name' => 'Plumbing Repairs', 'amount' => 5000],
        ];

        $category = fake()->randomElement($categories);

        return [
            'landlord_id' => User::factory()->state(['role' => 'landlord']),
            'building_id' => null,
            'name' => $category['name'],
            'description' => fake()->sentence(),
            'default_amount' => $category['amount'],
            'always_apply' => false,
            'is_active' => true,
            'sort_order' => fake()->numberBetween(1, 100),
        ];
    }

    public function platformDefault(): static
    {
        return $this->state([
            'landlord_id' => null,
            'building_id' => null,
        ]);
    }

    public function forLandlord(User $landlord): static
    {
        return $this->state([
            'landlord_id' => $landlord->id,
            'building_id' => null,
        ]);
    }

    public function forBuilding(Building $building): static
    {
        return $this->state([
            'building_id' => $building->id,
            'landlord_id' => $building->landlord_id,
        ]);
    }

    public function active(): static
    {
        return $this->state(['is_active' => true]);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }

    public function alwaysApply(): static
    {
        return $this->state(['always_apply' => true]);
    }

    public function optional(): static
    {
        return $this->state(['always_apply' => false]);
    }

    public function paintWorks(): static
    {
        return $this->state([
            'name' => 'Paint Works',
            'description' => 'Repainting walls and ceilings to restore original condition.',
            'default_amount' => 5000,
        ]);
    }

    public function cleaningFee(): static
    {
        return $this->state([
            'name' => 'Cleaning Fee',
            'description' => 'Professional deep cleaning of the unit.',
            'default_amount' => 3000,
            'always_apply' => true,
        ]);
    }

    public function keyReplacement(): static
    {
        return $this->state([
            'name' => 'Key Replacement',
            'description' => 'Replacement of lost or unreturned keys.',
            'default_amount' => 500,
        ]);
    }

    public function wallDamage(): static
    {
        return $this->state([
            'name' => 'Wall Damage Repair',
            'description' => 'Repair of holes, cracks, or damage to walls.',
            'default_amount' => 8000,
        ]);
    }

    public function floorRepair(): static
    {
        return $this->state([
            'name' => 'Floor Repair',
            'description' => 'Repair or replacement of damaged flooring.',
            'default_amount' => 10000,
        ]);
    }

    public function windowReplacement(): static
    {
        return $this->state([
            'name' => 'Window Replacement',
            'description' => 'Replacement of broken or damaged windows.',
            'default_amount' => 12000,
        ]);
    }

    public function electricalRepairs(): static
    {
        return $this->state([
            'name' => 'Electrical Repairs',
            'description' => 'Repair of damaged electrical outlets, switches, or fixtures.',
            'default_amount' => 4000,
        ]);
    }

    public function plumbingRepairs(): static
    {
        return $this->state([
            'name' => 'Plumbing Repairs',
            'description' => 'Repair of damaged pipes, taps, or plumbing fixtures.',
            'default_amount' => 5000,
        ]);
    }

    public function withAmount(float $amount): static
    {
        return $this->state(['default_amount' => $amount]);
    }

    public function sortOrder(int $order): static
    {
        return $this->state(['sort_order' => $order]);
    }
}
