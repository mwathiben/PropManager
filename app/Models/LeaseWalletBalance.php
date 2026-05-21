<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Currency;
use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Phase-76 WALLET-DEEP MULTI-CCY-1: cached wallet balance for a (lease,
 * currency) pair — non-default currencies only (the default currency lives in
 * Lease.wallet_balance). Written exclusively by WalletService under a lock.
 *
 * @property int $lease_id
 * @property int $landlord_id
 * @property string $currency
 * @property string $balance
 */
class LeaseWalletBalance extends Model
{
    use TenantScope;

    protected $fillable = [
        'lease_id',
        'landlord_id',
        'currency',
        'balance',
    ];

    protected $casts = [
        'balance' => 'decimal:2',
        'currency' => Currency::class,
    ];

    public function lease(): BelongsTo
    {
        return $this->belongsTo(Lease::class);
    }
}
