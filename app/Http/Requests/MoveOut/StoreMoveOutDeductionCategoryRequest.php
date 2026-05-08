<?php

namespace App\Http\Requests\MoveOut;

use App\Models\Building;
use App\Models\MoveOutDeductionCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreMoveOutDeductionCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', MoveOutDeductionCategory::class);
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'default_amount' => 'required|numeric|min:0',
            'always_apply' => 'sometimes|boolean',
            'is_active' => 'sometimes|boolean',
            'building_id' => 'nullable|integer|exists:buildings,id',
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

            if ($this->duplicateCategoryExists()) {
                $validator->errors()->add('name', 'A category with this name already exists in this scope.');
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

    private function duplicateCategoryExists(): bool
    {
        $query = MoveOutDeductionCategory::where('landlord_id', $this->user()->id)
            ->where('name', $this->name);

        if ($this->building_id) {
            $query->where('building_id', $this->building_id);
        } else {
            $query->whereNull('building_id');
        }

        return $query->exists();
    }
}
