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
     * Phase-46 CANONICAL-FIX-2: LandlordProfile is canonical for
     * profile_photo_path on landlord/caretaker accounts; users.profile_photo_path
     * is a denormalised mirror. On save, fan the canonical value into
     * the User row so downstream readers (avatar URLs, nav badges) see
     * a consistent value.
     */
    protected static function booted(): void
    {
        static::saved(function (LandlordProfile $profile): void {
            User::query()
                ->where('id', $profile->user_id)
                ->update(['profile_photo_path' => $profile->profile_photo_path]);
        });
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
