<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use App\Services\MetricsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Phase-60 TRIAL-DEPTH-3: transitions stale Trialing subscriptions
 * past their trial_ends_at to Cancelled with reason=trial_expired.
 * Emits trial_expired_count gauge so ops sees the daily expiry
 * volume. Runs daily 09:30 Africa/Nairobi (after Phase-34
 * TrialEndingReminder 09:00 so the reminder fires before the
 * expiry happens).
 */
class TrialAutoExpire extends Command
{
    protected $signature = 'trial:auto-expire';

    protected $description = 'Phase-60 TRIAL-DEPTH-3: transition expired trial subscriptions to cancelled.';

    public function handle(MetricsService $metrics): int
    {
        $expired = 0;

        DB::transaction(function () use (&$expired) {
            $rows = Subscription::query()
                ->where('status', SubscriptionStatus::Trialing)
                ->whereNotNull('trial_ends_at')
                ->where('trial_ends_at', '<', now())
                ->lockForUpdate()
                ->get();

            foreach ($rows as $sub) {
                $sub->status = SubscriptionStatus::Cancelled;
                $sub->cancelled_at = now();
                $sub->cancel_reason = 'trial_expired';
                $sub->ends_at = $sub->trial_ends_at;
                $sub->save();
                $expired++;
            }
        });

        $metrics->gauge('trial_expired_count', $expired);
        $this->info("trial_auto_expire expired={$expired}");

        return self::SUCCESS;
    }
}
