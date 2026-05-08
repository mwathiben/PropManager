<?php

namespace App\Http\Requests\MoveOut;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMoveOutDeductionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $moveOut = $this->route('moveOut');
        if (! $moveOut) {
            return false;
        }

        $user = $this->user();
        $landlordId = $user->isCaretaker() ? $user->landlord_id : $user->id;

        return $moveOut->landlord_id === $landlordId;
    }

    public function rules(): array
    {
        $moveOut = $this->route('moveOut');
        $buildingId = $moveOut?->lease?->unit?->building_id;
        $user = $this->user();
        $landlordId = $user->isCaretaker() ? $user->landlord_id : $user->id;

        return [
            'category_id' => [
                'nullable',
                'integer',
                Rule::exists('move_out_deduction_categories', 'id')
                    ->where(function ($query) use ($buildingId, $landlordId) {
                        $query->where('is_active', true)
                            ->where(function ($q) use ($buildingId, $landlordId) {
                                $q->where(function ($sub) use ($buildingId) {
                                    $sub->where('building_id', $buildingId);
                                })->orWhere(function ($sub) use ($landlordId) {
                                    $sub->whereNull('building_id')
                                        ->where('landlord_id', $landlordId);
                                });
                            });
                    }),
            ],
            'description' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0',
            'notes' => 'nullable|string|max:500',
            'photo' => 'nullable|image|max:5120',
        ];
    }

    public function messages(): array
    {
        return [
            'category_id.exists' => 'The selected category is not available for this move-out.',
        ];
    }
}
