<?php

declare(strict_types=1);

namespace App\Http\Requests\Water;

use Illuminate\Foundation\Http\FormRequest;

class SetupWaterClientsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isLandlord();
    }

    public function rules(): array
    {
        return [
            'supplies_water_clients' => ['required', 'boolean'],
            'water_client_rate' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
        ];
    }
}
