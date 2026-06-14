<?php

namespace App\Http\Requests\Finance;

use App\Enums\ManagementFeeBase;
use App\Enums\ManagementFeeFlatCadence;
use App\Enums\ManagementFeeType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePropertyOwnerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('owner'));
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:150',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:30',
            'id_number' => 'nullable|string|max:50',
            'notes' => 'nullable|string|max:1000',
            'is_active' => 'boolean',
            'management_fee_type' => ['nullable', Rule::enum(ManagementFeeType::class)],
            'management_fee_value' => 'nullable|numeric|min:0|max:9999999999',
            'management_fee_base' => ['nullable', Rule::enum(ManagementFeeBase::class)],
            'management_fee_flat_cadence' => ['nullable', Rule::enum(ManagementFeeFlatCadence::class)],
        ];
    }

    public function withValidator(\Illuminate\Validation\Validator $validator): void
    {
        // A percentage above 100 would hand the owner a negative net — cap it.
        $validator->sometimes('management_fee_value', 'max:100', fn ($input) => $input->management_fee_type === ManagementFeeType::Percentage->value);
    }

    protected function prepareForValidation(): void
    {
        // "No fee" must not carry a stale amount through (the editor hides the input).
        if ($this->input('management_fee_type') === ManagementFeeType::None->value) {
            $this->merge(['management_fee_value' => 0]);
        }
    }
}
