<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Slice-2: a clause's system binding — the config a signed clause governs.
 * Only ManagementFee writes + LOCKS a governed field (PropertyOwner.management_fee_*)
 * when the agreement activates (PR 2.3); the rest are informational in this slice
 * (money-flow/payout binding wiring extends in Slices 4–5). governsConfig() is the
 * single lever the AgreementApplicator + drift-lock guard key on.
 */
enum ClauseBinding: string
{
    case ManagementFee = 'management_fee';
    case MoneyFlow = 'money_flow';
    case Payout = 'payout';
    case ManagerAuthority = 'manager_authority';
    case Notice = 'notice';
    case Neutrality = 'neutrality';

    /** Whether assenting to this clause writes + locks a governed config field. */
    public function governsConfig(): bool
    {
        return $this === self::ManagementFee;
    }
}
