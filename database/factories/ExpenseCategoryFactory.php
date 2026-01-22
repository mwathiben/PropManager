<?php

namespace Database\Factories;

use App\Models\ExpenseCategory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ExpenseCategoryFactory extends Factory
{
    protected $model = ExpenseCategory::class;

    public function definition(): array
    {
        $categories = [
            'Maintenance' => '#3B82F6',
            'Utilities' => '#10B981',
            'Repairs' => '#F59E0B',
            'Security' => '#EF4444',
            'Cleaning' => '#8B5CF6',
            'Insurance' => '#06B6D4',
            'Legal' => '#6366F1',
            'Marketing' => '#EC4899',
            'Administrative' => '#84CC16',
            'Other' => '#6B7280',
        ];

        $name = fake()->randomElement(array_keys($categories));

        return [
            'landlord_id' => User::factory()->state(['role' => 'landlord']),
            'name' => $name,
            'description' => fake()->optional(0.5)->sentence(),
            'color' => $categories[$name],
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }

    public function forLandlord(User $landlord): static
    {
        return $this->state(['landlord_id' => $landlord->id]);
    }
}
