<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChartOfAccount extends Model
{
    use HasFactory, TenantScope;

    public const TYPE_ASSET = 'asset';
    public const TYPE_LIABILITY = 'liability';
    public const TYPE_EQUITY = 'equity';
    public const TYPE_INCOME = 'income';
    public const TYPE_EXPENSE = 'expense';

    public const TYPES = [
        self::TYPE_ASSET,
        self::TYPE_LIABILITY,
        self::TYPE_EQUITY,
        self::TYPE_INCOME,
        self::TYPE_EXPENSE,
    ];

    public const SOURCE_INVOICE_TYPE = 'invoice_type';
    public const SOURCE_EXPENSE_CATEGORY = 'expense_category';
    public const SOURCE_PAYMENT_METHOD = 'payment_method';
    public const SOURCE_DEPOSIT = 'deposit';
    public const SOURCE_DEFAULT_INCOME = 'default_income';
    public const SOURCE_DEFAULT_EXPENSE = 'default_expense';

    protected $fillable = [
        'landlord_id',
        'account_code',
        'account_name',
        'account_type',
        'source_kind',
        'source_key',
        'is_active',
        'description',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
