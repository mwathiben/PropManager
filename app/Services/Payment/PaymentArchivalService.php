<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Models\ArchivedPayment;
use App\Models\AuditLog;
use App\Models\Payment;
use App\Models\Refund;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class PaymentArchivalService
{
    public function archivePayment(Payment $payment): ArchivedPayment
    {
        $relatedData = $this->snapshotRelatedData($payment);

        $archived = ArchivedPayment::create([
            'original_payment_id' => $payment->id,
            'invoice_id' => $payment->invoice_id,
            'lease_id' => $payment->lease_id,
            'landlord_id' => $payment->landlord_id,
            'payout_account_id' => $payment->payout_account_id,
            'amount' => $payment->amount,
            'currency' => $payment->getRawOriginal('currency'),
            'payment_method' => $payment->payment_method,
            'payment_date' => $payment->payment_date,
            'reference' => $payment->reference,
            'paystack_reference' => $payment->paystack_reference,
            'paystack_split_code' => $payment->paystack_split_code,
            'is_split_payment' => $payment->is_split_payment,
            'mpesa_transaction_id' => $payment->mpesa_transaction_id,
            'mpesa_checkout_request_id' => $payment->mpesa_checkout_request_id,
            'intasend_transaction_id' => $payment->intasend_transaction_id,
            'intasend_reference' => $payment->intasend_reference,
            'bank_code' => $payment->bank_code,
            'bank_account_number' => $payment->bank_account_number,
            'bank_transaction_id' => $payment->bank_transaction_id,
            'bank_transaction_date' => $payment->bank_transaction_date,
            'bank_reference' => $payment->bank_reference,
            'reconciliation_status' => $payment->reconciliation_status,
            'reconciliation_matched_at' => $payment->reconciliation_matched_at,
            'is_voided' => $payment->is_voided,
            'voided_at' => $payment->voided_at,
            'void_reason' => $payment->void_reason,
            'notes' => $payment->notes,
            'original_created_at' => $payment->created_at,
            'original_updated_at' => $payment->updated_at,
            'archived_at' => now(),
            'related_data' => $relatedData,
        ]);

        $this->nullRestrictForeignKeys($payment->id);

        $paymentId = $payment->id;
        $landlordId = $payment->landlord_id;
        $paymentAttributes = $payment->attributesToArray();

        $payment->delete();

        $this->createAuditLog($paymentId, $landlordId, $paymentAttributes, $archived->id);

        return $archived;
    }

    public function getRetentionCutoffDate(): Carbon
    {
        return now()->subYears((int) config('security.compliance.data_retention_years', 7));
    }

    private function snapshotRelatedData(Payment $payment): array
    {
        $payment->loadMissing(['platformFee', 'receipt']);

        $data = [];

        if ($payment->platformFee) {
            $data['platform_fee'] = $payment->platformFee->toArray();
        }

        if ($payment->receipt) {
            $data['receipt'] = $payment->receipt->toArray();
        }

        $refunds = Refund::where('payment_id', $payment->id)->get();
        if ($refunds->isNotEmpty()) {
            $data['refunds'] = $refunds->toArray();
        }

        return $data;
    }

    private function nullRestrictForeignKeys(int $paymentId): void
    {
        DB::table('wallet_transactions')
            ->where('payment_id', $paymentId)
            ->update(['payment_id' => null]);

        DB::table('bank_reconciliation_queue')
            ->where('payment_id', $paymentId)
            ->update(['payment_id' => null]);

        DB::table('bank_webhook_logs')
            ->where('processed_payment_id', $paymentId)
            ->update(['processed_payment_id' => null]);
    }

    private function createAuditLog(int $paymentId, int $landlordId, array $oldValues, int $archivedPaymentId): void
    {
        AuditLog::withoutGlobalScope('landlord')->create([
            'user_id' => null,
            'landlord_id' => $landlordId,
            'event_type' => 'archived',
            'auditable_type' => Payment::class,
            'auditable_id' => $paymentId,
            'old_values' => $oldValues,
            'metadata' => [
                'archived_payment_id' => $archivedPaymentId,
                'reason' => 'Data retention policy',
                'retention_years' => config('security.compliance.data_retention_years', 7),
            ],
        ]);
    }
}
