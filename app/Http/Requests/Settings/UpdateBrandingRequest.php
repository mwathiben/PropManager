<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBrandingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->isScopeOwner();
    }

    public function rules(): array
    {
        return [
            'invoice_number_format' => 'required|string|max:50',
            'invoice_footer_text' => 'nullable|string|max:500',
            'receipt_footer_text' => 'nullable|string|max:500',
        ];
    }
}
