<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Mail\WinbackMailable;
use App\Models\Subscription;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;

/**
 * Phase-34 GROWTH-LIFECYCLE-3: winback at day 7 (WINBACK10) and
 * day 30 (WINBACK20) post-cancellation. Industry benchmark
 * 5-10% reactivation rate.
 */
class ChurnWinback extends Command
{
    protected $signature = 'subscriptions:churn-winback';

    protected $description = 'Phase-34 GROWTH-LIFECYCLE-3: winback discount touches at 7d + 30d post-cancel.';

    public function handle(): int
    {
        $sent = 0;
        foreach ([7 => 'WINBACK10', 30 => 'WINBACK20'] as $daysAgo => $code) {
            $target = now()->subDays($daysAgo)->startOfDay();
            $targetEnd = $target->copy()->endOfDay();

            $candidates = Subscription::query()
                ->whereBetween('cancelled_at', [$target, $targetEnd])
                ->with('user')
                ->get();

            foreach ($candidates as $sub) {
                $cacheKey = sprintf('winback:%d:%d', $sub->id, $daysAgo);
                if (! Cache::add($cacheKey, true, now()->addDays(60))) {
                    continue;
                }
                if (! $sub->user?->email) {
                    continue;
                }
                Mail::to($sub->user->email)->queue(new WinbackMailable($sub, $code));
                $sent++;
            }
        }

        $this->info(sprintf('Queued %d winback email(s).', $sent));

        return self::SUCCESS;
    }
}
