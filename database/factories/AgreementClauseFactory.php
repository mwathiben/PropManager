<?php

namespace Database\Factories;

use App\Models\AgreementClause;
use App\Models\Clause;
use App\Models\ManagementAgreement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AgreementClause>
 */
class AgreementClauseFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'management_agreement_id' => ManagementAgreement::factory(),
            'clause_id' => Clause::factory(),
            'params' => [],
            'position' => 0,
        ];
    }
}
