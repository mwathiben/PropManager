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
    ];

    public function handle(): int
    {
        $tableOption = (string) ($this->option('table') ?? '');
        if ($tableOption === '') {
            $this->error('--table is required. Use --table=audit, --table=security, or --table=all.');

            return self::INVALID;
        }

        $targets = $tableOption === 'all'
            ? array_keys(self::TABLES)
            : [$tableOption];

        foreach ($targets as $key) {
            if (! array_key_exists($key, self::TABLES)) {
                $this->error("Unknown --table value '{$key}'. Supported: ".implode(', ', array_keys(self::TABLES)).', all.');

                return self::INVALID;
            }
        }

        $totalDeleted = 0;
        $totalRetained = 0;
        $confirmed = (bool) $this->option('confirm');

        foreach ($targets as $key) {
            $spec = self::TABLES[$key];
            $retentionDays = (int) config($spec['config'], $spec['default']);
            $cutoff = now()->subDays($retentionDays);

            $column = $spec['column'];
            $candidateQuery = DB::table($spec['table'])->where($column, '<', $cutoff);
            if ($spec['where_not_null'] !== null) {
                $candidateQuery->whereNotNull($spec['where_not_null']);
            }
            $candidateCount = $candidateQuery->count();

            $this->info(sprintf(
                '[%s] retention=%d days, cutoff=%s, candidates=%d',
                $spec['table'],
                $retentionDays,
                $cutoff->toDateTimeString(),
                $candidateCount,
            ));

            if (! $confirmed) {
                $totalRetained += $candidateCount;

                continue;
            }

            // Chunk the delete so a very large prune does not lock the
            // table for too long. 1000 rows per batch is the spatie/
            // laravel-backup convention.
            $deleted = 0;
            do {
                $deleteQuery = DB::table($spec['table'])->where($column, '<', $cutoff);
                if ($spec['where_not_null'] !== null) {
                    $deleteQuery->whereNotNull($spec['where_not_null']);
                }
                $batch = $deleteQuery->limit(1000)->delete();
                $deleted += $batch;
            } while ($batch > 0);

            $this->line("  deleted: {$deleted}");
            $totalDeleted += $deleted;
        }

        if ($confirmed) {
            $this->info("Total deleted: {$totalDeleted}");
        } else {
            $this->warn('DRY RUN — pass --confirm to apply.');
            $this->info("Total candidates: {$totalRetained}");
        }

        return self::SUCCESS;
    }
}
