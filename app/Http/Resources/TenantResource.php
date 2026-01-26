<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TenantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $isOwnerOrLandlord = $user && (
            $user->id === $this->id
            || $user->isLandlord()
            || $user->isCaretaker()
            || $user->isSuperAdmin()
        );

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'mobile_number' => $this->mobile_number,
            'profile_photo_url' => $this->profile_photo_url,
            'kyc_completed_at' => $this->kyc_completed_at?->toIso8601String(),
            'national_id' => $this->when($isOwnerOrLandlord, fn () => $this->national_id),
            'emergency_contact_name' => $this->when($isOwnerOrLandlord, $this->emergency_contact_name),
            'emergency_contact_phone' => $this->when($isOwnerOrLandlord, $this->emergency_contact_phone),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
