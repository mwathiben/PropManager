<?php

namespace App\Http\Requests\MoveOut;

use App\Models\Building;
use App\Models\MoveOutDeductionCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateMoveOutDeductionCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $category = $this->route('move_out_category');

        return $this->user()->can('update', $category);
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'default_amount' => 'sometimes|required|numeric|min:0',
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

            if ($this->has('building_id') && $this->buildingBelongsToOtherLandlord()) {
                $validator->errors()->add('building_id', 'The selected building does not belong to you.');

                return;
            }

            if ($this->has('name') && $this->duplicateCategoryExists()) {
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
        $category = $this->route('move_out_category');
        $buildingId = $this->has('building_id') ? $this->building_id : $category->building_id;

        $query = MoveOutDeductionCategory::where('landlord_id', $this->user()->id)
            ->where('name', $this->name)
            ->where('id', '!=', $category->id);

        if ($buildingId) {
            $query->where('building_id', $buildingId);
        } else {
            $query->whereNull('building_id');
        }

        return $query->exists();
    }
}
