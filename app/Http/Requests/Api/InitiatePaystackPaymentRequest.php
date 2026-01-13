<?php

namespace App\Http\Requests\Api;

use App\Models\Invoice;
use Illuminate\Foundation\Http\FormRequest;

class InitiatePaystackPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $invoice = Invoice::find($this->invoice_id);

        if (! $invoice) {
            return true;
        }

        return $invoice->lease?->tenant_id === $this->user()?->id;
    }

    public function rules(): array
    {
        return [
            'invoice_id' => 'required|integer|exists:invoices,id',
            'amount' => 'required|numeric|min:1|max:500000',
            'callback_url' => 'nullable|url|max:500',
        ];
    }

    public function messages(): array
    {
        return [
            'invoice_id.required' => 'Invoice ID is required.',
            'invoice_id.exists' => 'Invoice not found.',
            'amount.required' => 'Payment amount is required.',
            'amount.min' => 'Payment amount must be at least KES 1.',
            'amount.max' => 'Payment amount cannot exceed KES 500,000 per transaction.',
            'callback_url.url' => 'Callback URL must be a valid URL.',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $invoice = Invoice::find($this->invoice_id);

            if ($invoice) {
                $remainingDue = $invoice->total_due - $invoice->amount_paid;

                if ($this->amount > $remainingDue) {
                    $validator->errors()->add(
                        'amount',
                        "Amount exceeds remaining balance of KES {$remainingDue}."
                    );
                }

                if ($invoice->status === 'paid') {
                    $validator->errors()->add('invoice_id', 'This invoice is already fully paid.');
                }
            }
        });
    }
}
