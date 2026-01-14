<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = auth()->user();

        return $user->isLandlord() || $user->isCaretaker();
    }

    public function rules(): array
    {
        return [
            'tenant_id' => 'required_without:invoice_id|nullable|exists:users,id',
            'invoice_id' => 'nullable|exists:invoices,id',
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => 'required|in:cash,bank_transfer,mobile_money,mpesa,cheque',
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
