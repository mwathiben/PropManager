<?php

namespace Database\Factories;

use App\Enums\AgreementStatus;
use App\Models\ManagementAgreement;
use App\Models\PropertyOwner;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ManagementAgreement>
 */
class ManagementAgreementFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'landlord_id' => User::factory()->state(['role' => 'manager']),
            'property_owner_id' => PropertyOwner::factory(),
            'status' => AgreementStatus::Draft,
            'title' => 'Management agreement',
        ];
    }
}
