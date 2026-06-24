<?php

namespace Database\Factories;

use App\Enums\ClauseBinding;
use App\Enums\ClauseType;
use App\Models\Clause;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Clause>
 */
class ClauseFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'key' => 'mgmt.'.fake()->unique()->slug(2),
            'type' => ClauseType::Management,
            'binding' => ClauseBinding::Notice,
            'title' => fake()->sentence(3),
            'explanation' => fake()->sentence(),
            'body_template' => 'Either party may end this agreement on {notice_days} days written notice.',
            'params_schema' => [],
            'is_exclusive' => true,
            'jurisdiction' => 'KE',
            'version' => 'draft-2026-06',
            'is_active' => true,
            'needs_legal_review' => true,
        ];
    }

    /** The fee clause — the one bound to PropertyOwner.management_fee_*. */
    public function managementFee(): static
    {
        return $this->state(fn (): array => [
            'key' => 'mgmt.fee.'.fake()->unique()->numerify('###'),
            'binding' => ClauseBinding::ManagementFee,
            'title' => 'Management fee',
            'explanation' => 'What the manager charges to run the owner\'s properties.',
            'body_template' => 'The Manager shall earn a management fee of {fee_description} on the Owner portfolio each period.',
            'params_schema' => [
                ['name' => 'type', 'options' => ['percentage', 'flat']],
                ['name' => 'value'],
                ['name' => 'base', 'options' => ['collected', 'billed', 'scheduled']],
                ['name' => 'flat_cadence', 'options' => ['per_period', 'per_unit']],
            ],
            'is_exclusive' => true,
        ]);
    }
}
