<?php

namespace Database\Factories;

use App\Models\Ticket;
use App\Models\TicketCost;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TicketCost>
 */
class TicketCostFactory extends Factory
{
    protected $model = TicketCost::class;

    public function definition(): array
    {
        return [
            'ticket_id' => Ticket::factory(),
            'category' => fake()->randomElement(TicketCost::CATEGORIES),
            'amount_cents' => fake()->numberBetween(1000, 100000),
            'currency' => 'KES',
            'recorded_at' => now(),
        ];
    }
}
