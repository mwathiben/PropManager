<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateInvoiceSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = auth()->user();

        return $user->isScopeOwner() || $user->isCaretaker();
    }

    public function rules(): array
    {
        return [
            'include_water_charges' => 'required|boolean',
            'include_arrears' => 'required|boolean',
            'auto_generate_monthly' => 'required|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'include_water_charges.required' => 'Water charges setting is required.',
            'include_water_charges.boolean' => 'Water charges must be true or false.',
            'include_arrears.required' => 'Arrears setting is required.',
            'include_arrears.boolean' => 'Arrears must be true or false.',
            'auto_generate_monthly.required' => 'Auto-generate setting is required.',
            'auto_generate_monthly.boolean' => 'Auto-generate must be true or false.',
        ];
    }
}
