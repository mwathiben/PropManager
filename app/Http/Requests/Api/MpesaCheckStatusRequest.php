<?php

namespace App\Http\Requests\Api;

use App\Models\Invoice;
use Illuminate\Foundation\Http\FormRequest;

class MpesaCheckStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        if (! $this->user()) {
            return false;
        }

        $invoice = Invoice::find($this->invoice_id);

        // The 'exists' rule will reject missing invoices in validation; here
        // we authorize even on null so validation gets to surface the right
        // error message rather than a generic 403.
        if (! $invoice) {
            return true;
        }

        // Tenants may only check status for invoices on their own lease.
        return $invoice->lease?->tenant_id === $this->user()->id;
    }

    public function rules(): array
    {
        return [
            'checkout_request_id' => 'required|string',
            'invoice_id' => 'required|exists:invoices,id',
        ];
    }

    public function messages(): array
    {
        return [
            'invoice_id.required' => 'Invoice ID is required to load payment configuration.',
            'invoice_id.exists' => 'The specified invoice does not exist.',
        ];
    }
}
