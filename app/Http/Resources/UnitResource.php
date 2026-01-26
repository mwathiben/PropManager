<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UnitResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'unit_number' => $this->unit_number,
            'floor_number' => $this->floor_number,
            'status' => $this->status,
            'target_rent' => (float) $this->target_rent,
            'meter_number' => $this->meter_number,
            'building' => new BuildingResource($this->whenLoaded('building')),
            'active_lease' => new LeaseResource($this->whenLoaded('activeLease')),
            'water_readings' => WaterReadingResource::collection($this->whenLoaded('waterReadings')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
