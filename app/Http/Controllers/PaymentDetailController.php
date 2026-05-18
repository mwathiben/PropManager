<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Payment;
use Inertia\Inertia;
use Inertia\Response;

class PaymentDetailController extends Controller
{
    public function show(Payment $payment): Response
    {
        $this->authorize('view', $payment);

        $payment->loadMissing([
            'invoice:id,invoice_number,lease_id,total_due,amount_paid,status',
            'lease' => function ($query) {
                $query->withTrashed();
            },
            'lease.tenant:id,name,email,mobile_number',
            'lease.unit:id,unit_number,building_id',
            'lease.unit.building:id,name',
        ]);

        $lease = $payment->lease;
        $leaseState = match (true) {
            $lease === null => 'unknown',
            $lease->deleted_at !== null => 'soft_deleted',
            (bool) $lease->is_active === false => 'ended',
            default => 'active',
        };

        return Inertia::render('Payments/Detail', [
            'payment' => [
                'id' => $payment->id,
                'amount' => (float) $payment->amount,
                'currency' => 'KES',
                'payment_method' => $payment->payment_method,
                'reference' => $payment->reference,
                'payment_date' => $payment->payment_date?->toDateString(),
                'created_at' => $payment->created_at?->toIso8601String(),
                'is_voided' => (bool) $payment->is_voided,
                'voided_at' => $payment->voided_at?->toIso8601String(),
                'void_reason' => $payment->void_reason,
            ],
            'invoice' => $payment->invoice ? [
                'id' => $payment->invoice->id,
                'invoice_number' => $payment->invoice->invoice_number,
                'total_due' => (float) $payment->invoice->total_due,
                'amount_paid' => (float) $payment->invoice->amount_paid,
                'status' => $payment->invoice->status,
            ] : null,
            'lease' => $lease ? [
                'id' => $lease->id,
                'state' => $leaseState,
                'rent_amount' => (float) $lease->rent_amount,
                'tenant' => $lease->tenant ? [
                    'id' => $lease->tenant->id,
                    'name' => $lease->tenant->name,
                    'email' => $lease->tenant->email,
                    'mobile_number' => $lease->tenant->mobile_number,
                ] : null,
                'unit' => $lease->unit ? [
                    'id' => $lease->unit->id,
                    'unit_number' => $lease->unit->unit_number,
                    'building' => $lease->unit->building ? [
                        'id' => $lease->unit->building->id,
                        'name' => $lease->unit->building->name,
                    ] : null,
                ] : null,
            ] : null,
        ]);
    }
}
