<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Building;
use App\Models\Document;
use App\Models\Invoice;
use App\Models\KycRequirement;
use App\Models\Lease;
use App\Models\Property;
use App\Models\Unit;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;

/**
 * Phase-12 RETAIN-4: force-delete soft-deleted rows past the grace
 * window. DELETION_GRACE_DAYS=30 is defined in .env.example but
 * historically had no consumer — soft-deleted rows lived forever
 * (storage cost + Kenya DPA 'storage limitation' violation).
 *
 * Usage:
 *   php artisan soft-deleted:purge --grace-days=30
 *   php artisan soft-deleted:purge --grace-days=30 --confirm
 *
 * Only models explicitly listed in MODELS are purged — accidental
 * force-deletion of an unrelated soft-deleted model is impossible.
 * Models added here MUST use the SoftDeletes trait.
 */
class SoftDeletedPurge extends Command
{
    protected $signature = 'soft-deleted:purge
        {--grace-days= : Override DELETION_GRACE_DAYS env value}
        {--confirm : Required to actually force-delete}';

    protected $description = 'Force-delete soft-deleted rows past the deletion grace window (RETAIN-4).';

    /**
     * Per-model list of soft-deletable models eligible for purge.
     *
     * @var array<int, class-string<Model>>
     */
    private const MODELS = [
        Building::class,
        Document::class,
        Invoice::class,
        KycRequirement::class,
        Lease::class,
        Property::class,
        Unit::class,
    ];

    public function handle(): int
    {
        $graceDays = (int) ($this->option('grace-days') ?? config('security.compliance.deletion_grace_days', 30));
        if ($graceDays <= 0) {
            $this->error('Grace days must be a positive integer.');

            return self::INVALID;
        }

        $cutoff = now()->subDays($graceDays);
        $confirmed = (bool) $this->option('confirm');
        $totalDeleted = 0;
        $totalCandidates = 0;

        foreach (self::MODELS as $model) {
            $query = $model::onlyTrashed()->where('deleted_at', '<', $cutoff);
            $count = $query->count();

            $this->info(sprintf(
                '[%s] grace=%d days, cutoff=%s, candidates=%d',
                class_basename($model),
                $graceDays,
                $cutoff->toDateTimeString(),
                $count,
            ));

            if (! $confirmed) {
                $totalCandidates += $count;

                continue;
            }

            // Chunk the purge per-model so a model with millions of
            // soft-deleted rows doesn't lock the table all night.
            $deleted = 0;
            do {
                $batch = $model::onlyTrashed()
                    ->where('deleted_at', '<', $cutoff)
                    ->limit(500)
                    ->get();

                foreach ($batch as $row) {
                    $row->forceDelete();
                    $deleted++;
                }
            } while ($batch->count() > 0);

            $this->line("  force-deleted: {$deleted}");
            $totalDeleted += $deleted;
        }

        if ($confirmed) {
            $this->info("Total force-deleted: {$totalDeleted}");
        } else {
            $this->warn('DRY RUN — pass --confirm to apply.');
            $this->info("Total candidates: {$totalCandidates}");
        }

        return self::SUCCESS;
    }
}
