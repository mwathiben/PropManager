<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class MpesaCheckStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
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
