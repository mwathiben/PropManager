<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use Illuminate\Console\Command;

class MarkOverdueInvoices extends Command
{
    protected $signature = 'invoices:mark-overdue';

    protected $description = 'Mark unpaid invoices as overdue after due date';

    public function handle(): int
    {
        $count = Invoice::whereIn('status', ['draft', 'sent', 'partial'])
            ->whereDate('due_date', '<', now())
            ->update(['status' => 'overdue']);

        $this->info("Marked {$count} invoices as overdue.");

        return self::SUCCESS;
    }
}
