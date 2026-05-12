<?php

namespace App\Http\Requests\Building;

use App\Enums\Currency;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class StorePropertyBuildingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check() && Auth::user()->isLandlord();
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'floors' => 'required|integer|min:1',
            'units_per_floor' => 'required|integer|min:1',
            // Phase-17 MONEY-9: temporarily KES-only. The Currency enum
            // declares USD/EUR/GBP cases for future cross-border support
            // but FX/exchange-rate handling is Phase-18+ work — without
            // FX, dashboards + reports would mis-sum across currencies.
            // Lock to KES until the Phase 18 candidate ships.
            'currency' => ['nullable', 'string', Rule::in(['KES'])],
        ];
    }
}
