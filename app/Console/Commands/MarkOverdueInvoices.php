<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use Illuminate\Console\Command;

class MarkOverdueInvoices extends Command
{
    /**
     * Phase-19 POLICY-9: guard rails on a cross-tenant cron.
     *   --landlord-id — scope to a single landlord (operator override).
     *   --dry-run     — report the count that WOULD update, mutate nothing.
     *   --confirm     — required for interactive runs (skipped by scheduler).
     */
    protected $signature = 'invoices:mark-overdue {--landlord-id=} {--dry-run} {--confirm}';

    protected $description = 'Mark unpaid invoices as overdue after due date (cross-tenant; --landlord-id to scope)';

    public function handle(): int
    {
        $landlordId = $this->option('landlord-id') !== null ? (int) $this->option('landlord-id') : null;
        $dryRun = (bool) $this->option('dry-run');

        if (! $dryRun && ! $this->option('confirm') && $this->input->isInteractive()) {
            $this->error('Refusing to run without --confirm in interactive mode (POLICY-9). Add --dry-run to preview.');

            return self::FAILURE;
        }

        $query = Invoice::whereIn('status', ['draft', 'sent', 'partial'])
            ->whereDate('due_date', '<', now())
            ->when($landlordId !== null, fn ($q) => $q->where('landlord_id', $landlordId));

        if ($dryRun) {
            $count = $query->count();
            $this->info("[DRY-RUN] would mark {$count} invoices as overdue — no DB writes.");

            return self::SUCCESS;
        }

        $count = $query->update(['status' => 'overdue']);

        $this->info("Marked {$count} invoices as overdue.");

        return self::SUCCESS;
    }
}
