<?php

namespace App\Http\Requests\BulkOperations;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class UpdateTargetRentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = Auth::user();

        return $user && ($user->isScopeOwner() || $user->isCaretaker());
    }

    public function rules(): array
    {
        return [
            'unit_ids' => 'required|array|min:1',
            'unit_ids.*' => 'exists:units,id',
            'adjustment_type' => 'required|in:percentage,fixed,set',
            'adjustment_value' => 'required|numeric',
            'building_id' => 'nullable|integer|exists:buildings,id',
            'wing_id' => 'nullable|integer|exists:wings,id',
        ];
    }
}
