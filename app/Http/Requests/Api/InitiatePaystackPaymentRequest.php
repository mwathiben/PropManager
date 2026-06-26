<?php

namespace App\Http\Requests\Api;

use App\Enums\InvoiceStatus;
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

        $user = $this->user();

        // Phase-99: a water client pays their own water-connection invoice.
        if ($user?->isWaterClient()) {
            return $invoice->isWaterClientInvoice()
                && $invoice->waterConnection?->user_id === $user->id;
        }

        return $invoice->lease?->tenant_id === $user?->id;
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
            'callback_url.in' => 'Callback URL must be on this site.',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $invoice = Invoice::find($this->invoice_id);

            if ($invoice) {
                $this->validateInvoiceAmount($validator, $invoice);
            }

            $this->validateCallbackUrl($validator);
        });
    }

    private function validateInvoiceAmount($validator, Invoice $invoice): void
    {
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

    private function validateCallbackUrl($validator): void
    {
        // CRYPTO-10: callback_url is forwarded to Paystack and the
        // payment-status redirect lands the user there. An off-site
        // callback is an open redirect and a payment-status leak
        // vector. Lock it to the configured app.url host.
        $callbackUrl = $this->input('callback_url');
        if (! $callbackUrl) {
            return;
        }

        $callbackHost = parse_url($callbackUrl, PHP_URL_HOST);
        $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);
        if (! $callbackHost || ! $appHost || strcasecmp($callbackHost, $appHost) !== 0) {
            $validator->errors()->add(
                'callback_url',
                'Callback URL host must match the application host.'
            );
        }
    }
}
