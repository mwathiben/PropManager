<?php

namespace Database\Factories;

use App\Models\MoveOut;
use App\Models\MoveOutInspectionItem;
use App\Models\MoveOutInspectionResult;
use Illuminate\Database\Eloquent\Factories\Factory;

class MoveOutInspectionResultFactory extends Factory
{
    protected $model = MoveOutInspectionResult::class;

    public function definition(): array
    {
        $result = fake()->randomElement(['pass', 'fail', 'na']);

        return [
            'move_out_id' => MoveOut::factory(),
            'inspection_item_id' => MoveOutInspectionItem::factory(),
            'result' => $result,
            'notes' => $result === 'fail' ? fake()->sentence() : null,
        ];
    }

    public function pass(): static
    {
        return $this->state([
            'result' => 'pass',
            'notes' => null,
        ]);
    }

    public function fail(): static
    {
        return $this->state([
            'result' => 'fail',
            'notes' => fake()->sentence(),
        ]);
    }

    public function notApplicable(): static
    {
        return $this->state([
            'result' => 'na',
            'notes' => fake()->optional(0.3)->sentence(),
        ]);
    }

    public function withNotes(string $notes): static
    {
        return $this->state([
            'notes' => $notes,
        ]);
    }

    public function forMoveOut(MoveOut $moveOut): static
    {
        return $this->state([
            'move_out_id' => $moveOut->id,
        ]);
    }

    public function forItem(MoveOutInspectionItem $item): static
    {
        return $this->state([
            'inspection_item_id' => $item->id,
        ]);
    }
}
