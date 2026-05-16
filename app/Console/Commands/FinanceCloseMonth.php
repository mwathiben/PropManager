<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\AccountingPeriod;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * Phase-30 INT-PERIOD-LOCK-1: monthly accounting close. Walks every
 * landlord with active finance activity and closes the previous
 * full calendar month. Idempotent: if a period for that landlord +
 * start date already exists, this run is a no-op.
 *
 * Scheduled monthly on the 1st at 02:30 Africa/Nairobi (after the
 * 02:00 dpa:enforce-retention window so the period close doesn't
 * race the retention pipeline for the same row).
 */
class FinanceCloseMonth extends Command
{
    protected $signature = 'finance:close-month
        {--landlord= : close only this landlord_id}
        {--month= : YYYY-MM (defaults to the previous calendar month)}';

    protected $description = 'Phase-30 INT-PERIOD-LOCK-1: close the previous calendar month for each landlord.';

    public function handle(): int
    {
        $month = $this->option('month');
        $target = $month !== null
            ? CarbonImmutable::createFromFormat('Y-m-d', $month.'-01')
            : CarbonImmutable::now()->subMonthNoOverflow()->startOfMonth();

        if (! $target instanceof CarbonImmutable) {
            $this->error('Invalid --month; expected YYYY-MM.');

            return self::FAILURE;
        }
        $periodStart = $target->startOfMonth();
        $periodEnd = $target->endOfMonth();

        $landlords = User::query()->where('role', 'landlord');
        if ($this->option('landlord')) {
            $landlords->where('id', (int) $this->option('landlord'));
        }

        $closed = 0;
        $skipped = 0;
        $landlords->each(function (User $landlord) use ($periodStart, $periodEnd, &$closed, &$skipped): void {
            $existing = AccountingPeriod::query()
                ->withoutGlobalScopes()
                ->where('landlord_id', $landlord->id)
                ->where('period_start', $periodStart->toDateString())
                ->first();
            if ($existing !== null) {
                $skipped++;

                return;
            }
            AccountingPeriod::create([
                'landlord_id' => $landlord->id,
                'period_start' => $periodStart->toDateString(),
                'period_end' => $periodEnd->toDateString(),
                'status' => AccountingPeriod::STATUS_CLOSED,
                'closed_at' => now(),
                'close_notes' => 'Auto-closed by finance:close-month',
            ]);
            $closed++;
        });

        $this->info(sprintf(
            'Closed %d landlord period(s) for %s; skipped %d (already closed).',
            $closed,
            $periodStart->format('Y-m'),
            $skipped,
        ));

        return self::SUCCESS;
    }
}
