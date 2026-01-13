<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubscriptionPlan extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'price_monthly',
        'price_yearly',
        'currency',
        'max_properties',
        'max_buildings',
        'max_units',
        'max_caretakers',
        'water_billing_enabled',
        'ocr_enabled',
        'reports_enabled',
        'bulk_operations_enabled',
        'document_storage_enabled',
        'document_storage_mb',
        'email_notifications_enabled',
        'sms_notifications_enabled',
        'priority_support',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'price_monthly' => 'decimal:2',
        'price_yearly' => 'decimal:2',
        'water_billing_enabled' => 'boolean',
        'ocr_enabled' => 'boolean',
        'reports_enabled' => 'boolean',
        'bulk_operations_enabled' => 'boolean',
        'document_storage_enabled' => 'boolean',
        'email_notifications_enabled' => 'boolean',
        'sms_notifications_enabled' => 'boolean',
        'priority_support' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class, 'plan_id');
    }

    public function getYearlySavingsAttribute(): float
    {
        return ($this->price_monthly * 12) - $this->price_yearly;
    }

    public function getYearlySavingsPercentAttribute(): int
    {
        $yearlyPrice = $this->price_monthly * 12;
        if ($yearlyPrice <= 0) {
            return 0;
        }

        return (int) round(($this->yearly_savings / $yearlyPrice) * 100);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('sort_order');
    }

    public static function free(): ?self
    {
        return static::where('slug', 'free')->first();
    }

    public function isFree(): bool
    {
        return $this->price_monthly == 0;
    }

    public function getFeaturesList(): array
    {
        $features = [];

        $features[] = "{$this->max_properties} ".($this->max_properties === 1 ? 'property' : 'properties');
        $features[] = "{$this->max_buildings} ".($this->max_buildings === 1 ? 'building' : 'buildings');
        $features[] = "{$this->max_units} units";
        $features[] = "{$this->max_caretakers} ".($this->max_caretakers === 1 ? 'caretaker' : 'caretakers');

        if ($this->water_billing_enabled) {
            $features[] = 'Water billing';
        }
        if ($this->ocr_enabled) {
            $features[] = 'OCR meter reading';
        }
        if ($this->reports_enabled) {
            $features[] = 'Reports & analytics';
        }
        if ($this->bulk_operations_enabled) {
            $features[] = 'Bulk operations';
        }
        if ($this->document_storage_enabled) {
            $features[] = "Document storage ({$this->document_storage_mb}MB)";
        }
        if ($this->email_notifications_enabled) {
            $features[] = 'Email notifications';
        }
        if ($this->sms_notifications_enabled) {
            $features[] = 'SMS notifications';
        }
        if ($this->priority_support) {
            $features[] = 'Priority support';
        }

        return $features;
    }
}
