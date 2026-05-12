<?php

namespace App\Observers;

use App\Exceptions\DataIntegrityException;
use App\Models\Invoice;
use App\Models\Lease;
use App\Models\Payment;
use App\Models\User;
use App\Services\FinanceCacheService;
use App\Services\PaymentLinkService;

class PaymentObserver
{
    public function __construct(
        protected PaymentLinkService $paymentLinkService
    ) {}

    /**
     * Phase-18 DATA-5: cross-tenant FK consistency. Pre-Phase-18 the
     * payment.tenant_id, payment.lease_id, payment.invoice_id, and
     * payment.landlord_id could in principle disagree on landlord_id.
     * The TenantScope trait filters READS to a single landlord_id but
     * the WRITE path had no DB-level check (MySQL CHECK constraints
     * can't reference other tables). This observer enforces the
     * invariant at the application layer: any inserted/updated
     * payment whose related lease/invoice/tenant has a different
     * landlord_id raises DataIntegrityException.
     */
    public function creating(Payment $payment): void
    {
        $this->assertCrossTenantConsistency($payment);
    }

    public function updating(Payment $payment): void
    {
        if ($payment->isDirty(['tenant_id', 'lease_id', 'invoice_id', 'landlord_id'])) {
            $this->assertCrossTenantConsistency($payment);
        }
    }

    public function created(Payment $payment): void
    {
        $this->invalidateAndWarmCache($payment);
        $this->revokePaymentLinks($payment);
    }

    public function updated(Payment $payment): void
    {
        $this->invalidateCache($payment);
    }

    public function deleted(Payment $payment): void
    {
        $this->invalidateCache($payment);
    }

    private function invalidateAndWarmCache(Payment $payment): void
    {
        if ($payment->landlord_id) {
            FinanceCacheService::invalidateAndWarm($payment->landlord_id);
        }
    }

    private function invalidateCache(Payment $payment): void
    {
        if ($payment->landlord_id) {
            FinanceCacheService::invalidateForLandlord($payment->landlord_id);
        }
    }

    private function revokePaymentLinks(Payment $payment): void
    {
        if ($payment->invoice_id && $payment->landlord_id) {
            $this->paymentLinkService->revokeForInvoice(
                $payment->invoice_id,
                $payment->landlord_id,
            );
        }
    }

    /**
     * Phase-18 DATA-5: assert payment.tenant_id, lease_id, invoice_id
     * and landlord_id all agree on the same landlord. Uses
     * withoutGlobalScope so the consistency check can resolve rows
     * even when the actor's scope is something else (e.g. super-admin
     * impersonation flow).
     */
    private function assertCrossTenantConsistency(Payment $payment): void
    {
        $expectedLandlordId = $payment->landlord_id;
        if (! $expectedLandlordId) {
            return; // landlord_id will be backfilled or validated by another path
        }

        if ($payment->lease_id) {
            $leaseLandlord = Lease::withoutGlobalScope('landlord')
                ->whereKey($payment->lease_id)
                ->value('landlord_id');
            if ($leaseLandlord !== null && (int) $leaseLandlord !== (int) $expectedLandlordId) {
                throw new DataIntegrityException(
                    message: "Payment.lease_id ({$payment->lease_id}) belongs to landlord {$leaseLandlord} but payment.landlord_id is {$expectedLandlordId}",
                    errorCode: 'PAYMENT_CROSS_TENANT_LEASE_MISMATCH',
                    context: ['payment_landlord_id' => $expectedLandlordId, 'lease_landlord_id' => $leaseLandlord],
                );
            }
        }

        if ($payment->invoice_id) {
            $invoiceLandlord = Invoice::withoutGlobalScope('landlord')
                ->whereKey($payment->invoice_id)
                ->value('landlord_id');
            if ($invoiceLandlord !== null && (int) $invoiceLandlord !== (int) $expectedLandlordId) {
                throw new DataIntegrityException(
                    message: "Payment.invoice_id ({$payment->invoice_id}) belongs to landlord {$invoiceLandlord} but payment.landlord_id is {$expectedLandlordId}",
                    errorCode: 'PAYMENT_CROSS_TENANT_INVOICE_MISMATCH',
                    context: ['payment_landlord_id' => $expectedLandlordId, 'invoice_landlord_id' => $invoiceLandlord],
                );
            }
        }

        if ($payment->tenant_id) {
            $tenantLandlord = User::withoutGlobalScope('landlord')
                ->whereKey($payment->tenant_id)
                ->value('landlord_id');
            if ($tenantLandlord !== null && (int) $tenantLandlord !== (int) $expectedLandlordId) {
                throw new DataIntegrityException(
                    message: "Payment.tenant_id ({$payment->tenant_id}) belongs to landlord {$tenantLandlord} but payment.landlord_id is {$expectedLandlordId}",
                    errorCode: 'PAYMENT_CROSS_TENANT_TENANT_MISMATCH',
                    context: ['payment_landlord_id' => $expectedLandlordId, 'tenant_landlord_id' => $tenantLandlord],
                );
            }
        }
    }
}
