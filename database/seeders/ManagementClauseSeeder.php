<?php

namespace Database\Seeders;

use App\Enums\ClauseBinding;
use App\Enums\ClauseType;
use App\Models\Clause;
use Illuminate\Database\Seeder;

/**
 * Slice-2 PR-2.1: curated Kenyan management-agreement clauses.
 *
 * DRAFT content — every clause is flagged needs_legal_review and must be signed
 * off by a Kenyan advocate before go-live (see docs/legal-compliance-kenya.md).
 * Idempotent: keyed on (key, version) so re-running never duplicates. The
 * neutrality clause is the non-removable liability shield.
 */
class ManagementClauseSeeder extends Seeder
{
    private const VERSION = 'draft-2026-06';

    public function run(): void
    {
        foreach ($this->clauses() as $clause) {
            Clause::query()->updateOrCreate(
                ['key' => $clause['key'], 'version' => self::VERSION],
                array_merge($clause, [
                    'type' => ClauseType::Management,
                    'version' => self::VERSION,
                    'jurisdiction' => 'KE',
                    'is_active' => true,
                    'needs_legal_review' => true,
                ]),
            );
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function clauses(): array
    {
        return [
            [
                'key' => 'mgmt.fee',
                'binding' => ClauseBinding::ManagementFee,
                'title' => 'Management fee',
                'explanation' => 'What the Manager charges to run the Owner\'s properties, and how it is calculated.',
                'body_template' => 'The Manager shall earn a management fee of {fee_description} on the Owner\'s portfolio for each period, deducted from collections before the net is remitted to the Owner.',
                'params_schema' => [
                    ['name' => 'type', 'options' => ['percentage', 'flat']],
                    ['name' => 'value'],
                    ['name' => 'base', 'options' => ['collected', 'billed', 'scheduled']],
                    ['name' => 'flat_cadence', 'options' => ['per_period', 'per_unit']],
                ],
                'is_exclusive' => true,
            ],
            [
                'key' => 'mgmt.money_flow',
                'binding' => ClauseBinding::MoneyFlow,
                'title' => 'How rent is collected and held',
                'explanation' => 'Whether the Manager ever holds the Owner\'s money. Split-at-source keeps the Manager out of the client-money trust regime.',
                'body_template' => 'Rent shall be collected by split-at-source so that the Manager never receives or holds the Owner\'s funds; the Owner\'s net share is settled directly to the Owner\'s account at the time of payment.',
                'params_schema' => [],
                'is_exclusive' => true,
            ],
            [
                'key' => 'mgmt.payout',
                'binding' => ClauseBinding::Payout,
                'title' => 'Owner payout',
                'explanation' => 'Where and how quickly the Owner\'s net proceeds are paid.',
                'body_template' => 'The Owner\'s net proceeds shall be paid to {payout_destination} within {payout_days} days of collection, accompanied by a statement of account.',
                'params_schema' => [
                    ['name' => 'payout_destination'],
                    ['name' => 'payout_days'],
                ],
                'is_exclusive' => true,
            ],
            [
                'key' => 'mgmt.authority',
                'binding' => ClauseBinding::ManagerAuthority,
                'title' => 'Manager authority',
                'explanation' => 'What the Manager may do on the Owner\'s behalf without asking first.',
                'body_template' => 'The Manager is authorised to let units, collect rent, and carry out repairs up to KES {repair_cap} per item without prior approval; works above that figure require the Owner\'s written consent.',
                'params_schema' => [
                    ['name' => 'repair_cap'],
                ],
                'is_exclusive' => true,
            ],
            [
                'key' => 'mgmt.notice',
                'binding' => ClauseBinding::Notice,
                'title' => 'Ending the agreement',
                'explanation' => 'How much notice either party must give to end the management relationship.',
                'body_template' => 'Either party may terminate this agreement by giving not less than {notice_days} days\' written notice, the notice to end on a rent-payment day.',
                'params_schema' => [
                    ['name' => 'notice_days'],
                ],
                'is_exclusive' => true,
            ],
            [
                'key' => 'mgmt.neutrality',
                'binding' => ClauseBinding::Neutrality,
                'title' => 'Platform neutrality and liability',
                'explanation' => 'PropManager hosts and executes this agreement but is not a party to it. Required and cannot be removed.',
                'body_template' => 'PropManager provides the platform that records and executes this agreement. PropManager is not a party to this agreement, is not an estate agent, and is not liable for either party\'s performance or breach. This document is not legal advice; each party should obtain independent legal advice.',
                'params_schema' => [],
                'is_exclusive' => true,
            ],
        ];
    }
}
