<?php

declare(strict_types=1);

namespace App\Http\Requests\Water;

use App\Models\WaterConnection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWaterConnectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isLandlord();
    }

    public function rules(): array
    {
        // Scope the meter/unit FKs to the acting landlord so a connection can't be
        // pointed at another landlord's metering point.
        $landlordId = $this->user()->id;

        return [
            'identifier' => ['required', 'string', 'max:100'],
            'client_name' => ['nullable', 'string', 'max:255'],
            'billing_mode' => ['required', Rule::in(WaterConnection::BILLING_MODES)],
            'client_rate' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            // whereNull(deleted_at): Rule::exists matches soft-deleted rows by default —
            // don't let a connection be pointed at a decommissioned meter/unit.
            'meter_id' => ['nullable', 'integer', Rule::exists('water_meters', 'id')->where('landlord_id', $landlordId)->whereNull('deleted_at')],
            'unit_id' => ['nullable', 'integer', Rule::exists('units', 'id')->where('landlord_id', $landlordId)->whereNull('deleted_at')],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'connected_at' => ['nullable', 'date', 'before_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
