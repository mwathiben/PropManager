<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceItem extends Model
{
    public const TYPE_RENT = 'rent';

    public const TYPE_DEPOSIT = 'deposit';

    public const TYPE_WATER = 'water';

    public const TYPE_ELECTRICITY = 'electricity';

    public const TYPE_ARREARS = 'arrears';

    public const TYPE_LATE_FEE = 'late_fee';

    public const TYPE_ADMIN_FEE = 'admin_fee';

    public const TYPE_KEY_DEPOSIT = 'key_deposit';

    public const TYPE_OTHER = 'other';

    public const TYPE_CREDIT = 'credit';

    protected $fillable = [
        'invoice_id',
        'item_type',
        'description',
        'quantity',
        'unit_price',
        'total',
        'sort_order',
        'metadata',
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'total' => 'decimal:2',
        'metadata' => 'array',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public static function getTypeLabel(string $type): string
    {
        return match ($type) {
            self::TYPE_RENT => 'Rent',
            self::TYPE_DEPOSIT => 'Security Deposit',
            self::TYPE_WATER => 'Water Charges',
            self::TYPE_ELECTRICITY => 'Electricity',
            self::TYPE_ARREARS => 'Previous Balance',
            self::TYPE_LATE_FEE => 'Late Payment Fee',
            self::TYPE_ADMIN_FEE => 'Administrative Fee',
            self::TYPE_KEY_DEPOSIT => 'Key Deposit',
            self::TYPE_OTHER => 'Other Charges',
            self::TYPE_CREDIT => 'Credit Applied',
            default => ucfirst(str_replace('_', ' ', $type)),
        };
    }

    public function isCredit(): bool
    {
        return $this->item_type === self::TYPE_CREDIT || $this->total < 0;
    }
}
