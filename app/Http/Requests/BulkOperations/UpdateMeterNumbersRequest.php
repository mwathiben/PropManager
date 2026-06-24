<?php

namespace App\Http\Requests\BulkOperations;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class UpdateMeterNumbersRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = Auth::user();

        return $user && ($user->isScopeOwner() || $user->isCaretaker());
    }

    public function rules(): array
    {
        return [
            'updates' => 'required|array|min:1',
            'updates.*.unit_id' => 'required|exists:units,id',
            'updates.*.meter_number' => 'nullable|string|max:50',
            'building_id' => 'nullable|integer|exists:buildings,id',
            'wing_id' => 'nullable|integer|exists:wings,id',
        ];
    }
}
