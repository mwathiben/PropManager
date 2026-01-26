<?php

namespace Database\Factories;

use App\Models\Property;
use App\Models\User;
use App\Models\VerificationTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

class VerificationTemplateFactory extends Factory
{
    protected $model = VerificationTemplate::class;

    public function definition(): array
    {
        return [
            'landlord_id' => User::factory()->state(['role' => 'landlord']),
            'property_id' => null,
            'name' => fake()->randomElement([
                'Standard Verification',
                'Premium Tenant Verification',
                'Basic Documents Check',
                'Full Background Check',
                'Quick Verification',
            ]),
            'is_default' => false,
        ];
    }

    public function default(): static
    {
        return $this->state([
            'is_default' => true,
            'name' => 'Default Verification Template',
        ]);
    }

    public function notDefault(): static
    {
        return $this->state([
            'is_default' => false,
        ]);
    }

    public function forProperty(Property $property): static
    {
        return $this->state([
            'property_id' => $property->id,
            'landlord_id' => $property->landlord_id,
        ]);
    }

    public function forLandlord(User $landlord): static
    {
        return $this->state([
            'landlord_id' => $landlord->id,
        ]);
    }
}
