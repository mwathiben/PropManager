<?php

namespace Database\Factories;

use App\Models\Ticket;
use App\Models\TicketFeedback;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TicketFeedbackFactory extends Factory
{
    protected $model = TicketFeedback::class;

    public function definition(): array
    {
        $ticket = Ticket::factory()->resolved()->create();

        return [
            'ticket_id' => $ticket->id,
            'user_id' => $ticket->reporter_id ?? User::factory()->state(['role' => 'tenant']),
            'rating' => fake()->numberBetween(1, 5),
            'comments' => fake()->optional(0.7)->sentence(),
        ];
    }

    public function excellent(): static
    {
        return $this->state([
            'rating' => 5,
            'comments' => fake()->optional(0.8)->randomElement([
                'Excellent service, very satisfied!',
                'Quick resolution, thank you!',
                'Great work by the team.',
            ]),
        ]);
    }

    public function good(): static
    {
        return $this->state([
            'rating' => 4,
            'comments' => fake()->optional(0.6)->randomElement([
                'Good service overall.',
                'Issue resolved satisfactorily.',
                'Happy with the outcome.',
            ]),
        ]);
    }

    public function average(): static
    {
        return $this->state([
            'rating' => 3,
            'comments' => fake()->optional(0.5)->randomElement([
                'Acceptable resolution.',
                'Could have been faster.',
                'Average service.',
            ]),
        ]);
    }

    public function poor(): static
    {
        return $this->state([
            'rating' => 2,
            'comments' => fake()->optional(0.9)->randomElement([
                'Not fully satisfied with the resolution.',
                'Took too long to address.',
                'Expected better service.',
            ]),
        ]);
    }

    public function veryPoor(): static
    {
        return $this->state([
            'rating' => 1,
            'comments' => fake()->optional(0.95)->randomElement([
                'Very disappointed with the service.',
                'Issue not properly resolved.',
                'Poor communication throughout.',
            ]),
        ]);
    }

    public function forTicket(Ticket $ticket): static
    {
        return $this->state([
            'ticket_id' => $ticket->id,
            'user_id' => $ticket->reporter_id ?? User::factory()->state(['role' => 'tenant']),
        ]);
    }

    public function byUser(User $user): static
    {
        return $this->state(['user_id' => $user->id]);
    }
}
