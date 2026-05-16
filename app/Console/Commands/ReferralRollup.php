<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Referral;
use App\Services\MetricsService;
use Illuminate\Console\Command;

/**
 * Phase-34 GROWTH-REFERRAL-3: per-landlord attribution rollup.
 *
 * Emits landlord_referrals_count_30d{landlord_id} gauge for the top
 * 20 referrers — feeds the operator dashboard so we can see which
 * 5% of landlords drive 80% of referrals (Pareto, basically). Same
 * top-N shape as Phase-33 log:volume-audit.
 */
class ReferralRollup extends Command
{
    protected $signature = 'referrals:rollup';

    protected $description = 'Phase-34 GROWTH-REFERRAL-3: per-landlord referrals_count_30d gauge.';

    public function handle(MetricsService $metrics): int
    {
        $since = now()->subDays(30);

        $rows = Referral::query()
            ->whereNotNull('attributed_at')
            ->where('attributed_at', '>=', $since)
            ->selectRaw('referrer_user_id, COUNT(*) AS attributed_count')
            ->groupBy('referrer_user_id')
            ->orderByDesc('attributed_count')
            ->limit(20)
            ->get();

        foreach ($rows as $row) {
            $metrics->gauge(
                'landlord_referrals_count_30d',
                (float) $row->attributed_count,
                ['landlord_id' => (string) $row->referrer_user_id],
            );
        }

        $this->info(sprintf('Emitted referrals_count_30d for %d landlord(s).', $rows->count()));

        return self::SUCCESS;
    }
}
