<?php

declare(strict_types=1);

namespace App\Http\Requests\Water;

use Illuminate\Foundation\Http\FormRequest;

class UpdateWaterAbstractionLimitRequest extends FormRequest
{
    public function authorize(): bool
    {
        $building = $this->route('building');

        return $this->user()->isLandlord()
            && $building !== null
            && (int) $building->landlord_id === (int) $this->user()->id;
    }

    public function rules(): array
    {
        return [
            'water_abstraction_limit' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
        ];
    }
}
