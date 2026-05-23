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
            'meter_id' => ['nullable', 'integer', Rule::exists('water_meters', 'id')->where('landlord_id', $landlordId)],
            'unit_id' => ['nullable', 'integer', Rule::exists('units', 'id')->where('landlord_id', $landlordId)],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'connected_at' => ['nullable', 'date', 'before_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
