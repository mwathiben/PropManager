<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Events\DocumentExpiryApproaching;
use App\Models\Document;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Phase-82 DOC-REMINDERS-1: daily scan for renewable, current documents whose
 * expiry falls within their reminder window (per-doc reminder_days, else 30).
 * Fires DocumentExpiryApproaching once per (document, year-month) — the active
 * loop the tenant banner never had. Mirrors leases:scan-renewals idempotency.
 */
class DocumentsScanExpiring extends Command
{
    protected $signature = 'documents:scan-expiring {--dry-run}';

    protected $description = 'Phase-82 DOC-REMINDERS-1: fire document expiry reminders for renewable docs in their reminder window.';

    public const DEFAULT_WINDOW_DAYS = 30;

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $today = CarbonImmutable::now()->startOfDay();
        $fired = 0;

        Document::query()
            ->withoutGlobalScopes()
            ->dueForReminder(self::DEFAULT_WINDOW_DAYS)
            ->chunkById(200, function ($documents) use ($today, $dryRun, &$fired) {
                foreach ($documents as $document) {
                    $daysRemaining = (int) $today->diffInDays(
                        CarbonImmutable::parse($document->expires_at)->startOfDay(),
                        false,
                    );

                    // One reminder per document per calendar month.
                    $key = sprintf('document-expiry:%d:%s', $document->id, $today->format('Y-m'));
                    if (! Cache::add($key, true, now()->addDays(31))) {
                        continue;
                    }

                    if (! $dryRun) {
                        DocumentExpiryApproaching::dispatch($document, $daysRemaining);
                    }
                    $fired++;
                }
            });

        $this->info("documents:scan-expiring: {$fired} reminder(s) fired".($dryRun ? ' (dry-run)' : ''));

        return self::SUCCESS;
    }
}
