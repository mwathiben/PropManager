<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email,'.$this->route('tenant')->id,
            'phone' => 'required|string|max:20',
            'id_number' => 'nullable|string|max:20',
        ];
    }
}
