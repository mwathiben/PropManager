<?php

namespace App\Http\Requests\WaterReading;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class UpdateWaterReadingRequest extends FormRequest
{
    public function authorize(): bool
    {
        // PRIV-2: also verify the route-bound reading belongs to this
        // landlord. Role check alone admits any reading bound by
        // TenantScope under the same landlord_id.
        $user = Auth::user();
        $reading = $this->route('reading');

        if (! $user || ! $reading) {
            return false;
        }

        if (! $user->isLandlord() && ! $user->isCaretaker()) {
            return false;
        }

        $landlordId = $user->isCaretaker() ? (int) $user->landlord_id : (int) $user->id;

        return (int) $reading->landlord_id === $landlordId;
    }

    public function rules(): array
    {
        return [
            'current_reading' => 'required|numeric|min:0',
            'reading_date' => 'required|date',
        ];
    }
}
