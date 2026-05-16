<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\DepositRefundRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;

/**
 * Phase-28 TENANT-PAY-3: tenant-initiated deposit refund request.
 *
 * The tenant submits the request after MoveOut. Validates that the
 * lease belongs to this tenant + only one active (non-terminal)
 * request per lease + payment_details shape matches payment_method.
 *
 * Landlord approval/mark-paid flow ships as a follow-up — see
 * audit_closeout deferral "TENANT-PAY-3 landlord approval UI".
 */
class DepositRefundController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $tenant = $request->user();

        $data = $request->validate([
            'lease_id' => [
                'required',
                Rule::exists('leases', 'id')->where(fn ($q) => $q->where('tenant_id', $tenant->id)),
            ],
            'requested_amount_cents' => ['required', 'integer', 'min:1'],
            'payment_method' => ['required', Rule::in([
                DepositRefundRequest::METHOD_MPESA,
                DepositRefundRequest::METHOD_BANK_TRANSFER,
                DepositRefundRequest::METHOD_CHEQUE,
            ])],
            'payment_details' => ['required', 'array'],
            'payment_details.phone' => ['required_if:payment_method,mpesa', 'string', 'regex:/^\+254[17]\d{8}$/'],
            'payment_details.bank_name' => ['required_if:payment_method,bank_transfer', 'string', 'max:120'],
            'payment_details.account_number' => ['required_if:payment_method,bank_transfer', 'string', 'max:40'],
            'payment_details.branch' => ['nullable', 'string', 'max:120'],
            'payment_details.name' => ['required_if:payment_method,cheque', 'string', 'max:120'],
        ]);

        $activeStatuses = [
            DepositRefundRequest::STATUS_SUBMITTED,
            DepositRefundRequest::STATUS_UNDER_REVIEW,
            DepositRefundRequest::STATUS_APPROVED,
        ];
        $exists = DepositRefundRequest::where('lease_id', $data['lease_id'])
            ->whereIn('status', $activeStatuses)
            ->exists();
        abort_if($exists, 422, 'An active deposit refund request already exists for this lease.');

        DepositRefundRequest::create([
            'landlord_id' => $tenant->landlord_id,
            'tenant_id' => $tenant->id,
            'lease_id' => $data['lease_id'],
            'requested_amount_cents' => $data['requested_amount_cents'],
            'payment_method' => $data['payment_method'],
            'payment_details' => $data['payment_details'],
            'status' => DepositRefundRequest::STATUS_SUBMITTED,
            'submitted_at' => now(),
        ]);

        return Redirect::route('tenant.finances.index')
            ->with('success', __('tenant.deposit_refund.submitted'));
    }
}
