<?php

namespace App\Http\Requests;

use App\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = auth()->user();

        return $user->isScopeOwner() || $user->isCaretaker();
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('payment_method')) {
            $this->merge([
                'payment_method' => PaymentMethod::normalize($this->payment_method),
            ]);
        }
    }

    public function rules(): array
    {
        // VALID-2: scope tenant_id and invoice_id to the caller's landlord.
        // Pre-fix, exists:users,id and exists:invoices,id allowed a landlord to
        // record a payment against another landlord's invoice or tenant —
        // direct cross-tenant financial corruption. VALID-8: explicit max
        // and decimal:0,2 reject scientific notation and DECIMAL(12,2) overflow.
        $user = auth()->user();
        $landlordId = $user?->isCaretaker() ? (int) $user->landlord_id : (int) $user?->id;

        return [
            'tenant_id' => [
                'required_without:invoice_id',
                'nullable',
                Rule::exists('users', 'id')
                    ->where('landlord_id', $landlordId)
                    ->where('role', 'tenant'),
            ],
            'invoice_id' => [
                'nullable',
                Rule::exists('invoices', 'id')->where('landlord_id', $landlordId),
            ],
            'amount' => ['required', 'decimal:0,2', 'min:0.01', 'max:9999999.99'],
            'payment_method' => ['required', Rule::in(PaymentMethod::values())],
            'payment_date' => 'required|date|before_or_equal:today',
            'reference' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:500',
            'is_unallocated' => 'boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'tenant_id.required_without' => 'Please select a tenant or an invoice.',
            'tenant_id.exists' => 'The selected tenant does not exist.',
            'invoice_id.exists' => 'The selected invoice does not exist.',
            'amount.required' => 'Payment amount is required.',
            'amount.numeric' => 'Payment amount must be a number.',
            'amount.min' => 'Payment amount must be at least 0.01.',
            'payment_method.required' => 'Payment method is required.',
            'payment_method.in' => 'Invalid payment method selected.',
            'payment_date.required' => 'Payment date is required.',
            'payment_date.date' => 'Invalid payment date format.',
            'payment_date.before_or_equal' => 'Payment date cannot be in the future.',
            'reference.max' => 'Reference cannot exceed 255 characters.',
            'notes.max' => 'Notes cannot exceed 500 characters.',
        ];
    }
}
