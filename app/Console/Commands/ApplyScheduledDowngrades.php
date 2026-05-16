<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Subscription;
use App\Models\SubscriptionChange;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Phase-35 PLATFORM-BILLING-2: walks subscription_changes rows
 * whose scheduled_for is past AND effective_at is still NULL,
 * applies the plan_id change + stamps effective_at.
 *
 * Runs at 02:00 — before any Phase-30 finance cluster (02:30
 * finance:close-month) so the new plan_id is reflected when the
 * month closes.
 */
class ApplyScheduledDowngrades extends Command
{
    protected $signature = 'subscriptions:apply-downgrades';

    protected $description = 'Phase-35 PLATFORM-BILLING-2: apply scheduled downgrades at period boundary.';

    public function handle(): int
    {
        $due = SubscriptionChange::query()
            ->whereNotNull('scheduled_for')
            ->whereNull('effective_at')
            ->where('scheduled_for', '<=', now())
            ->get();

        $applied = 0;
        foreach ($due as $change) {
            DB::transaction(function () use ($change, &$applied) {
                $sub = Subscription::find($change->subscription_id);
                if (! $sub) {
                    return;
                }
                $sub->update(['plan_id' => $change->to_plan_id]);
                $change->update(['effective_at' => now()]);
                $applied++;
            });
        }

        $this->info(sprintf('Applied %d scheduled downgrade(s).', $applied));

        return self::SUCCESS;
    }
}
