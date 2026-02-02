<?php

namespace App\Http\Requests\Kyc;

use App\Models\KycRequirement;
use Illuminate\Foundation\Http\FormRequest;

class UpdateKycRequirementRequest extends FormRequest
{
    public function authorize(): bool
    {
        $requirement = $this->route('kycRequirement');

        if (! $requirement instanceof KycRequirement) {
            return false;
        }

        return $this->user()->can('update', $requirement);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'label' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_required' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
