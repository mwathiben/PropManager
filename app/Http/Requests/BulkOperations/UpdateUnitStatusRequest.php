<?php

namespace App\Http\Requests\BulkOperations;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class UpdateUnitStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = Auth::user();

        return $user && ($user->isLandlord() || $user->isCaretaker());
    }

    public function rules(): array
    {
        // VALID-7: scope every exists: to the caller's landlord — see
        // AdjustRentRequest for the full rationale.
        $user = Auth::user();
        $landlordId = $user?->isCaretaker() ? (int) $user->landlord_id : (int) $user?->id;

        return [
            'unit_ids' => 'required|array|min:1',
            'unit_ids.*' => [
                'integer',
                \Illuminate\Validation\Rule::exists('units', 'id')->where('landlord_id', $landlordId),
            ],
            'new_status' => 'required|in:vacant,occupied,maintenance,arrears',
            'notes' => 'nullable|string|max:500',
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
