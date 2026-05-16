<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\DepositRefundRequest;
use App\Models\PaymentPlan;
use App\Models\User;

/**
 * Phase-28 TENANT-CI-2: per-tenant abilities map shared on every
 * Inertia request. Mirrors AuthAbilities::for shape — array<string,
 * bool> so Vue `can('statement:download')` reads cheaply.
 *
 * Every key here is also listed in docs/runbooks/tenant-portal.md as
 * a contract; the Phase28CiTest enforces parity between this method's
 * keys and the runbook.
 */
class TenantAbilities
{
    public const ABILITY_KEYS = [
        'statement:download',
        'statement:email',
        'documents:view_kyc',
        'tickets:create',
        'payment_plan:request',
        'deposit:request_refund',
    ];

    /**
     * @return array<string, bool>
     */
    public static function for(User $user): array
    {
        $hasCompletedKyc = $user->hasCompletedKyc();
        $activeLease = $user->lease;
        $hasUnpaidInvoice = $activeLease
            ? $activeLease->invoices()
                ->whereIn('status', ['sent', 'partial', 'overdue'])
                ->exists()
            : false;
        $moveOutComplete = $activeLease
            ? \App\Models\MoveOut::query()
                ->where('lease_id', $activeLease->id)
                ->where('status', \App\Enums\MoveOutStatus::Completed)
                ->exists()
            : false;
        $hasActiveRefund = DepositRefundRequest::where('tenant_id', $user->id)
            ->whereIn('status', [
                DepositRefundRequest::STATUS_SUBMITTED,
                DepositRefundRequest::STATUS_UNDER_REVIEW,
                DepositRefundRequest::STATUS_APPROVED,
            ])
            ->exists();
        $hasActivePlan = PaymentPlan::where('tenant_id', $user->id)
            ->whereIn('status', [PaymentPlan::STATUS_REQUESTED, PaymentPlan::STATUS_APPROVED])
            ->exists();

        return [
            'statement:download' => true,
            'statement:email' => true,
            'documents:view_kyc' => $hasCompletedKyc,
            'tickets:create' => true,
            'payment_plan:request' => $hasUnpaidInvoice && ! $hasActivePlan,
            'deposit:request_refund' => $moveOutComplete && ! $hasActiveRefund,
        ];
    }
}
