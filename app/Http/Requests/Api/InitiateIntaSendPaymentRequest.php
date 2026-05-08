<?php

namespace App\Http\Requests\Api;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use Illuminate\Foundation\Http\FormRequest;

class InitiateIntaSendPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $invoice = Invoice::find($this->invoice_id);

        if (! $invoice) {
            return false;
        }

        return $invoice->lease?->tenant_id === $this->user()?->id;
    }

    public function rules(): array
    {
        return [
            'invoice_id' => 'required|integer|exists:invoices,id',
            'amount' => 'required|numeric|min:1|max:150000',
            'phone' => ['required', 'string', 'regex:/^(?:254|\+254|0)?[71]\d{8}$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'invoice_id.required' => 'Invoice ID is required.',
            'invoice_id.exists' => 'Invoice not found.',
            'amount.required' => 'Payment amount is required.',
            'amount.min' => 'Payment amount must be at least KES 1.',
            'amount.max' => 'Payment amount cannot exceed KES 150,000 per transaction.',
            'phone.required' => 'Phone number is required.',
            'phone.regex' => 'Invalid Kenyan phone number format. Use 0712345678 or 254712345678.',
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

                if ($invoice->status === InvoiceStatus::Paid) {
                    $validator->errors()->add('invoice_id', 'This invoice is already fully paid.');
                }
            }
        });
    }
}
