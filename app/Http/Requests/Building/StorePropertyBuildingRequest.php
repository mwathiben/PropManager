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
            'currency' => ['nullable', 'string', Rule::in(Currency::values())],
        ];
    }
}
