<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Phase-76 WALLET-DEEP AUTO-APPLY-1: per-landlord wallet auto-apply override.
 * Keyed by landlord_id (no TenantScope — the sweep cron reads across landlords).
 *
 * @property int $landlord_id
 * @property string $auto_apply_mode
 */
class LandlordWalletSetting extends Model
{
    public const MODE_OFF = 'off';

    public const MODE_ON_INVOICE_CREATE = 'on_invoice_create';

    public const MODE_OLDEST_FIRST_SWEEP = 'oldest_first_sweep';

    protected $fillable = [
        'landlord_id',
        'auto_apply_mode',
    ];

    public function landlord(): BelongsTo
    {
        return $this->belongsTo(User::class, 'landlord_id');
    }
}
