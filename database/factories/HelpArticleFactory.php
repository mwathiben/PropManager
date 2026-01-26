<?php

namespace Database\Factories;

use App\Models\HelpArticle;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class HelpArticleFactory extends Factory
{
    protected $model = HelpArticle::class;

    public function definition(): array
    {
        $title = fake()->sentence(4);

        return [
            'title' => $title,
            'slug' => Str::slug($title).'-'.fake()->unique()->numberBetween(1, 99999),
            'content' => fake()->paragraphs(3, true),
            'category' => fake()->randomElement(['getting-started', 'billing', 'tenants', 'properties', 'support']),
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

    public function gettingStarted(): static
    {
        return $this->state([
            'category' => 'getting-started',
            'title' => fake()->randomElement([
                'Getting Started with PropManager',
                'Setting Up Your First Property',
                'Adding Tenants to Your Property',
                'Understanding the Dashboard',
            ]),
        ]);
    }

    public function billing(): static
    {
        return $this->state([
            'category' => 'billing',
            'title' => fake()->randomElement([
                'Understanding Invoice Generation',
                'Setting Up Payment Methods',
                'Managing Late Fees',
                'Processing Refunds',
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
