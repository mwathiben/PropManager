<?php

namespace Database\Factories;

use App\Models\LegalDocument;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class LegalDocumentFactory extends Factory
{
    protected $model = LegalDocument::class;

    public function definition(): array
    {
        $type = fake()->randomElement([
            LegalDocument::TYPE_TERMS,
            LegalDocument::TYPE_PRIVACY,
            LegalDocument::TYPE_COOKIES,
            LegalDocument::TYPE_DPA,
        ]);

        return [
            'type' => $type,
            'version' => '1.0.0',
            'title' => $this->getTitleForType($type),
            'content' => fake()->paragraphs(10, true),
            'summary' => fake()->optional(0.7)->paragraph(),
            'is_active' => true,
            'effective_date' => now(),
            'created_by' => null,
        ];
    }

    private function getTitleForType(string $type): string
    {
        return match ($type) {
            LegalDocument::TYPE_TERMS => 'Terms of Service',
            LegalDocument::TYPE_PRIVACY => 'Privacy Policy',
            LegalDocument::TYPE_COOKIES => 'Cookie Policy',
            LegalDocument::TYPE_DPA => 'Data Processing Agreement',
            default => ucfirst($type).' Policy',
        };
    }

    public function active(): static
    {
        return $this->state([
            'is_active' => true,
            'effective_date' => now()->subDay(),
        ]);
    }

    public function inactive(): static
    {
        return $this->state([
            'is_active' => false,
        ]);
    }

    public function terms(): static
    {
        return $this->state([
            'type' => LegalDocument::TYPE_TERMS,
            'title' => 'Terms of Service',
        ]);
    }

    public function privacy(): static
    {
        return $this->state([
            'type' => LegalDocument::TYPE_PRIVACY,
            'title' => 'Privacy Policy',
        ]);
    }

    public function cookies(): static
    {
        return $this->state([
            'type' => LegalDocument::TYPE_COOKIES,
            'title' => 'Cookie Policy',
        ]);
    }

    public function dpa(): static
    {
        return $this->state([
            'type' => LegalDocument::TYPE_DPA,
            'title' => 'Data Processing Agreement',
        ]);
    }

    public function futureEffective(): static
    {
        return $this->state([
            'effective_date' => now()->addWeek(),
        ]);
    }

    public function version(string $version): static
    {
        return $this->state([
            'version' => $version,
        ]);
    }

    public function createdBy(User $user): static
    {
        return $this->state([
            'created_by' => $user->id,
        ]);
    }
}
