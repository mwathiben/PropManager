<?php

declare(strict_types=1);

namespace App\Services\I18n\Drivers;

use App\Services\I18n\Contracts\TranslationDriverInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Phase-52 DEEPL-DRIVER-1/2/3: DeepL translation driver via Http
 * facade.
 *
 * Endpoint auto-detection: free-tier keys end in ':fx' → uses
 * api-free.deepl.com; pro keys → api.deepl.com. One env var works
 * for both tiers.
 *
 * Formality: when the constructor is given a formality and the
 * target locale supports it (de/fr/es/it/nl/pl/pt/ja/ru), the
 * request carries the formality field. Locales that don't support
 * formality ignore the field server-side, but we send it only when
 * supported to keep request shape clean.
 *
 * Glossary: pass-through of an optional glossary_id; DeepL pro
 * accounts can register a custom glossary via the dashboard.
 */
final class DeepLTranslationDriver implements TranslationDriverInterface
{
    private const PER_CHAR_USD = 25e-6;

    private const FORMALITY_LOCALES = ['de', 'fr', 'es', 'it', 'nl', 'pl', 'pt', 'ja', 'ru'];

    public function __construct(
        protected string $apiKey,
        protected ?string $formality = null,
        protected ?string $glossaryId = null,
    ) {}

    public function translate(string $sourceText, string $sourceLocale, string $targetLocale): string
    {
        if ($this->apiKey === '') {
            return $this->stubFallback($sourceText, $targetLocale, 'missing api key');
        }

        $payload = [
            'text' => [$sourceText],
            'source_lang' => strtoupper($sourceLocale),
            'target_lang' => strtoupper($targetLocale),
        ];

        if ($this->formality !== null && in_array($targetLocale, self::FORMALITY_LOCALES, true)) {
            $payload['formality'] = $this->formality;
        }

        if ($this->glossaryId !== null) {
            $payload['glossary_id'] = $this->glossaryId;
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'DeepL-Auth-Key ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])
                ->timeout(10)
                ->post($this->endpointBase() . '/v2/translate', $payload);
        } catch (\Throwable $e) {
            return $this->stubFallback($sourceText, $targetLocale, "http exception: {$e->getMessage()}");
        }

        if (! $response->successful()) {
            return $this->stubFallback($sourceText, $targetLocale, "http {$response->status()}");
        }

        $translated = $response->json('translations.0.text');
        if (! is_string($translated) || $translated === '') {
            return $this->stubFallback($sourceText, $targetLocale, 'empty translation');
        }

        return $translated;
    }

    public function costEstimateUsd(int $characterCount): float
    {
        return $characterCount * self::PER_CHAR_USD;
    }

    public function name(): string
    {
        return 'deepl';
    }

    public function endpointBase(): string
    {
        return str_ends_with($this->apiKey, ':fx')
            ? 'https://api-free.deepl.com'
            : 'https://api.deepl.com';
    }

    private function stubFallback(string $sourceText, string $targetLocale, string $reason): string
    {
        Log::warning('DeepLTranslationDriver falling back to stub', [
            'reason' => $reason,
            'target_locale' => $targetLocale,
        ]);

        return (new StubTranslationDriver())->translate($sourceText, '', $targetLocale);
    }
}
