<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\SubscriptionStatus;
use App\Mail\DunningReminderMailable;
use App\Models\Subscription;
use App\Services\SubscriptionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;

/**
 * Phase-34 GROWTH-LIFECYCLE-2: past_due payment recovery.
 *
 *   Day 1 / 4 / 7 since entering past_due: nudge email.
 *   Day 14: auto-cancel with cancel_reason='technical_issues'.
 *
 * Involuntary churn (failed payment) is typically 20-40% of total
 * churn — easiest to recover. Cache::add keyed on (sub_id, dunning_day)
 * prevents re-sending the same touch.
 */
class DunningEmails extends Command
{
    protected $signature = 'subscriptions:dunning-emails';

    protected $description = 'Phase-34 GROWTH-LIFECYCLE-2: dunning sequence + auto-cancel at day 14.';

    public function handle(SubscriptionService $service): int
    {
        $sent = 0;
        $cancelled = 0;

        $pastDue = Subscription::query()
            ->where('status', SubscriptionStatus::PastDue)
            ->with('user')
            ->get();

        foreach ($pastDue as $sub) {
            if (! $sub->updated_at) {
                continue;
            }
            $daysSince = (int) abs($sub->updated_at->diffInDays(now()));

            if (in_array($daysSince, [1, 4, 7], true)) {
                $cacheKey = sprintf('dunning:%d:%d', $sub->id, $daysSince);
                if (! Cache::add($cacheKey, true, now()->addDays(2))) {
                    continue;
                }
                if (! $sub->user?->email) {
                    continue;
                }
                if (! app(\App\Services\Platform\LifecycleOptInChecker::class)->allows($sub->user)) {
                    continue;
                }
                Mail::to($sub->user->email)->queue(new DunningReminderMailable($sub, $daysSince));
                $sent++;

                continue;
            }

            if ($daysSince >= 14) {
                $service->cancel($sub, true, 'technical_issues', 'Auto-cancelled after 14 days past due.');
                $cancelled++;
            }
        }

        $this->info(sprintf('Queued %d dunning email(s). Auto-cancelled %d subscription(s).', $sent, $cancelled));

        return self::SUCCESS;
    }
}
