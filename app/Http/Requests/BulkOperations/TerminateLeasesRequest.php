<?php

namespace App\Http\Requests\BulkOperations;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class TerminateLeasesRequest extends FormRequest
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
            'termination_date' => 'required|date',
            'reason' => 'nullable|string|max:500',
            'notify_tenants' => 'boolean',
            'update_unit_status' => 'boolean',
            'building_id' => 'nullable|integer|exists:buildings,id',
            'wing_id' => 'nullable|integer|exists:wings,id',
        ];
    }
}
