<?php

namespace App\Http\Requests\BulkOperations;

use Illuminate\Foundation\Http\FormRequest;

class ExtendLeasesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->isLandlord() || auth()->user()->isCaretaker();
    }

    public function rules(): array
    {
        return [
            'lease_ids' => 'required|array|min:1',
            'lease_ids.*' => 'exists:leases,id',
            'extension_months' => 'required|integer|min:1|max:60',
            'notify_tenants' => 'boolean',
            'building_id' => 'nullable|integer|exists:buildings,id',
            'wing_id' => 'nullable|integer|exists:buildings,id',
        ];
    }
}
