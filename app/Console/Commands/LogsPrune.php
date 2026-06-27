<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Phase-12 RETAIN-1 / RETAIN-2: prune append-only log tables past their
 * configured retention window. The retention values have lived in
 * config/security.php for months with zero consumers — this command
 * is the consumer.
 *
 * audit_logs:        config('security.audit.retention_days')   default 365
 * security_logs:     config('security.logging.retention_days') default 90
 *
 * Usage:
 *   php artisan logs:prune --table=audit --dry-run
 *   php artisan logs:prune --table=security --confirm
 *   php artisan logs:prune --table=all --confirm
 *
 * Pruning is destructive; --confirm is required. --dry-run is the
 * default behaviour and reports row counts without DELETE.
 */
class LogsPrune extends Command
{
    protected $signature = 'logs:prune
        {--table= : One of: audit, security, all}
        {--confirm : Required to actually delete rows}
        {--dry-run : Force dry-run even without --confirm (default behaviour)}';

    protected $description = 'Prune audit_logs / security_logs past their configured retention window.';

    /**
     * Map of supported tables to their retention-config key + cutoff
     * resolver. Each entry MUST be append-only — pruning is safe only
     * when the table never UPDATEs a row's logical 'occurrence date'.
     *
     * @var array<string, array{table: string, config: string, default: int}>
     */
    private const TABLES = [
        'audit' => [
            'table' => 'audit_logs',
            'config' => 'security.audit.retention_days',
            'default' => 365,
            'column' => 'created_at',
            'where_not_null' => null,
        ],
        'security' => [
            'table' => 'security_logs',
            'config' => 'security.logging.retention_days',
            'default' => 90,
            'column' => 'created_at',
            'where_not_null' => null,
        ],
        // Phase-12 RETAIN-7: webhook_dead_letters carries failed-
        // payment payloads with PII (phone, account, transaction id).
        // Resolved entries past 90 days are operational debt the
        // operator already decided is closed — purge. Unresolved old
        // entries are NOT pruned here; the column-vs-where_not_null
        // gate is the safety mechanism.
        'dead-letter' => [
            'table' => 'webhook_dead_letters',
            // RETAIN-8 follow-up: config('payments.dead_letter.retention_days')
            // existed pre-Phase-12 with no consumer. This command is the
            // consumer; the value (28 days default) is preserved.
            'config' => 'payments.dead_letter.retention_days',
            'default' => 28,
            'column' => 'resolved_at',
            'where_not_null' => 'resolved_at',
        ],
        // Phase-12 RETAIN-10: high-volume append-only webhook log
        // tables. Payment-reconciliation operators rarely look back
        // past 180 days; index bloat past that hurts read perf.
        'webhook' => [
            'table' => 'webhook_logs',
            'config' => 'webhook.retention_days',
            'default' => 180,
            'column' => 'created_at',
            'where_not_null' => null,
        ],
        'bank-webhook' => [
            'table' => 'bank_webhook_logs',
            'config' => 'webhook.bank_retention_days',
            'default' => 180,
            'column' => 'created_at',
            'where_not_null' => null,
        ],
        // Phase-13 DPA-8 (RETAIN-5 follow-up): consent records past
        // their 'duration of consent + 3 years' window. We prune
        // ONLY withdrawn consents — active consents never expire
        // because the lawful basis is ongoing. The retention window
        // is computed against withdrawn_at; an active consent has
        // NULL there and the where_not_null gate skips it.
        'consent' => [
            'table' => 'consents',
            'config' => 'security.compliance.consent_retention_days',
            'default' => 1095, // 3 years
            'column' => 'withdrawn_at',
            'where_not_null' => 'withdrawn_at',
        ],
    ];

    public function handle(): int
    {
        $targets = $this->resolveTargets();
        if ($targets === null) {
            return self::INVALID;
        }

        $confirmed = (bool) $this->option('confirm');
        [$totalDeleted, $totalRetained] = $this->processTargets($targets, $confirmed);

        $this->outputSummary($confirmed, $totalDeleted, $totalRetained);

        return self::SUCCESS;
    }

    /**
     * Parse and validate the --table option, returning the list of keys to process,
     * or null if the option is missing/invalid (error already printed).
     *
     * @return list<string>|null
     */
    private function resolveTargets(): ?array
    {
        $tableOption = (string) ($this->option('table') ?? '');

        if ($tableOption === '') {
            $this->error('--table is required. Use --table=audit, --table=security, or --table=all.');

            return null;
        }

        $targets = $tableOption === 'all'
            ? array_keys(self::TABLES)
            : [$tableOption];

        foreach ($targets as $key) {
            if (! array_key_exists($key, self::TABLES)) {
                $this->error("Unknown --table value '{$key}'. Supported: ".implode(', ', array_keys(self::TABLES)).', all.');

                return null;
            }
        }

        return $targets;
    }

    /**
     * Process each target key, printing per-table info and optionally deleting rows.
     *
     * @param  list<string>  $targets
     * @return array{int, int} [totalDeleted, totalRetained]
     */
    private function processTargets(array $targets, bool $confirmed): array
    {
        $totalDeleted = 0;
        $totalRetained = 0;

        foreach ($targets as $key) {
            [$deleted, $retained] = $this->processOneTarget($key, $confirmed);
            $totalDeleted += $deleted;
            $totalRetained += $retained;
        }

        return [$totalDeleted, $totalRetained];
    }

    /**
     * Process a single table key: count candidates, optionally delete, and log progress.
     *
     * @return array{int, int} [deleted, retained]
     */
    private function processOneTarget(string $key, bool $confirmed): array
    {
        $spec = self::TABLES[$key];
        $retentionDays = (int) config($spec['config'], $spec['default']);
        $cutoff = now()->subDays($retentionDays);
        $column = $spec['column'];

        $candidateCount = $this->countCandidates($spec, $column, $cutoff);

        $this->info(sprintf(
            '[%s] retention=%d days, cutoff=%s, candidates=%d',
            $spec['table'],
            $retentionDays,
            $cutoff->toDateTimeString(),
            $candidateCount,
        ));

        if (! $confirmed) {
            return [0, $candidateCount];
        }

        $deleted = $this->deleteInChunks($spec, $column, $cutoff);
        $this->line("  deleted: {$deleted}");

        return [$deleted, 0];
    }

    /**
     * Count candidate rows for pruning (respects where_not_null guard).
     *
     * @param  array<string, mixed>  $spec
     */
    private function countCandidates(array $spec, string $column, \Carbon\CarbonInterface $cutoff): int
    {
        $query = DB::table($spec['table'])->where($column, '<', $cutoff);
        if ($spec['where_not_null'] !== null) {
            $query->whereNotNull($spec['where_not_null']);
        }

        return $query->count();
    }

    /**
     * Delete matching rows in 1 000-row chunks to avoid long table locks.
     *
     * @param  array<string, mixed>  $spec
     */
    private function deleteInChunks(array $spec, string $column, \Carbon\CarbonInterface $cutoff): int
    {
        $deleted = 0;

        do {
            $query = DB::table($spec['table'])->where($column, '<', $cutoff);
            if ($spec['where_not_null'] !== null) {
                $query->whereNotNull($spec['where_not_null']);
            }
            $batch = $query->limit(1000)->delete();
            $deleted += $batch;
        } while ($batch > 0);

        return $deleted;
    }

    /**
     * Print the final summary line (dry-run warning or total deleted).
     */
    private function outputSummary(bool $confirmed, int $totalDeleted, int $totalRetained): void
    {
        if ($confirmed) {
            $this->info("Total deleted: {$totalDeleted}");
        } else {
            $this->warn('DRY RUN — pass --confirm to apply.');
            $this->info("Total candidates: {$totalRetained}");
        }
    }
}
