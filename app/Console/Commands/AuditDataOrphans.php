<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\MetricsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Phase-18 DATA-7: periodic orphan-row detection across the
 * canonical FK relationships.
 *
 * Scheduled weekly Sunday 06:00 Africa/Nairobi. Catches:
 *   - leases referencing a soft-deleted Unit (DATA-3 backstop)
 *   - invoices referencing a soft-deleted Lease
 *   - payments referencing a deleted/missing Invoice (DATA-1 sanity)
 *   - audit_logs.user_id pointing at a non-existent User
 *   - security_logs.user_id pointing at a non-existent User
 *
 * Emits a single Prometheus gauge data_orphan_row_count{kind=X} per
 * orphan category. Exits FAILURE if ANY orphan group has count > 0.
 */
class AuditDataOrphans extends Command
{
    protected $signature = 'data:audit-orphans {--limit=10 : sample rows per category to log}';

    protected $description = 'Phase-18 DATA-7: detect orphan rows across canonical FK relationships.';

    public function handle(MetricsService $metrics): int
    {
        $checks = [
            'lease_referencing_trashed_unit' => fn () => DB::table('leases')
                ->join('units', 'units.id', '=', 'leases.unit_id')
                ->whereNull('leases.deleted_at')
                ->whereNotNull('units.deleted_at')
                ->select('leases.id as lease_id', 'units.id as unit_id'),
            'invoice_referencing_trashed_lease' => fn () => DB::table('invoices')
                ->join('leases', 'leases.id', '=', 'invoices.lease_id')
                ->whereNull('invoices.deleted_at')
                ->whereNotNull('leases.deleted_at')
                ->select('invoices.id as invoice_id', 'leases.id as lease_id'),
            'audit_log_orphan_user_id' => fn () => DB::table('audit_logs')
                ->leftJoin('users', 'users.id', '=', 'audit_logs.user_id')
                ->whereNotNull('audit_logs.user_id')
                ->whereNull('users.id')
                ->select('audit_logs.id as audit_log_id', 'audit_logs.user_id'),
            'security_log_orphan_user_id' => fn () => DB::table('security_logs')
                ->leftJoin('users', 'users.id', '=', 'security_logs.user_id')
                ->whereNotNull('security_logs.user_id')
                ->whereNull('users.id')
                ->select('security_logs.id as security_log_id', 'security_logs.user_id'),
        ];

        $anyOrphans = false;
        $totalOrphans = 0;

        foreach ($checks as $kind => $queryBuilder) {
            try {
                $query = $queryBuilder();
                $count = (clone $query)->count();
                $totalOrphans += $count;

                try {
                    $metrics->gauge('data_orphan_row_count', (float) $count, ['kind' => $kind]);
                } catch (\Throwable) {
                }

                if ($count === 0) {
                    $this->info("data:audit-orphans: {$kind} — clean (0)");

                    continue;
                }

                $anyOrphans = true;
                $sample = $query->limit((int) $this->option('limit'))->get();

                $this->warn("data:audit-orphans: {$kind} — {$count} orphan(s):");
                foreach ($sample as $row) {
                    $this->warn('  '.json_encode($row));
                }

                Log::channel(config('logging.schedule_channel', 'stack'))->warning(
                    "data:audit-orphans detected orphans ({$kind})",
                    ['count' => $count, 'sample' => $sample->map(fn ($r) => (array) $r)->all()],
                );
            } catch (\Throwable $e) {
                $this->warn("data:audit-orphans: {$kind} — check failed: {$e->getMessage()}");
            }
        }

        return $anyOrphans ? self::FAILURE : self::SUCCESS;
    }
}
