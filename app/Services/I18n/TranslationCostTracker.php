<?php

declare(strict_types=1);

namespace App\Services\I18n;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Phase-52 COST-GUARD-1/2: tracks per-day translation spend with a
 * hard budget refusal at config('i18n.daily_budget_usd', 20).
 *
 * Storage is Laravel Cache with a 25-hour TTL so a daily roll-over
 * naturally drops old data. Per-locale gauges share the same
 * pattern with a different cache key.
 *
 * canSpend(estimatedUsd) is the pre-flight check — returns false if
 * the prospective spend would push the daily total past the budget.
 * record() is the post-call ledger — call AFTER a successful API
 * response (failed calls don't count).
 */
final class TranslationCostTracker
{
    private const TTL_SECONDS = 25 * 3600;

    public function canSpend(float $estimatedUsd): bool
    {
        $budget = (float) config('i18n.daily_budget_usd', 20.0);
        $current = $this->currentDailySpend();

        return $current + $estimatedUsd <= $budget;
    }

    public function record(string $driver, string $locale, int $characterCount, float $costUsd): void
    {
        if ($costUsd <= 0) {
            return;
        }

        $today = $this->today();
        Cache::increment($this->totalKey($today), (int) round($costUsd * 1_000_000));
        Cache::increment($this->localeKey($today, $locale), (int) round($costUsd * 1_000_000));
        Cache::put($this->totalKeyTtlMarker($today), 1, self::TTL_SECONDS);
        Cache::put($this->localeKeyTtlMarker($today, $locale), 1, self::TTL_SECONDS);
    }

    public function rateLimit(string $driver, int $maxPerMinute): bool
    {
        $minute = (int) floor(microtime(true) / 60);
        $key = "i18n:translation:rate:{$driver}:{$minute}";
        $count = (int) Cache::increment($key);
        if ($count === 1) {
            Cache::put($key, 1, 120);
        }

        if ($count > $maxPerMinute) {
            Log::warning('Translation rate-limit hit', ['driver' => $driver, 'count' => $count, 'limit' => $maxPerMinute]);

            return false;
        }

        return true;
    }

    public function currentDailySpend(): float
    {
        return $this->microUsdToUsd((int) Cache::get($this->totalKey($this->today()), 0));
    }

    public function localeDailySpend(string $locale): float
    {
        return $this->microUsdToUsd((int) Cache::get($this->localeKey($this->today(), $locale), 0));
    }

    private function today(): string
    {
        return Carbon::now('UTC')->format('Y-m-d');
    }

    private function totalKey(string $date): string
    {
        return "i18n:translation:spend:daily:total:{$date}";
    }

    private function totalKeyTtlMarker(string $date): string
    {
        return "i18n:translation:spend:daily:total:{$date}:ttl";
    }

    private function localeKey(string $date, string $locale): string
    {
        return "i18n:translation:spend:daily:locale:{$locale}:{$date}";
    }

    private function localeKeyTtlMarker(string $date, string $locale): string
    {
        return "i18n:translation:spend:daily:locale:{$locale}:{$date}:ttl";
    }

    private function microUsdToUsd(int $microUsd): float
    {
        return $microUsd / 1_000_000;
    }
}
