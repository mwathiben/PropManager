<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\OwnerPayout;
use Illuminate\Support\Carbon;

/**
 * Phase-103 OWNER-PAYOUTS: the owner's running position, DERIVED (never a stored ledger that
 * could drift): lifetime statement net (collected − expenses − management fee, excluding
 * voided payments) minus the sum of non-voided payouts already remitted. Can go negative —
 * that means the manager has paid the owner ahead of earnings (an advance), surfaced honestly.
 */
class OwnerLedgerService
{
    /** A floor far enough back to capture every payment/expense for "lifetime". */
    private const INCEPTION = '2000-01-01';

    public function __construct(private OwnerStatementService $statements) {}

    /**
     * @return array{lifetime_collected: float, lifetime_expenses: float, lifetime_management_fee: float, lifetime_net: float, total_paid_out: float, balance_due: float}
     */
    public function summary(int $landlordId, int $ownerId): array
    {
        $statement = $this->statements->forOwner(
            $landlordId,
            $ownerId,
            Carbon::parse(self::INCEPTION),
            Carbon::now()->endOfDay(),
        );

        // forOwner returns null only when the owner isn't this landlord's — every caller
        // (PropertyOwnerController::show, OwnerPortalPayoutsController) resolves + guards the
        // owner first, so null here means a same-request deletion; a zeroed position is the
        // safe degradation (no fabricated earnings).
        $lifetimeNet = (float) ($statement['net'] ?? 0.0);

        $totalPaidOut = (float) OwnerPayout::query()
            ->where('landlord_id', $landlordId)
            ->where('property_owner_id', $ownerId)
            ->whereNull('voided_at')
            ->sum('amount');

        return [
            'lifetime_collected' => (float) ($statement['collected'] ?? 0.0),
            'lifetime_expenses' => (float) ($statement['total_expenses'] ?? 0.0),
            'lifetime_management_fee' => (float) ($statement['management_fee'] ?? 0.0),
            'lifetime_net' => round($lifetimeNet, 2),
            'total_paid_out' => round($totalPaidOut, 2),
            'balance_due' => round($lifetimeNet - $totalPaidOut, 2),
        ];
    }
}
