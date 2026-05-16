<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Mail\ActivationNudgeMailable;
use App\Models\OnboardingProgress;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;

/**
 * Phase-34 GROWTH-LIFECYCLE-3: dispatch when OnboardingProgress.
 * last_touched_at is older than 3 days AND landlord hasn't finished
 * the funnel. Phase-31 ActivationAudit measures this stall bucket
 * but didn't dispatch — this cron closes that loop.
 */
class ActivationNudge extends Command
{
    protected $signature = 'landlords:activation-nudge';

    protected $description = 'Phase-34 GROWTH-LIFECYCLE-3: nudge landlords stalled mid-onboarding for 3+ days.';

    public function handle(): int
    {
        $cutoff = now()->subDays(3);
        $sent = 0;

        $stalls = OnboardingProgress::query()
            ->where('last_touched_at', '<=', $cutoff)
            ->whereNull('completed_at')
            ->get();

        foreach ($stalls as $progress) {
            $landlord = User::find($progress->user_id);
            if (! $landlord?->email || $landlord->role !== 'landlord') {
                continue;
            }
            // One nudge per 7-day window per landlord (don't spam).
            $cacheKey = sprintf('activation_nudge:%d:%s', $landlord->id, now()->format('Y-W'));
            if (! Cache::add($cacheKey, true, now()->addDays(7))) {
                continue;
            }
            Mail::to($landlord->email)->queue(new ActivationNudgeMailable($progress));
            $sent++;
        }

        $this->info(sprintf('Queued %d activation nudge(s).', $sent));

        return self::SUCCESS;
    }
}
