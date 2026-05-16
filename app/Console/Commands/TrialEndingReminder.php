<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\SubscriptionStatus;
use App\Mail\TrialEndingMailable;
use App\Models\Subscription;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;

/**
 * Phase-34 GROWTH-LIFECYCLE-1: trial-ending reminder fired at
 * 3 days, 1 day, and 0 days remaining. Cache::add idempotency
 * keyed on (subscription_id, days_remaining) prevents re-send.
 *
 * Conversion impact: industry data shows 8-12pp lift in trial->paid
 * with a 2-touch reminder vs no reminder at all.
 */
class TrialEndingReminder extends Command
{
    protected $signature = 'subscriptions:trial-ending-reminder';

    protected $description = 'Phase-34 GROWTH-LIFECYCLE-1: nudge trialing subscriptions at -3/-1/0 days.';

    public function handle(): int
    {
        $sent = 0;
        foreach ([3, 1, 0] as $daysRemaining) {
            $target = now()->addDays($daysRemaining)->startOfDay();
            $targetEnd = $target->copy()->endOfDay();

            $candidates = Subscription::query()
                ->where('status', SubscriptionStatus::Trialing)
                ->whereBetween('trial_ends_at', [$target, $targetEnd])
                ->with('user')
                ->get();

            foreach ($candidates as $sub) {
                $cacheKey = sprintf('trial_ending:%d:%d', $sub->id, $daysRemaining);
                if (! Cache::add($cacheKey, true, now()->addDays(2))) {
                    continue;
                }
                if (! $sub->user || ! $sub->user->email) {
                    continue;
                }
                if (! $this->lifecycleOptedIn($sub->user)) {
                    continue;
                }
                Mail::to($sub->user->email)->queue(new TrialEndingMailable($sub, $daysRemaining));
                $sent++;
            }
        }

        $this->info(sprintf('Queued %d trial-ending reminder(s).', $sent));

        return self::SUCCESS;
    }

    private function lifecycleOptedIn(\App\Models\User $user): bool
    {
        return app(\App\Services\Platform\LifecycleOptInChecker::class)->allows($user);
    }
}
