<?php

declare(strict_types=1);

namespace App\Services\Legal;

use App\Models\LandlordHoldSettings;
use Illuminate\Support\Facades\Cache;

/**
 * Phase-72 HOLD-SETTINGS: resolves a landlord's effective legal-hold settings —
 * the per-landlord override row when present, else the Phase-68 global config
 * default, per key. Cached 5 min; busted on save.
 */
class HoldSettingsResolver
{
    private const CACHE_TTL = 300;

    /**
     * @return array{stale_after_days:int, reminder_cooldown_days:int, matter_reference_format:?string, reminder_recipients:array<int,string>, auto_hold_on_eviction:bool}
     */
    public function effective(int $landlordId): array
    {
        return Cache::remember(self::cacheKey($landlordId), self::CACHE_TTL, function () use ($landlordId) {
            $row = LandlordHoldSettings::query()->where('landlord_id', $landlordId)->first();

            return [
                'stale_after_days' => $row?->stale_after_days ?? (int) config('legal_hold.stale_after_days', 365),
                'reminder_cooldown_days' => $row?->reminder_cooldown_days ?? (int) config('legal_hold.stale_reminder_cooldown_days', 30),
                'matter_reference_format' => $row?->matter_reference_format,
                'reminder_recipients' => array_values(array_filter((array) ($row?->reminder_recipients ?? []))),
                'auto_hold_on_eviction' => (bool) ($row?->auto_hold_on_eviction ?? false),
            ];
        });
    }

    public function staleAfterDays(int $landlordId): int
    {
        return $this->effective($landlordId)['stale_after_days'];
    }

    public function reminderCooldownDays(int $landlordId): int
    {
        return $this->effective($landlordId)['reminder_cooldown_days'];
    }

    public function flush(int $landlordId): void
    {
        Cache::forget(self::cacheKey($landlordId));
    }

    private static function cacheKey(int $landlordId): string
    {
        return "legal_hold:settings:{$landlordId}";
    }
}
