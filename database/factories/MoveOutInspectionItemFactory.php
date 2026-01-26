<?php

namespace Database\Factories;

use App\Models\MoveOutInspectionItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class MoveOutInspectionItemFactory extends Factory
{
    protected $model = MoveOutInspectionItem::class;

    public function definition(): array
    {
        $category = fake()->randomElement(['living_room', 'bedroom', 'kitchen', 'bathroom', 'general']);

        return [
            'landlord_id' => User::factory()->state(['role' => 'landlord']),
            'name' => $this->getNameForCategory($category),
            'category' => $category,
            'is_active' => true,
            'sort_order' => fake()->numberBetween(1, 100),
        ];
    }

    private function getNameForCategory(string $category): string
    {
        $items = [
            'living_room' => ['Walls', 'Floors', 'Windows', 'Curtain Rods', 'Light Fixtures'],
            'bedroom' => ['Walls', 'Floors', 'Closet Doors', 'Windows', 'Light Fixtures'],
            'kitchen' => ['Cabinets', 'Countertops', 'Sink', 'Stove/Oven', 'Exhaust Fan'],
            'bathroom' => ['Toilet', 'Shower/Tub', 'Sink', 'Mirror', 'Tiles'],
            'general' => ['Front Door', 'Keys', 'Electrical Outlets', 'Paint', 'Cleanliness'],
        ];

        return fake()->randomElement($items[$category] ?? ['General Item']);
    }

    public function active(): static
    {
        return $this->state([
            'is_active' => true,
        ]);
    }

    public function inactive(): static
    {
        return $this->state([
            'is_active' => false,
        ]);
    }

    public function livingRoom(): static
    {
        return $this->state([
            'category' => 'living_room',
            'name' => fake()->randomElement(['Walls', 'Floors', 'Windows', 'Curtain Rods', 'Light Fixtures']),
        ]);
    }

    public function bedroom(): static
    {
        return $this->state([
            'category' => 'bedroom',
            'name' => fake()->randomElement(['Walls', 'Floors', 'Closet Doors', 'Windows', 'Light Fixtures']),
        ]);
    }

    public function kitchen(): static
    {
        return $this->state([
            'category' => 'kitchen',
            'name' => fake()->randomElement(['Cabinets', 'Countertops', 'Sink', 'Stove/Oven', 'Exhaust Fan']),
        ]);
    }

    public function bathroom(): static
    {
        return $this->state([
            'category' => 'bathroom',
            'name' => fake()->randomElement(['Toilet', 'Shower/Tub', 'Sink', 'Mirror', 'Tiles']),
        ]);
    }

    public function general(): static
    {
        return $this->state([
            'category' => 'general',
            'name' => fake()->randomElement(['Front Door', 'Keys', 'Electrical Outlets', 'Paint', 'Cleanliness']),
        ]);
    }

    public function sortOrder(int $order): static
    {
        return $this->state([
            'sort_order' => $order,
        ]);
    }

    public function forLandlord(User $landlord): static
    {
        return $this->state([
            'landlord_id' => $landlord->id,
        ]);
    }
}
