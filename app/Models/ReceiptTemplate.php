<?php

namespace App\Models;

use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReceiptTemplate extends Model
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
        // Header Elements
        'show_logo',
        'show_receipt_number',
        'show_payment_date',
        // Payment Information
        'show_payment_method',
        'show_transaction_reference',
        'show_amount_breakdown',
        // Tenant Information
        'show_tenant_name',
        'show_tenant_email',
        'show_tenant_phone',
        // Property Information
        'show_unit_details',
        'show_building_name',
        // Invoice Information
        'show_invoice_details',
        'show_invoice_breakdown',
        'show_balance_after_payment',
        // Footer Elements
        'show_thank_you_message',
        'show_qr_code',
        'show_footer',
        // Custom Content
        'custom_header',
        'custom_footer',
        'thank_you_message',
        // Colors
        'primary_color',
        'secondary_color',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'show_logo' => 'boolean',
        'show_receipt_number' => 'boolean',
        'show_payment_date' => 'boolean',
        'show_payment_method' => 'boolean',
        'show_transaction_reference' => 'boolean',
        'show_amount_breakdown' => 'boolean',
        'show_tenant_name' => 'boolean',
        'show_tenant_email' => 'boolean',
        'show_tenant_phone' => 'boolean',
        'show_unit_details' => 'boolean',
        'show_building_name' => 'boolean',
        'show_invoice_details' => 'boolean',
        'show_invoice_breakdown' => 'boolean',
        'show_balance_after_payment' => 'boolean',
        'show_thank_you_message' => 'boolean',
        'show_qr_code' => 'boolean',
        'show_footer' => 'boolean',
    ];

    public function landlord(): BelongsTo
    {
        return $this->belongsTo(User::class, 'landlord_id');
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(Receipt::class, 'receipt_template_id');
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

    public static function getToggleGroups(): array
    {
        return [
            [
                'title' => 'Header Elements',
                'toggles' => [
                    ['key' => 'show_logo', 'label' => 'Show Logo'],
                    ['key' => 'show_receipt_number', 'label' => 'Show Receipt Number'],
                    ['key' => 'show_payment_date', 'label' => 'Show Payment Date'],
                ],
            ],
            [
                'title' => 'Payment Information',
                'toggles' => [
                    ['key' => 'show_payment_method', 'label' => 'Show Payment Method'],
                    ['key' => 'show_transaction_reference', 'label' => 'Show Transaction Reference'],
                    ['key' => 'show_amount_breakdown', 'label' => 'Show Amount Breakdown'],
                ],
            ],
            [
                'title' => 'Tenant Information',
                'toggles' => [
                    ['key' => 'show_tenant_name', 'label' => 'Show Tenant Name'],
                    ['key' => 'show_tenant_email', 'label' => 'Show Tenant Email'],
                    ['key' => 'show_tenant_phone', 'label' => 'Show Tenant Phone'],
                ],
            ],
            [
                'title' => 'Property Information',
                'toggles' => [
                    ['key' => 'show_unit_details', 'label' => 'Show Unit Details'],
                    ['key' => 'show_building_name', 'label' => 'Show Building Name'],
                ],
            ],
            [
                'title' => 'Invoice Information',
                'toggles' => [
                    ['key' => 'show_invoice_details', 'label' => 'Show Invoice Details'],
                    ['key' => 'show_invoice_breakdown', 'label' => 'Show Invoice Breakdown'],
                    ['key' => 'show_balance_after_payment', 'label' => 'Show Balance After Payment'],
                ],
            ],
            [
                'title' => 'Footer Elements',
                'toggles' => [
                    ['key' => 'show_thank_you_message', 'label' => 'Show Thank You Message'],
                    ['key' => 'show_qr_code', 'label' => 'Show QR Code'],
                    ['key' => 'show_footer', 'label' => 'Show Footer Note'],
                ],
            ],
        ];
    }
}
