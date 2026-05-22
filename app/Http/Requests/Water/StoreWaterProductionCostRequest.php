<?php

declare(strict_types=1);

namespace App\Http\Requests\Water;

use App\Models\WaterProductionCost;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWaterProductionCostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', WaterProductionCost::class);
    }

    public function rules(): array
    {
        // Scope the building FK to the acting landlord so a cost can't be logged
        // against another landlord's building.
        $landlordId = $this->user()->id;

        return [
            'building_id' => ['nullable', 'integer', Rule::exists('buildings', 'id')->where('landlord_id', $landlordId)],
            'cost_date' => ['required', 'date', 'before_or_equal:today'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:99999999'],
            'category' => ['required', 'string', Rule::in(WaterProductionCost::CATEGORIES)],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }
}
