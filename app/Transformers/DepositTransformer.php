<?php

namespace App\Transformers;

use App\Models\DepositTransaction;
use App\Models\Lease;
use Illuminate\Support\Collection;

class DepositTransformer
{
    public static function transform(Lease $lease): array
    {
        return [
            'id' => $lease->id,
            'amount' => $lease->deposit_amount,
            'status' => $lease->deposit_status,
            'refund_amount' => $lease->deposit_refund_amount,
            'deductions' => $lease->deposit_deductions,
            'deduction_reason' => $lease->deposit_deduction_reason,
            'processed_at' => $lease->deposit_processed_at?->format('Y-m-d'),
            'tenant_name' => $lease->tenant?->name,
            'tenant_email' => $lease->tenant?->email,
            'unit_number' => $lease->unit?->unit_number,
            'building_name' => $lease->unit?->building?->name,
            'start_date' => $lease->start_date?->format('Y-m-d'),
            'end_date' => $lease->end_date?->format('Y-m-d'),
            'is_active' => $lease->is_active,
            'lease' => self::transformLeaseRelations($lease),
            'transactions' => self::transformTransactions($lease->depositTransactions),
        ];
    }

    private static function transformLeaseRelations(Lease $lease): array
    {
        return [
            'id' => $lease->id,
            'tenant' => $lease->tenant ? [
                'id' => $lease->tenant->id,
                'name' => $lease->tenant->name,
            ] : null,
            'unit' => $lease->unit ? [
                'id' => $lease->unit->id,
                'unit_number' => $lease->unit->unit_number,
                'building' => $lease->unit->building?->name,
            ] : null,
        ];
    }

    private static function transformTransactions(Collection $transactions): array
    {
        return $transactions->map(fn (DepositTransaction $t) => [
            'id' => $t->id,
            'type' => $t->type,
            'type_label' => $t->getTypeLabel(),
            'amount' => $t->amount,
            'balance_after' => $t->balance_after,
            'reason' => $t->reason,
            'payment_method' => $t->payment_method,
            'reference' => $t->reference,
            'processed_by' => $t->processedBy?->name,
            'created_at' => $t->created_at->format('Y-m-d H:i'),
        ])->all();
    }
}
