<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use App\Models\User;
use App\Services\MetricsService;
use App\Services\Sre\AlertFiringRecorder;
use Illuminate\Console\Command;

/**
 * Phase-35 PLATFORM-METER-2/3: per-landlord per-feature usage ratio.
 *
 *   - For every active paying landlord + every billable feature
 *     (properties/units/caretakers/buildings), computes
 *     ratio = current_usage / plan_limit.
 *   - Emits metered_usage_ratio{feature, plan_slug} gauge for top
 *     50 (landlord, feature) pairs by descending ratio.
 *   - Fires high_metered_overage (sev4) when any pair exceeds 1.5x
 *     plan limit — operator follows up with upgrade conversation.
 */
class MeteredSoftCapAudit extends Command
{
    private const BILLABLE_FEATURES = ['properties', 'units', 'caretakers', 'buildings'];

    protected $signature = 'metered:soft-cap-audit {--threshold=1.5}';

    protected $description = 'Phase-35 PLATFORM-METER-2: per-landlord per-feature usage ratio + overage alert.';

    public function handle(MetricsService $metrics, AlertFiringRecorder $recorder): int
    {
        $threshold = max(0.0, (float) $this->option('threshold'));

        $payingLandlordIds = Subscription::query()
            ->whereIn('status', [SubscriptionStatus::Active, SubscriptionStatus::PastDue])
            ->whereNull('cancelled_at')
            ->pluck('user_id')
            ->all();

        if ($payingLandlordIds === []) {
            $this->info('No paying landlords — nothing to audit.');
            $recorder->resolve('high_metered_overage');

            return self::SUCCESS;
        }

        $pairs = [];
        foreach ($payingLandlordIds as $landlordId) {
            $landlord = User::find($landlordId);
            if (! $landlord) {
                continue;
            }
            foreach (self::BILLABLE_FEATURES as $feature) {
                $limit = $landlord->getLimit($feature);
                if ($limit <= 0) {
                    continue;
                }
                $usage = $landlord->getUsage($feature);
                $ratio = round($usage / $limit, 4);
                $pairs[] = [
                    'landlord_id' => (int) $landlordId,
                    'feature' => $feature,
                    'plan_slug' => $landlord->plan?->slug ?? 'unknown',
                    'ratio' => $ratio,
                ];
            }
        }

        usort($pairs, fn ($a, $b) => $b['ratio'] <=> $a['ratio']);
        foreach (array_slice($pairs, 0, 50) as $pair) {
            $metrics->gauge(
                'metered_usage_ratio',
                (float) $pair['ratio'],
                ['feature' => $pair['feature'], 'plan_slug' => $pair['plan_slug']],
            );
        }

        $offenders = array_filter($pairs, fn ($p) => $p['ratio'] >= $threshold);
        if ($offenders !== []) {
            $worst = max(array_column($offenders, 'ratio'));
            $recorder->record(
                alertKey: 'high_metered_overage',
                value: $worst,
                threshold: $threshold,
                metadata: ['offenders' => array_values($offenders)],
            );
        } else {
            $recorder->resolve('high_metered_overage');
        }

        $this->info(sprintf(
            'Audited %d pair(s). offenders=%d',
            count($pairs),
            count($offenders),
        ));

        return self::SUCCESS;
    }
}
