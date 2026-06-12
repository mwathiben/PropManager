<?php

namespace App\Http\Requests\Building;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class UpdateUnitsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check();
    }

    public function rules(): array
    {
        return [
            'selectedUnitIds' => 'required|array',
            'selectedUnitIds.*' => 'integer|exists:units,id',
            'action' => ['required', 'string', Rule::in(['update_rent', 'update_type', 'delete'])],
            'value' => $this->valueRules(),
        ];
    }

    /**
     * The `value` payload is action-dependent. The Architect's rent field is
     * a <input type="number">, so update_rent emits a JS number — validating
     * it as a plain string silently rejected every rent change. update_type
     * sends a unit-type string; delete carries no value.
     *
     * Bulk status changes are NOT handled here (BuildingService::bulkUpdateUnits
     * has no update_status branch) — they have their own guarded endpoint,
     * bulk.updateUnitStatus / UpdateUnitStatusRequest — so update_status is
     * deliberately not an accepted action.
     *
     * @return array<int, mixed>
     */
    private function valueRules(): array
    {
        return match ($this->input('action')) {
            'update_rent' => ['required', 'numeric', 'min:0'],
            'update_type' => ['required', 'string', Rule::in(['residential', 'commercial'])],
            default => ['nullable'],
        };
    }
}
