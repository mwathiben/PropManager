<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\FinanceStatsService;
use Illuminate\Console\Command;

class WarmFinanceCache extends Command
{
    protected $signature = 'finance:warm-cache
                            {--landlord= : Warm cache for a specific landlord ID}
                            {--all : Warm cache for all landlords (default if no option provided)}';

    protected $description = 'Pre-warm finance statistics cache for faster page loads';

    public function handle(FinanceStatsService $stats): int
    {
        $startTime = microtime(true);

        $landlords = $this->getLandlords();

        if ($landlords->isEmpty()) {
            $this->warn('No landlords found to warm cache for.');

            return self::SUCCESS;
        }

        $this->info("Warming finance cache for {$landlords->count()} landlord(s)...");

        $bar = $this->output->createProgressBar($landlords->count());
        $bar->start();

        foreach ($landlords as $landlord) {
            $this->warmCacheForLandlord($stats, $landlord->id);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        $duration = round((microtime(true) - $startTime) * 1000);
        $this->info("Cache warmed successfully in {$duration}ms.");

        return self::SUCCESS;
    }

    private function getLandlords()
    {
        $landlordId = $this->option('landlord');

        if ($landlordId) {
            $landlord = User::where('id', $landlordId)
                ->where('role', 'landlord')
                ->first();

            if (! $landlord) {
                $this->error("Landlord with ID {$landlordId} not found.");

                return collect();
            }

            return collect([$landlord]);
        }

        return User::where('role', 'landlord')
            ->where('is_archived', false)
            ->get(['id']);
    }

    private function warmCacheForLandlord(FinanceStatsService $stats, int $landlordId): void
    {
        $stats->getHubStats($landlordId);
        $stats->getDepositStats($landlordId);
        $stats->getLateFeeStats($landlordId);
        $stats->getExpenseStats($landlordId);
        $stats->getMonthlyTrend($landlordId);
    }
}
