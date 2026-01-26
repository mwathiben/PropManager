<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LeaseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'start_date' => $this->start_date?->toIso8601String(),
            'end_date' => $this->end_date?->toIso8601String(),
            'rent_amount' => (float) $this->rent_amount,
            'deposit_amount' => (float) $this->deposit_amount,
            'wallet_balance' => (float) $this->wallet_balance,
            'is_active' => $this->is_active,
            'tenant' => new TenantResource($this->whenLoaded('tenant')),
            'unit' => $this->whenLoaded('unit', fn () => [
                'id' => $this->unit->id,
                'unit_number' => $this->unit->unit_number,
                'floor' => $this->unit->floor,
                'bedrooms' => $this->unit->bedrooms,
                'bathrooms' => $this->unit->bathrooms,
                'status' => $this->unit->status,
                'building' => [
                    'id' => $this->unit->building->id,
                    'name' => $this->unit->building->name,
                    'property' => $this->unit->building->property ? [
                        'id' => $this->unit->building->property->id,
                        'name' => $this->unit->building->property->name,
                        'address' => $this->unit->building->property->address,
                    ] : null,
                ],
            ]),
            'rent_history' => $this->whenLoaded('rentHistory', fn () => $this->rentHistory->map(fn ($rh) => [
                'id' => $rh->id,
                'old_rent' => (float) $rh->old_rent,
                'new_rent' => (float) $rh->new_rent,
                'effective_date' => $rh->effective_date?->toIso8601String(),
                'reason' => $rh->reason,
            ])),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
