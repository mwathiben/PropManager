<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OnboardingMilestone extends Model
{
    use TenantScope;

    public const SIGNED_UP = 'signed_up';
    public const FIRST_PROPERTY = 'first_property';
    public const FIRST_UNIT = 'first_unit';
    public const FIRST_TENANT = 'first_tenant';
    public const FIRST_INVOICE = 'first_invoice';
    public const FIRST_PAYMENT = 'first_payment';

    public const FUNNEL = [
        self::SIGNED_UP,
        self::FIRST_PROPERTY,
        self::FIRST_UNIT,
        self::FIRST_TENANT,
        self::FIRST_INVOICE,
        self::FIRST_PAYMENT,
    ];

    protected $fillable = [
        'landlord_id',
        'milestone',
        'reached_at',
        'metadata',
    ];

    protected $casts = [
        'reached_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function landlord(): BelongsTo
    {
        return $this->belongsTo(User::class, 'landlord_id');
    }
}
