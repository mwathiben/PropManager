<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Phase-48 TENANT-PAYMENT-METHOD-1: stored M-Pesa / bank / card credentials
 * for tenants. details_encrypted is auto-encrypted via the encrypted:json
 * cast (Laravel Crypt-backed). All writes should go through
 * App\Services\Tenant\TenantPaymentMethodService to ensure the single-default
 * invariant per (user, type) tuple holds.
 *
 * @property int $id
 * @property int $user_id
 * @property string $type mpesa|bank|card
 * @property array<string, mixed> $details_encrypted
 * @property bool $is_default
 * @property \Carbon\Carbon|null $verified_at
 */
class TenantPaymentMethod extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'type',
        'details_encrypted',
        'is_default',
        'verified_at',
    ];

    protected $casts = [
        'details_encrypted' => 'encrypted:json',
        'is_default' => 'boolean',
        'verified_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
