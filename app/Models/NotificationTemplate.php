<?php

namespace App\Models;

use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class NotificationTemplate extends Model
{
    use TenantScope;

    protected $fillable = [
        'landlord_id',
        'name',
        'slug',
        'type',
        'subject',
        'body',
        'available_placeholders',
        'is_default',
        'is_active',
    ];

    protected $casts = [
        'available_placeholders' => 'array',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * Boot the model
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($template) {
            if (empty($template->slug)) {
                $template->slug = Str::slug($template->name);
            }
        });
    }

    /**
     * Get the landlord who owns this template
     */
    public function landlord(): BelongsTo
    {
        return $this->belongsTo(User::class, 'landlord_id');
    }

    /**
     * Get schedules using this template
     */
    public function schedules(): HasMany
    {
        return $this->hasMany(NotificationSchedule::class, 'template_id');
    }

    /**
     * Scope to get active templates
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get templates of a specific type
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope to get default templates
     */
    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    /**
     * Render the template with given context
     */
    public function render(array $context): array
    {
        $subject = $this->subject;
        $body = $this->body;

        foreach ($context as $key => $value) {
            $placeholder = '{{'.$key.'}}';
            $subject = str_replace($placeholder, (string) $value, $subject);
            $body = str_replace($placeholder, (string) $value, $body);
        }

        return [
            'subject' => $subject,
            'body' => $body,
        ];
    }

    /**
     * Get common placeholders available for all templates
     */
    public static function getCommonPlaceholders(): array
    {
        return [
            'tenant_name' => 'Tenant full name',
            'tenant_email' => 'Tenant email address',
            'unit_number' => 'Unit/apartment number',
            'building_name' => 'Building name',
            'landlord_name' => 'Landlord name',
            'property_name' => 'Property name',
            'current_date' => 'Current date',
        ];
    }

    /**
     * Get type-specific placeholders
     */
    public static function getTypePlaceholders(string $type): array
    {
        return match ($type) {
            'rent_reminder' => [
                'rent_amount' => 'Monthly rent amount',
                'due_date' => 'Rent due date',
                'days_until_due' => 'Days until due',
            ],
            'arrears_notice' => [
                'arrears_amount' => 'Outstanding balance',
                'days_overdue' => 'Days past due',
                'last_payment_date' => 'Last payment date',
            ],
            'invoice' => [
                'invoice_number' => 'Invoice number',
                'total_amount' => 'Invoice total',
                'due_date' => 'Payment due date',
                'invoice_url' => 'Link to view invoice',
            ],
            'receipt' => [
                'receipt_number' => 'Receipt number',
                'payment_amount' => 'Payment amount',
                'payment_date' => 'Payment date',
                'payment_method' => 'Payment method',
            ],
            'rent_hike' => [
                'old_rent' => 'Current rent amount',
                'new_rent' => 'New rent amount',
                'effective_date' => 'Effective date',
                'percentage_increase' => 'Percentage increase',
            ],
            'lease_expiry' => [
                'expiry_date' => 'Lease expiry date',
                'days_until_expiry' => 'Days until expiry',
            ],
            'lease_renewal' => [
                'renewal_date' => 'Renewal date',
                'new_rent' => 'New rent amount',
            ],
            'eviction_notice' => [
                'arrears_amount' => 'Outstanding balance',
                'notice_period' => 'Notice period (days)',
                'vacate_date' => 'Required vacate date',
            ],
            default => [],
        };
    }

    /**
     * Get all available placeholders for a type
     */
    public static function getAllPlaceholders(string $type): array
    {
        return array_merge(
            self::getCommonPlaceholders(),
            self::getTypePlaceholders($type)
        );
    }
}
