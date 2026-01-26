<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BuildingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'building_type' => $this->building_type,
            'address' => $this->address,
            'total_floors' => $this->total_floors,
            'units_per_floor' => $this->units_per_floor,
            'water_billing_type' => $this->water_billing_type,
            'is_wing' => $this->is_wing,
            'units_count' => $this->whenCounted('units'),
            'property' => new PropertyResource($this->whenLoaded('property')),
            'units' => UnitResource::collection($this->whenLoaded('units')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
