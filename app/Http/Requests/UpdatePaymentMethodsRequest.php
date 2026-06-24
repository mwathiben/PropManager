<?php

namespace App\Http\Requests;

use App\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePaymentMethodsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = auth()->user();

        return $user->isScopeOwner() || $user->isCaretaker();
    }

    public function rules(): array
    {
        return [
            'accepted_payment_methods' => 'required|array',
            'accepted_payment_methods.*' => ['string', Rule::in(PaymentMethod::values())],
            'bank_name' => 'nullable|string|max:100',
            'bank_account_name' => 'nullable|string|max:100',
            'bank_account_number' => 'nullable|string|max:50',
            'bank_branch' => 'nullable|string|max:100',
            'mpesa_shortcode_type' => 'nullable|string|in:paybill,till',
            'mpesa_shortcode' => 'nullable|string|max:20',
            'mpesa_account_name' => 'nullable|string|max:100',
            'mpesa_passkey' => 'nullable|string|max:255',
        ];
    }

    public function messages(): array
    {
        return [
            'accepted_payment_methods.required' => 'Please select at least one payment method.',
            'accepted_payment_methods.array' => 'Invalid payment methods format.',
            'accepted_payment_methods.*.in' => 'Invalid payment method selected.',
            'bank_name.max' => 'Bank name cannot exceed 100 characters.',
            'bank_account_name.max' => 'Account name cannot exceed 100 characters.',
            'bank_account_number.max' => 'Account number cannot exceed 50 characters.',
            'bank_branch.max' => 'Branch cannot exceed 100 characters.',
            'mpesa_shortcode_type.in' => 'M-Pesa shortcode type must be paybill or till.',
            'mpesa_shortcode.max' => 'M-Pesa shortcode cannot exceed 20 characters.',
            'mpesa_account_name.max' => 'M-Pesa account name cannot exceed 100 characters.',
            'mpesa_passkey.max' => 'M-Pesa passkey cannot exceed 255 characters.',
        ];
    }
}
