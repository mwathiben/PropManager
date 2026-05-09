<?php

namespace App\Jobs;

use App\Services\FinanceStatsService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class WarmFinanceCacheJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, Queueable;

    public int $tries = 1;

    public int $uniqueFor = 10;

    public function __construct(
        public readonly int $landlordId
    ) {}

    public function uniqueId(): string
    {
        return "warm-finance-cache:{$this->landlordId}";
    }

    public function handle(FinanceStatsService $stats): void
    {
        $start = microtime(true);

        // PERF-Q12: warm overview + arrears BEFORE hub. getHubStats() calls
        // getOverviewStats() and getArrearsStats() inside its own remember
        // block; without this ordering, cold-start warming would execute
        // those inner aggregations once for the inner caches and again
        // inside the outer hub callback before the inner caches were set.
        $stats->getOverviewStats($this->landlordId);
        $stats->getArrearsStats($this->landlordId);

        $stats->getHubStats($this->landlordId);
        $stats->getDepositStats($this->landlordId);
        $stats->getLateFeeStats($this->landlordId);
        $stats->getExpenseStats($this->landlordId);
        $stats->getMonthlyTrend($this->landlordId);

        $durationMs = round((microtime(true) - $start) * 1000);

        Log::channel('cache')->info('Cache warmed', [
            'landlord_id' => $this->landlordId,
            'duration_ms' => $durationMs,
        ]);
    }
}
