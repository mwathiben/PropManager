<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InvoiceType extends Model
{
    public const TYPE_STANDARD = 'standard';

    public const TYPE_FIRST_PAYMENT = 'first_payment';

    public const TYPE_UTILITY = 'utility';

    public const TYPE_ARREARS = 'arrears';

    public const TYPE_CREDIT_NOTE = 'credit_note';

    protected $fillable = [
        'code',
        'name',
        'description',
        'is_system',
        'is_credit',
    ];

    protected $casts = [
        'is_system' => 'boolean',
        'is_credit' => 'boolean',
    ];

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public static function standard(): ?self
    {
        return static::where('code', self::TYPE_STANDARD)->first();
    }

    public static function firstPayment(): ?self
    {
        return static::where('code', self::TYPE_FIRST_PAYMENT)->first();
    }

    public static function utility(): ?self
    {
        return static::where('code', self::TYPE_UTILITY)->first();
    }

    public static function arrears(): ?self
    {
        return static::where('code', self::TYPE_ARREARS)->first();
    }

    public static function creditNote(): ?self
    {
        return static::where('code', self::TYPE_CREDIT_NOTE)->first();
    }

    public function isCredit(): bool
    {
        return $this->is_credit;
    }

    public function isStandard(): bool
    {
        return $this->code === self::TYPE_STANDARD;
    }

    public function isFirstPayment(): bool
    {
        return $this->code === self::TYPE_FIRST_PAYMENT;
    }
}
