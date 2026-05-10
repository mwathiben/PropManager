<?php

namespace App\Http\Requests;

use App\Rules\SecureFile;
use Illuminate\Foundation\Http\FormRequest;

class StoreLeaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->isLandlord() || auth()->user()->isCaretaker();
    }

    public function rules(): array
    {
        // VALID-8: decimal:0,2 + max on every money field. Pre-fix, numeric
        // accepted scientific notation (1e308) which truncated at the
        // DB layer's DECIMAL(12,2) — silently corrupting rent/deposit values.
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'phone' => 'required|string|max:20',
            'id_number' => 'nullable|string',
            'rent_amount' => ['required', 'decimal:0,2', 'min:0', 'max:9999999.99'],
            'service_charge' => ['nullable', 'decimal:0,2', 'min:0', 'max:9999999.99'],
            'deposit_amount' => ['required', 'decimal:0,2', 'min:0', 'max:9999999.99'],
            'start_date' => 'required|date',
            // UPLOAD-7: SecureFile sniffs the actual MIME via finfo and
            // checks magic bytes; the 'mimes:' rule trusted only the
            // client-provided extension and would happily accept a
            // .php webshell renamed to .pdf.
            'lease_doc' => [
                'nullable',
                'file',
                new SecureFile(maxSizeMb: 2, allowedMimes: ['application/pdf', 'image/jpeg', 'image/png'], allowedExtensions: ['pdf', 'jpg', 'jpeg', 'png']),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'email.unique' => __('validation.custom.email.unique'),
            'rent_amount.min' => __('validation.custom.rent_amount.min'),
            'deposit_amount.min' => __('validation.custom.deposit_amount.min'),
        ];
    }
}
