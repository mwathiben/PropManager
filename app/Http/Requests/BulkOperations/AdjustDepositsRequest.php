<?php

namespace App\Http\Requests\BulkOperations;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class AdjustDepositsRequest extends FormRequest
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
            'adjustment_type' => 'required|in:percentage,fixed,set',
            'adjustment_value' => 'required|numeric',
            'notify_tenants' => 'boolean',
            'building_id' => 'nullable|integer|exists:buildings,id',
            'wing_id' => 'nullable|integer|exists:wings,id',
        ];
    }
}
