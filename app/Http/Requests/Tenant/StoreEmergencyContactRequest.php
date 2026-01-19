<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;

class StoreEmergencyContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'relationship' => 'required|string|max:100',
            'phone' => 'required|string|max:20',
            'email' => 'nullable|email|max:255',
            'is_primary' => 'boolean',
        ];
    }
}
