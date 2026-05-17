<?php

declare(strict_types=1);

namespace App\Services\I18n\Drivers;

use App\Services\I18n\Contracts\TranslationDriverInterface;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Phase-52 GOOGLE-DRIVER-1/2/3: Google Cloud Translation v2 driver.
 *
 * Uses Laravel's Http facade directly (NOT the google-cloud-translate
 * SDK) — keeps composer.json clean + Http::fake() makes the driver
 * testable without real credentials.
 *
 * Endpoint: https://translation.googleapis.com/language/translate/v2
 *   - key query param carries the API key
 *   - q / source / target / format fields in the POST body
 *
 * On any non-200 response, the driver logs a warning and falls back
 * to the stub marker — a single bad row never aborts a batch.
 *
 * Rate limit: 300 req/min platform-wide (matches Google's default
 * quota). Past 300, falls back to stub for the remainder of the
 * minute. Caller can opt out by passing maxPerMinute=0.
 */
final class GoogleTranslationDriver implements TranslationDriverInterface
{
    private const ENDPOINT = 'https://translation.googleapis.com/language/translate/v2';

    private const PER_CHAR_USD = 20e-6;

    public function __construct(
        protected string $apiKey,
        protected int $maxPerMinute = 300,
    ) {}

    public function translate(string $sourceText, string $sourceLocale, string $targetLocale): string
    {
        if ($this->apiKey === '') {
            return $this->stubFallback($sourceText, $targetLocale, 'missing api key');
        }

        if ($this->maxPerMinute > 0 && ! $this->underRateLimit()) {
            return $this->stubFallback($sourceText, $targetLocale, 'rate-limit hit');
        }

        try {
            $response = Http::asForm()
                ->timeout(10)
                ->post(self::ENDPOINT . '?key=' . urlencode($this->apiKey), [
                    'q' => $sourceText,
                    'source' => $sourceLocale,
                    'target' => $targetLocale,
                    'format' => 'text',
                ]);
        } catch (\Throwable $e) {
            return $this->stubFallback($sourceText, $targetLocale, "http exception: {$e->getMessage()}");
        }

        if (! $response->successful()) {
            return $this->stubFallback($sourceText, $targetLocale, "http {$response->status()}");
        }

        return $this->extractTranslation($response, $sourceText, $targetLocale);
    }

    public function costEstimateUsd(int $characterCount): float
    {
        return $characterCount * self::PER_CHAR_USD;
    }

    public function name(): string
    {
        return 'google';
    }

    private function underRateLimit(): bool
    {
        $minute = (int) floor(microtime(true) / 60);
        $key = "i18n:google:rate:{$minute}";
        $count = (int) \Illuminate\Support\Facades\Cache::increment($key);
        if ($count === 1) {
            \Illuminate\Support\Facades\Cache::put($key, 1, 120);
        }

        return $count <= $this->maxPerMinute;
    }

    private function extractTranslation(Response $response, string $sourceText, string $targetLocale): string
    {
        $payload = $response->json();
        $translated = $payload['data']['translations'][0]['translatedText'] ?? null;

        if (! is_string($translated) || $translated === '') {
            return $this->stubFallback($sourceText, $targetLocale, 'empty translation');
        }

        return $translated;
    }

    private function stubFallback(string $sourceText, string $targetLocale, string $reason): string
    {
        Log::warning('GoogleTranslationDriver falling back to stub', [
            'reason' => $reason,
            'target_locale' => $targetLocale,
        ]);

        return (new StubTranslationDriver())->translate($sourceText, '', $targetLocale);
    }
}
