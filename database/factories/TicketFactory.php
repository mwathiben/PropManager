<?php

namespace Database\Factories;

use App\Models\Building;
use App\Models\Ticket;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TicketFactory extends Factory
{
    protected $model = Ticket::class;

    public function definition(): array
    {
        $category = fake()->randomElement(['issue', 'complaint']);
        $subcategories = $category === 'issue'
            ? array_keys(Ticket::issueSubcategories())
            : array_keys(Ticket::complaintSubcategories());

        return [
            'building_id' => Building::factory(),
            'landlord_id' => fn (array $attrs) => $attrs['building_id'] instanceof Building ? $attrs['building_id']->landlord_id : Building::findOrFail($attrs['building_id'])->landlord_id,
            'unit_id' => null,
            'reporter_id' => User::factory(),
            'assigned_to' => null,
            'category' => $category,
            'subcategory' => fake()->randomElement($subcategories),
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'priority' => fake()->randomElement(['low', 'medium', 'high', 'urgent']),
            'status' => 'open',
            'location' => fake()->optional(0.5)->randomElement(['bathroom', 'kitchen', 'bedroom', 'living room', 'balcony', 'common area']),
            'resolution_notes' => null,
            'resolved_at' => null,
            'closed_at' => null,
        ];
    }

    public function open(): static
    {
        return $this->state(['status' => 'open']);
    }

    public function acknowledged(): static
    {
        return $this->state(['status' => 'acknowledged']);
    }

    public function inProgress(): static
    {
        return $this->state(['status' => 'in_progress']);
    }

    public function resolved(): static
    {
        return $this->state([
            'status' => 'resolved',
            'resolution_notes' => fake()->sentence(),
            'resolved_at' => now(),
        ]);
    }

    public function closed(): static
    {
        return $this->state([
            'status' => 'closed',
            'resolution_notes' => fake()->sentence(),
            'resolved_at' => now()->subHours(2),
            'closed_at' => now(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(['status' => 'cancelled']);
    }

    public function lowPriority(): static
    {
        return $this->state(['priority' => 'low']);
    }

    public function mediumPriority(): static
    {
        return $this->state(['priority' => 'medium']);
    }

    public function highPriority(): static
    {
        return $this->state(['priority' => 'high']);
    }

    public function urgent(): static
    {
        return $this->state(['priority' => 'urgent']);
    }

    public function issue(): static
    {
        return $this->state([
            'category' => 'issue',
            'subcategory' => fake()->randomElement(array_keys(Ticket::issueSubcategories())),
        ]);
    }

    public function complaint(): static
    {
        return $this->state([
            'category' => 'complaint',
            'subcategory' => fake()->randomElement(array_keys(Ticket::complaintSubcategories())),
        ]);
    }

    public function forBuilding(Building $building): static
    {
        return $this->state([
            'building_id' => $building->id,
            'landlord_id' => $building->landlord_id,
        ]);
    }

    public function forUnit(Unit $unit): static
    {
        return $this->state([
            'unit_id' => $unit->id,
            'building_id' => $unit->building_id,
            'landlord_id' => $unit->landlord_id,
        ]);
    }

    public function reportedBy(User $user): static
    {
        return $this->state(['reporter_id' => $user->id]);
    }

    public function assignedTo(User $user): static
    {
        return $this->state(['assigned_to' => $user->id]);
    }

    public function forLandlord(User $landlord): static
    {
        return $this->state(function () use ($landlord) {
            $building = Building::factory()->create([
                'property_id' => \App\Models\Property::factory()->create(['landlord_id' => $landlord->id])->id,
            ]);

            return [
                'landlord_id' => $landlord->id,
                'building_id' => $building->id,
            ];
        });
    }
}
