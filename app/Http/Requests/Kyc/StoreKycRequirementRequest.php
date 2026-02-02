<?php

namespace App\Http\Requests\Kyc;

use App\Models\Building;
use App\Models\KycRequirement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreKycRequirementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', KycRequirement::class);
    }

    /**
     * @return array<string, string>
     */
    public function rules(): array
    {
        return [
            'requirement_type' => 'required|string|max:50',
            'label' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'building_id' => 'nullable|integer|exists:buildings,id',
            'is_required' => 'sometimes|boolean',
            'is_active' => 'sometimes|boolean',
            'sort_order' => 'sometimes|integer|min:0',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            if ($this->buildingBelongsToOtherLandlord()) {
                $validator->errors()->add('building_id', 'The selected building does not belong to you.');

                return;
            }

            if ($this->duplicateRequirementTypeExists()) {
                $validator->errors()->add('requirement_type', 'A requirement with this type already exists for this scope.');
            }
        });
    }

    private function buildingBelongsToOtherLandlord(): bool
    {
        if (! $this->building_id) {
            return false;
        }

        $building = Building::find($this->building_id);

        return $building && $building->landlord_id !== $this->user()->id;
    }

    private function duplicateRequirementTypeExists(): bool
    {
        $query = KycRequirement::where('landlord_id', $this->user()->id)
            ->where('requirement_type', $this->requirement_type);

        if ($this->building_id) {
            $query->where('building_id', $this->building_id);
        } else {
            $query->whereNull('building_id');
        }

        return $query->exists();
    }
}
