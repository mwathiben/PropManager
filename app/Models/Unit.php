<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Unit extends Model
{
    use Auditable, HasFactory, SoftDeletes, TenantScope;

    protected $fillable = [
        'building_id',
        'landlord_id',
        'unit_number',
        'floor_number',
        'status',
        'target_rent',
        'meter_number',
    ];

    protected $casts = [
        'floor_number' => 'integer',
        'target_rent' => 'decimal:2',
    ];

    // --- RELATIONSHIPS ---

    public function building()
    {
        return $this->belongsTo(Building::class);
    }

    public function leases()
    {
        return $this->hasMany(Lease::class);
    }

    public function activeLease()
    {
        return $this->hasOne(Lease::class)->where('is_active', true);
    }

    public function waterReadings()
    {
        return $this->hasMany(WaterReading::class);
    }

    // --- LOGIC HELPERS ---

    /**
     * Determine the price to show on the "Create Lease" form.
     */
    public function getSuggestedRentAttribute()
    {
        // If we have a target rent set, use it.
        // Otherwise, maybe fallback to a building average (optional logic)
        return $this->target_rent ?? 0.00;
    }
}
