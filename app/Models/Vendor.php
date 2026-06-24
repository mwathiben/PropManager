<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vendor extends Model
{
    use Auditable, HasFactory, TenantScope;

    protected $fillable = [
        'landlord_id',
        'name',
        'contact_person',
        'email',
        'phone',
        'address',
        'tax_id',
        'notes',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function landlord(): BelongsTo
    {
        return $this->belongsTo(User::class, 'landlord_id');
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    /**
     * Phase-75 VENDOR-ROUTING-1: trade competencies (Ticket issue
     * subcategories) used for pool suggestion + auto-routing.
     */
    public function specialties(): HasMany
    {
        return $this->hasMany(VendorSpecialty::class);
    }

    /**
     * Replace the vendor's specialties with the given allow-listed categories.
     *
     * @param  array<int, string>  $categories
     */
    public function syncSpecialties(array $categories): void
    {
        $allowed = array_keys(Ticket::issueSubcategories());
        $valid = array_values(array_unique(array_intersect($categories, $allowed)));

        $this->specialties()->whereNotIn('category', $valid)->delete();
        $existing = $this->specialties()->pluck('category')->all();
        foreach (array_diff($valid, $existing) as $category) {
            $this->specialties()->create(['category' => $category]);
        }
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function getTotalExpenses(): float
    {
        return $this->expenses()->sum('amount');
    }
}
