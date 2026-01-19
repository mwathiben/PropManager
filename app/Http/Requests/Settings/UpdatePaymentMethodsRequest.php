<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePaymentMethodsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->isLandlord();
    }

    public function rules(): array
    {
        return [
            'accepted_payment_methods' => 'required|array|min:1',
            'accepted_payment_methods.*' => 'string|in:cash,bank_transfer,mobile_money,paystack',
            'bank_name' => 'nullable|string|max:255',
            'bank_account_name' => 'nullable|string|max:255',
            'bank_account_number' => 'nullable|string|max:50',
            'bank_branch' => 'nullable|string|max:255',
            'mpesa_paybill' => 'nullable|string|max:20',
            'mpesa_account_name' => 'nullable|string|max:255',
            'paystack_enabled' => 'boolean',
        ];
    }
}
