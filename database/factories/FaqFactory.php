<?php

namespace Database\Factories;

use App\Models\Faq;
use Illuminate\Database\Eloquent\Factories\Factory;

class FaqFactory extends Factory
{
    protected $model = Faq::class;

    public function definition(): array
    {
        return [
            'question' => fake()->sentence().'?',
            'answer' => fake()->paragraphs(2, true),
            'category' => fake()->randomElement(['general', 'billing', 'tenants', 'properties', 'technical']),
            'roles' => null,
            'order' => fake()->numberBetween(1, 100),
            'is_published' => true,
        ];
    }

    public function published(): static
    {
        return $this->state(['is_published' => true]);
    }

    public function draft(): static
    {
        return $this->state(['is_published' => false]);
    }

    public function forRole(string $role): static
    {
        return $this->state(['roles' => [$role]]);
    }

    public function forRoles(array $roles): static
    {
        return $this->state(['roles' => $roles]);
    }

    public function forAllRoles(): static
    {
        return $this->state(['roles' => null]);
    }

    public function inCategory(string $category): static
    {
        return $this->state(['category' => $category]);
    }

    public function general(): static
    {
        return $this->state([
            'category' => 'general',
            'question' => fake()->randomElement([
                'What is PropManager?',
                'How do I get started?',
                'What payment methods are accepted?',
                'How do I contact support?',
            ]),
        ]);
    }

    public function billing(): static
    {
        return $this->state([
            'category' => 'billing',
            'question' => fake()->randomElement([
                'How do I pay my rent?',
                'When is rent due?',
                'How are late fees calculated?',
                'Can I get a payment plan?',
            ]),
        ]);
    }

    public function tenantFacing(): static
    {
        return $this->state([
            'roles' => ['tenant'],
            'category' => 'tenants',
        ]);
    }

    public function landlordFacing(): static
    {
        return $this->state([
            'roles' => ['landlord', 'caretaker'],
        ]);
    }
}
