<?php

namespace App\Http\Requests;

use App\Models\Payment;
use App\Services\RefundService;
use Illuminate\Foundation\Http\FormRequest;

class RefundRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = auth()->user();

        if (! $user->isLandlord() && ! $user->isCaretaker()) {
            return false;
        }

        if ($this->payment_id) {
            $payment = Payment::find($this->payment_id);
            if ($payment) {
                $landlordId = $user->isCaretaker() ? $user->landlord_id : $user->id;

                return $payment->landlord_id === $landlordId;
            }
        }

        return true;
    }

    public function rules(): array
    {
        // VALID-8: decimal:0,2 + max — see RecordPaymentRequest for the
        // rationale. amount > refundableAmount is also enforced in
        // withValidator below, but that runs after coercion; the validator
        // here rejects scientific notation up front.
        return [
            'payment_id' => 'required|exists:payments,id',
            'amount' => ['required', 'decimal:0,2', 'min:0.01', 'max:9999999.99'],
            'reason' => 'required|string|max:500',
            'refund_method' => 'required|string|in:original_method,cash,bank_transfer,mobile_money',
            'notes' => 'nullable|string|max:1000',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->payment_id && $this->amount) {
                $payment = Payment::find($this->payment_id);

                if ($payment) {
                    $refundService = app(RefundService::class);
                    $refundableAmount = $refundService->getRefundableAmount($payment);

                    if ($this->amount > $refundableAmount) {
                        $validator->errors()->add(
                            'amount',
                            "Amount cannot exceed the refundable amount of {$refundableAmount}."
                        );
                    }
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'payment_id.required' => 'Please select a payment to refund.',
            'payment_id.exists' => 'The selected payment does not exist.',
            'amount.required' => 'Refund amount is required.',
            'amount.numeric' => 'Refund amount must be a number.',
            'amount.min' => 'Refund amount must be at least 0.01.',
            'reason.required' => 'Refund reason is required.',
            'reason.max' => 'Reason cannot exceed 500 characters.',
            'refund_method.required' => 'Refund method is required.',
            'refund_method.in' => 'Invalid refund method selected.',
            'notes.max' => 'Notes cannot exceed 1000 characters.',
        ];
    }
}
