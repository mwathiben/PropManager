<?php

declare(strict_types=1);

namespace App\Http\Requests\Meter;

use App\Models\Meter;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMeterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Meter::class);
    }

    public function rules(): array
    {
        // Review C2: scope FK existence to the acting landlord so a meter can't
        // be registered against another landlord's building/unit/parent meter.
        $landlordId = $this->user()->id;

        return [
            'building_id' => ['nullable', 'integer', Rule::exists('buildings', 'id')->where('landlord_id', $landlordId)],
            'unit_id' => ['nullable', 'integer', Rule::exists('units', 'id')->where('landlord_id', $landlordId)],
            'parent_meter_id' => ['nullable', 'integer', Rule::exists('water_meters', 'id')->where('landlord_id', $landlordId)],
            'serial_number' => ['nullable', 'string', 'max:255'],
            'meter_type' => ['nullable', 'string', 'max:50'],
            'initial_reading' => ['required', 'numeric', 'min:0'],
            'installed_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
