<?php

namespace App\Http\Requests\Kyc;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreKycRequirementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isLandlord();
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $landlordId = $this->user()->id;
        $buildingId = $this->input('building_id');

        return [
            'requirement_type' => [
                'required',
                'string',
                'max:50',
                Rule::unique('kyc_requirements')
                    ->where('landlord_id', $landlordId)
                    ->where('building_id', $buildingId),
            ],
            'label' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'building_id' => [
                'nullable',
                Rule::exists('buildings', 'id')->where('landlord_id', $landlordId),
            ],
            'is_required' => ['boolean'],
            'is_active' => ['boolean'],
            'sort_order' => ['integer', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'requirement_type.unique' => 'A requirement with this type already exists for this scope.',
            'building_id.exists' => 'The selected building does not belong to you.',
        ];
    }
}
