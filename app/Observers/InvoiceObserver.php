<?php

namespace App\Observers;

use App\Models\Invoice;
use App\Services\FinanceCacheService;

class InvoiceObserver
{
    public function created(Invoice $invoice): void
    {
        if ($invoice->landlord_id) {
            FinanceCacheService::invalidateAndWarm($invoice->landlord_id);
        }
    }

    public function updated(Invoice $invoice): void
    {
        if ($invoice->landlord_id) {
            FinanceCacheService::invalidateForLandlord($invoice->landlord_id);
        }
    }

    public function deleted(Invoice $invoice): void
    {
        if ($invoice->landlord_id) {
            FinanceCacheService::invalidateForLandlord($invoice->landlord_id);
        }
    }
}
