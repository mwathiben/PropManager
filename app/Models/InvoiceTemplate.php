<?php

namespace App\Models;

use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InvoiceTemplate extends Model
{
    use TenantScope;

    public const DESIGN_CLASSIC = 'classic';

    public const DESIGN_MODERN = 'modern';

    public const DESIGN_MINIMAL = 'minimal';

    public const DESIGN_PROFESSIONAL = 'professional';

    protected $fillable = [
        'landlord_id',
        'name',
        'design',
        'is_default',
        'show_logo',
        'show_tax_number',
        'show_tenant_id',
        'show_unit_details',
        'show_lease_reference',
        'show_due_date',
        'show_late_warning',
        'show_bank_details',
        'show_footer',
        'show_qr_code',
        'show_payment_instructions',
        'show_arrears_breakdown',
        'show_water_details',
        'custom_header',
        'custom_footer',
        'primary_color',
        'secondary_color',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'show_logo' => 'boolean',
        'show_tax_number' => 'boolean',
        'show_tenant_id' => 'boolean',
        'show_unit_details' => 'boolean',
        'show_lease_reference' => 'boolean',
        'show_due_date' => 'boolean',
        'show_late_warning' => 'boolean',
        'show_bank_details' => 'boolean',
        'show_footer' => 'boolean',
        'show_qr_code' => 'boolean',
        'show_payment_instructions' => 'boolean',
        'show_arrears_breakdown' => 'boolean',
        'show_water_details' => 'boolean',
    ];

    public function landlord(): BelongsTo
    {
        return $this->belongsTo(User::class, 'landlord_id');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'invoice_template_id');
    }

    public static function getDesignOptions(): array
    {
        return [
            self::DESIGN_CLASSIC => 'Classic',
            self::DESIGN_MODERN => 'Modern',
            self::DESIGN_MINIMAL => 'Minimal',
            self::DESIGN_PROFESSIONAL => 'Professional',
        ];
    }

    public function makeDefault(): void
    {
        static::where('landlord_id', $this->landlord_id)
            ->where('id', '!=', $this->id)
            ->update(['is_default' => false]);

        $this->update(['is_default' => true]);
    }
}
