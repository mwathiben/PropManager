<?php

declare(strict_types=1);

namespace App\Services\Wallet;

use App\Models\LandlordWalletSetting;
use Illuminate\Support\Facades\Cache;

/**
 * Phase-76 WALLET-DEEP AUTO-APPLY-1: resolves a landlord's effective wallet
 * auto-apply mode — the per-landlord override row when present, else the
 * config default. Cached 5 min; busted on save (mirrors HoldSettingsResolver).
 */
class WalletAutoApplyResolver
{
    private const CACHE_TTL = 300;

    public function mode(int $landlordId): string
    {
        return Cache::remember(self::cacheKey($landlordId), self::CACHE_TTL, function () use ($landlordId) {
            $row = LandlordWalletSetting::query()->where('landlord_id', $landlordId)->first();
            $mode = $row?->auto_apply_mode ?? (string) config('wallet.default_auto_apply_mode', LandlordWalletSetting::MODE_ON_INVOICE_CREATE);

            return in_array($mode, (array) config('wallet.auto_apply_modes', []), true)
                ? $mode
                : LandlordWalletSetting::MODE_ON_INVOICE_CREATE;
        });
    }

    public function appliesOnInvoiceCreate(int $landlordId): bool
    {
        return $this->mode($landlordId) === LandlordWalletSetting::MODE_ON_INVOICE_CREATE;
    }

    public function sweeps(int $landlordId): bool
    {
        return $this->mode($landlordId) === LandlordWalletSetting::MODE_OLDEST_FIRST_SWEEP;
    }

    public function flush(int $landlordId): void
    {
        Cache::forget(self::cacheKey($landlordId));
    }

    private static function cacheKey(int $landlordId): string
    {
        return "wallet:auto_apply_mode:{$landlordId}";
    }
}
