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
            'management_fee_type' => 'nullable|in:none,percentage,flat',
            'management_fee_value' => 'nullable|numeric|min:0|max:9999999999',
        ];
    }

    public function withValidator(\Illuminate\Validation\Validator $validator): void
    {
        $validator->sometimes('management_fee_value', 'max:100', fn ($input) => $input->management_fee_type === 'percentage');
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('management_fee_type') === 'none') {
            $this->merge(['management_fee_value' => 0]);
        }
    }
}
