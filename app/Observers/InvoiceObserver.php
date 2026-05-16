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

            // Phase-31 ONB-TTFI-1: first invoice = activation funnel step 5.
            app(\App\Services\Onboarding\OnboardingMilestoneRecorder::class)
                ->record(
                    landlordId: (int) $invoice->landlord_id,
                    milestone: \App\Models\OnboardingMilestone::FIRST_INVOICE,
                    metadata: ['invoice_id' => $invoice->id],
                );
        }
    }

    public function updated(Invoice $invoice): void
    {
        $originalLandlordId = $invoice->getOriginal('landlord_id');

        if ($originalLandlordId && $originalLandlordId !== $invoice->landlord_id) {
            FinanceCacheService::invalidateForLandlord($originalLandlordId);
        }

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
