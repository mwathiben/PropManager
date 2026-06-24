<?php

namespace App\Http\Requests\BulkOperations;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class AdjustRentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = Auth::user();

        return $user && ($user->isScopeOwner() || $user->isCaretaker());
    }

    public function rules(): array
    {
        // VALID-7: scope every exists: to the caller's landlord. Pre-fix,
        // unscoped exists:leases,id let the validator return 422 for genuine
        // unknowns and pass for foreign-landlord ids — an enumeration
        // oracle that leaked competitor lease counts.
        $user = Auth::user();
        $landlordId = $user?->isCaretaker() ? (int) $user->landlord_id : (int) $user?->id;

        return [
            'lease_ids' => 'required|array|min:1',
            'lease_ids.*' => [
                'integer',
                \Illuminate\Validation\Rule::exists('leases', 'id')->where('landlord_id', $landlordId),
            ],
            'adjustment_type' => 'required|in:percentage,fixed',
            'adjustment_value' => 'required|numeric',
            'effective_date' => 'required|date|after_or_equal:today',
            'notify_tenants' => 'boolean',
            'reason' => 'nullable|string|max:500',
            'building_id' => [
                'nullable', 'integer',
                \Illuminate\Validation\Rule::exists('buildings', 'id')->where('landlord_id', $landlordId),
            ],
            'wing_id' => [
                'nullable', 'integer',
                \Illuminate\Validation\Rule::exists('buildings', 'id')->where('landlord_id', $landlordId),
            ],
        ];
    }
}
