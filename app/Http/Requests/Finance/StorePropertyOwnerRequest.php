<?php

namespace App\Http\Requests\Finance;

use App\Models\PropertyOwner;
use Illuminate\Foundation\Http\FormRequest;

class StorePropertyOwnerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', PropertyOwner::class);
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:150',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:30',
            'id_number' => 'nullable|string|max:50',
            'notes' => 'nullable|string|max:1000',
        ];
    }
}
