<?php

declare(strict_types=1);

namespace App\Http\Requests\Meter;

use App\Models\Meter;
use Illuminate\Foundation\Http\FormRequest;

class StoreMeterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Meter::class);
    }

    public function rules(): array
    {
        return [
            'building_id' => ['nullable', 'integer', 'exists:buildings,id'],
            'unit_id' => ['nullable', 'integer', 'exists:units,id'],
            'parent_meter_id' => ['nullable', 'integer', 'exists:water_meters,id'],
            'serial_number' => ['nullable', 'string', 'max:255'],
            'meter_type' => ['nullable', 'string', 'max:50'],
            'initial_reading' => ['required', 'numeric', 'min:0'],
            'installed_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
