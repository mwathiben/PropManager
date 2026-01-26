<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WaterReadingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reading_date' => $this->reading_date?->toDateString(),
            'previous_reading' => (float) $this->previous_reading,
            'current_reading' => (float) $this->current_reading,
            'consumption' => (float) $this->consumption,
            'cost' => (float) $this->cost,
            'status' => $this->status,
            'is_invoiced' => $this->is_invoiced,
            'photo_url' => $this->photo_url,
            'unit' => new UnitResource($this->whenLoaded('unit')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
