<?php

namespace App\Http\Requests\BulkOperations;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class AdjustRentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = Auth::user();

        return $user && ($user->isLandlord() || $user->isCaretaker());
    }

    public function rules(): array
    {
        return [
            'lease_ids' => 'required|array|min:1',
            'lease_ids.*' => 'exists:leases,id',
            'adjustment_type' => 'required|in:percentage,fixed',
            'adjustment_value' => 'required|numeric',
            'effective_date' => 'required|date|after_or_equal:today',
            'notify_tenants' => 'boolean',
            'reason' => 'nullable|string|max:500',
            'building_id' => 'nullable|integer|exists:buildings,id',
            'wing_id' => 'nullable|integer|exists:wings,id',
        ];
    }
}
