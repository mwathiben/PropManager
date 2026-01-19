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
        $tenantId = $this->route('tenant')?->id;

        return [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email,'.$tenantId,
            'phone' => 'required|string|max:20',
            'id_number' => 'nullable|string|max:20',
        ];
    }
}
