<?php

namespace App\Http\Requests\WaterSetting;

use Illuminate\Foundation\Http\FormRequest;

class UpdateWaterSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->isLandlord();
    }

    public function rules(): array
    {
        return [
            'water_billing_type' => 'required|in:consumption,flat_rate,none',
            'water_unit_rate' => 'nullable|numeric|min:0',
            'flat_water_rate' => 'nullable|numeric|min:0',
            // Phase-87 tariff depth (global).
            'tiered_tariffs' => 'nullable|array',
            'tiered_tariffs.*.from' => 'required_with:tiered_tariffs|numeric|min:0',
            'tiered_tariffs.*.to' => 'nullable|numeric|min:0',
            'tiered_tariffs.*.rate' => 'required_with:tiered_tariffs|numeric|min:0',
            'water_standing_charge' => 'nullable|numeric|min:0',
            'water_minimum_charge' => 'nullable|numeric|min:0',
            'water_sewerage_percent' => 'nullable|numeric|min:0|max:100',
            'water_vat_percent' => 'nullable|numeric|min:0|max:100',
            'water_source' => 'nullable|in:borehole,county,mixed',
            // Phase-88 reading cycle.
            'water_reading_day' => 'nullable|integer|min:1|max:28',
            // min:1 — a 0-day window would auto-approve readings the same day,
            // bypassing the landlord review the feature exists to provide (review).
            'water_review_days' => 'nullable|integer|min:1|max:31',
            'building_overrides' => 'nullable|array',
            'building_overrides.*.id' => 'required|exists:buildings,id',
            'building_overrides.*.water_billing_type' => 'nullable|in:consumption,flat_rate,none,inherit',
            'building_overrides.*.water_unit_rate' => 'nullable|numeric|min:0',
            'building_overrides.*.water_flat_rate' => 'nullable|numeric|min:0',
            'building_overrides.*.water_standing_charge' => 'nullable|numeric|min:0',
            'building_overrides.*.water_minimum_charge' => 'nullable|numeric|min:0',
            'building_overrides.*.water_sewerage_percent' => 'nullable|numeric|min:0|max:100',
            'building_overrides.*.water_vat_percent' => 'nullable|numeric|min:0|max:100',
            'building_overrides.*.water_source' => 'nullable|in:borehole,county,mixed',
            'building_overrides.*.water_reading_day' => 'nullable|integer|min:1|max:28',
            'building_overrides.*.water_review_days' => 'nullable|integer|min:1|max:31',
        ];
    }

    /**
     * Review CRITICAL-2: per-band rules can't catch gaps/overlaps/inversions
     * across bands, which would silently mis-bill. Enforce: start at 0, each
     * to > from, contiguous, and only the last band may be open-ended.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            $bands = $this->input('tiered_tariffs');
            if (empty($bands) || ! is_array($bands)) {
                return;
            }

            $sorted = collect($bands)
                ->sortBy(fn ($b) => (float) ($b['from'] ?? 0))
                ->values();
            $expectedFrom = 0.0;
            $last = $sorted->count() - 1;

            foreach ($sorted as $i => $band) {
                $from = (float) ($band['from'] ?? 0);
                $hasTo = isset($band['to']) && $band['to'] !== null && $band['to'] !== '';
                $to = $hasTo ? (float) $band['to'] : null;

                $invalid = $from != $expectedFrom
                    || ($to !== null && $to <= $from)
                    || ($to === null && $i !== $last);

                if ($invalid) {
                    $v->errors()->add('tiered_tariffs', __('water.settings.tiers_invalid'));

                    return;
                }

                $expectedFrom = $to ?? $from;
            }
        });
    }
}
