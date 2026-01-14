<?php

namespace App\Observers;

use App\Models\Invoice;
use App\Services\FinanceCacheService;

class InvoiceObserver
{
    public function created(Invoice $invoice): void
    {
        $this->invalidateCache($invoice);
    }

    public function updated(Invoice $invoice): void
    {
        $this->invalidateCache($invoice);
    }

    public function deleted(Invoice $invoice): void
    {
        $this->invalidateCache($invoice);
    }

    private function invalidateCache(Invoice $invoice): void
    {
        if ($invoice->landlord_id) {
            FinanceCacheService::invalidateForLandlord($invoice->landlord_id);
        }
    }
}
