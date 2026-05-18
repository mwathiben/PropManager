<?php

declare(strict_types=1);

namespace App\Services\Lease;

use App\Models\Lease;
use Illuminate\Support\Facades\DB;

/**
 * Phase-61 RENEWAL-AUTO-1: scan leases expiring within $daysAhead
 * and auto-create the next-period Lease for those opted in
 * (auto_renew=true) where no counter-proposal is already in flight.
 *
 * The original lease keeps its end_date; the new lease covers
 * start = original.end_date + 1 day. Financial terms inherit.
 *
 * Phase 45 LEASE-COUNTER handles manual counter-proposal during
 * renewal; this complements with the default-renew path.
 */
class LeaseRenewalAutoService
{
    /**
     * @return array<int, Lease> the newly created leases
     */
    public function scanExpiring(int $daysAhead = 30, bool $dryRun = false): array
    {
        $until = now()->addDays($daysAhead);

        $expiring = Lease::query()
            ->where('auto_renew', true)
            ->where('is_active', true)
            ->whereDate('end_date', '>=', now()->toDateString())
            ->whereDate('end_date', '<=', $until->toDateString())
            ->get();

        $created = [];

        foreach ($expiring as $lease) {
            if ($dryRun) {
                continue;
            }

            $created[] = DB::transaction(fn () => $this->renew($lease));
        }

        return $created;
    }

    public function renew(Lease $lease): Lease
    {
        // Mirror the original lease's term length (in days) so the new
        // lease covers an equivalent window.
        $start = $lease->end_date->copy()->addDay();
        $termDays = $lease->start_date->diffInDays($lease->end_date);
        $end = $start->copy()->addDays($termDays);

        return Lease::create([
            'unit_id' => $lease->unit_id,
            'tenant_id' => $lease->tenant_id,
            'landlord_id' => $lease->landlord_id,
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'rent_amount' => $lease->rent_amount,
            'deposit_amount' => $lease->deposit_amount,
            'service_charge' => $lease->service_charge,
            'is_active' => true,
            'auto_renew' => true,
            'renewed_from_lease_id' => $lease->id,
            'reminder_tier' => $lease->reminder_tier,
        ]);
    }
}
