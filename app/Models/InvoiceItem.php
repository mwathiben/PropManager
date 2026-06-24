<?php

namespace App\Models;

use App\ValueObjects\Money;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceItem extends Model
{
    use HasFactory;

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
        'tax_amount_cents',
        'tax_rate_bps',
        'sort_order',
        'metadata',
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'total' => 'decimal:2',
        'tax_amount_cents' => 'integer',
        'tax_rate_bps' => 'integer',
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

    /**
     * Phase-42 TAX-1: tax amount as a Money value object. Returns
     * Money::zero() when the row has no tax stamped (pre-Phase-42
     * rows or non-taxable items).
     */
    public function taxAmount(): Money
    {
        if ($this->tax_amount_cents === null) {
            return Money::zero();
        }

        return Money::fromMinorUnits((int) $this->tax_amount_cents);
    }

    /**
     * Phase-42 TAX-1: subtotal exclusive of tax. The `total` column
     * stores the tax-inclusive amount (existing convention), so we
     * subtract the tax to get the tax-exclusive line subtotal.
     */
    public function subtotalExclusiveOfTax(): Money
    {
        $total = Money::fromString((string) $this->total);

        return $total->subtract($this->taxAmount());
    }

    public function isTaxed(): bool
    {
        return $this->tax_amount_cents !== null && (int) $this->tax_amount_cents > 0;
    }
}
