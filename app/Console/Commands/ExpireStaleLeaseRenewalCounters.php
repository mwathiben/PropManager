<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\LeaseRenewal;
use App\Models\LeaseRenewalCounterHistory;
use App\Services\MetricsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Phase-45 LEASE-COUNTER-3: expire counter-offers older than
 * LeaseRenewal::COUNTER_EXPIRY_DAYS (14 days). A counter_proposed
 * renewal that the landlord never reviews would stall indefinitely
 * without this sweep.
 *
 * Cron: daily 06:00 Africa/Nairobi. Emits
 * lease_renewal_counter_expired_count{landlord_id} gauge.
 */
class ExpireStaleLeaseRenewalCounters extends Command
{
    protected $signature = 'lease-renewal:expire-stale-counters';

    protected $description = 'Phase-45 LEASE-COUNTER-3: expire counter-offers older than 14 days.';

    public function handle(MetricsService $metrics): int
    {
        $cutoff = now()->subDays(LeaseRenewal::COUNTER_EXPIRY_DAYS);

        $stale = LeaseRenewal::query()
            ->where('status', LeaseRenewal::STATUS_COUNTER_PROPOSED)
            ->where('counter_submitted_at', '<', $cutoff)
            ->get();

        $perLandlord = [];

        foreach ($stale as $renewal) {
            DB::transaction(function () use ($renewal): void {
                $renewal->update(['status' => LeaseRenewal::STATUS_EXPIRED]);
                LeaseRenewalCounterHistory::create([
                    'lease_renewal_id' => $renewal->id,
                    'actor_user_id' => $renewal->landlord_id,
                    'action' => LeaseRenewalCounterHistory::ACTION_EXPIRED,
                    'rent_amount_cents' => $renewal->counter_rent_amount_cents,
                    'end_date' => $renewal->counter_end_date,
                    'message' => null,
                ]);
            });

            $perLandlord[$renewal->landlord_id] = ($perLandlord[$renewal->landlord_id] ?? 0) + 1;
        }

        foreach ($perLandlord as $landlordId => $count) {
            $metrics->gauge('lease_renewal_counter_expired_count', $count, ['landlord_id' => (string) $landlordId]);
        }

        $this->info(sprintf('Expired %d stale counter-offers across %d landlord(s).', $stale->count(), count($perLandlord)));

        return self::SUCCESS;
    }
}
