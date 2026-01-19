<?php

namespace App\Http\Requests\Payment;

use Illuminate\Foundation\Http\FormRequest;

class InitializePaystackRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $invoice = $this->route('invoice');
        $maxAmount = $invoice ? ($invoice->total_due - $invoice->amount_paid) : PHP_INT_MAX;

        return [
            'amount' => "required|numeric|min:1|max:{$maxAmount}",
        ];
    }

    public function messages(): array
    {
        return [
            'amount.max' => 'The payment amount cannot exceed the remaining balance.',
        ];
    }
}
