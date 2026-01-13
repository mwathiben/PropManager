<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class LandlordProfile extends Model
{
    protected $fillable = [
        'user_id',
        'company_name',
        'business_registration_number',
        'profile_photo_path',
        'address',
        'city',
        'country',
        'tax_id',
        'website',
    ];

    /**
     * Get the user this profile belongs to
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the profile photo URL
     */
    public function getProfilePhotoUrlAttribute(): ?string
    {
        if (! $this->profile_photo_path) {
            return null;
        }

        return Storage::disk('public')->url($this->profile_photo_path);
    }

    /**
     * Check if profile has a photo
     */
    public function hasProfilePhoto(): bool
    {
        return ! empty($this->profile_photo_path);
    }

    /**
     * Get the display name (company name or user name)
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->company_name ?? $this->user->name;
    }

    /**
     * Get the full address
     */
    public function getFullAddressAttribute(): ?string
    {
        $parts = array_filter([
            $this->address,
            $this->city,
            $this->country,
        ]);

        return ! empty($parts) ? implode(', ', $parts) : null;
    }

    /**
     * Check if profile is complete
     */
    public function isComplete(): bool
    {
        return ! empty($this->company_name) || ! empty($this->address);
    }
}
