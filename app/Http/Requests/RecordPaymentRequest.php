<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RecordPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('recordPayment', $this->route('invoice'));
    }

    public function rules(): array
    {
        return [
            // VALID-8: explicit decimal:0,2 + max rejects scientific notation
            // and DECIMAL(12,2) overflow that would otherwise silently
            // truncate at the DB layer.
            'amount' => ['required', 'decimal:0,2', 'min:0.01', 'max:9999999.99'],
            'payment_method' => 'required|string|in:cash,bank_transfer,mobile_money,paystack',
            'reference' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:500',
        ];
    }
}
