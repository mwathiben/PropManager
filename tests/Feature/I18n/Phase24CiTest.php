<?php

declare(strict_types=1);

namespace Tests\Feature\I18n;

use Tests\TestCase;

/**
 * Phase-24 I18N-CI-1: the i18n watchdog suite. A translation effort
 * rots silently — a new English key ships without its Swahili pair,
 * or the resolver wiring gets refactored away. These cheap
 * source-level assertions (the Phase-18/19/21/22/23 watchdog
 * pattern) are the guard.
 *
 *  - KEY PARITY: every supported locale's PHP lang files + JSON
 *    bundle have EXACTLY the English key set — no missing keys
 *    (would fall back silently), no orphan keys (dead translations).
 *  - PLACEHOLDER PARITY: the :token / {token} placeholders in each
 *    string match between English and the translation.
 *  - WIRING: the resolver middleware, the Inertia share, the
 *    vue-i18n engine, and the supported-locale config are all in
 *    place.
 */
class Phase24CiTest extends TestCase
{
    /**
     * Every supported locale beyond `en` mirrors the English key set,
     * across both the PHP lang files and the JSON frontend bundle.
     */
    public function test_lang_key_parity_across_locales(): void
    {
        $locales = array_keys(config('app.available_locales'));
        $this->assertContains('en', $locales, 'en must be a supported locale.');

        $phpFiles = glob(lang_path('en/*.php'));
        $this->assertNotEmpty($phpFiles, 'lang/en must contain PHP lang files.');

        foreach ($locales as $locale) {
            if ($locale === 'en') {
                continue;
            }

            // --- PHP lang files ---
            foreach ($phpFiles as $enFile) {
                $name = basename($enFile);
                $translatedPath = lang_path("{$locale}/{$name}");
                $this->assertFileExists(
                    $translatedPath,
                    "I18N-CI-1: lang/{$locale}/{$name} must exist (mirror of lang/en/{$name}).",
                );

                $en = $this->flatten(require $enFile);
                $translated = $this->flatten(require $translatedPath);

                $this->assertSame(
                    array_keys($en),
                    array_keys($translated),
                    "I18N-CI-1: lang/{$locale}/{$name} key set must match lang/en/{$name} exactly.",
                );

                foreach ($en as $key => $enValue) {
                    $this->assertSame(
                        $this->placeholders($enValue),
                        $this->placeholders($translated[$key]),
                        "I18N-CI-1: placeholder tokens for '{$key}' in lang/{$locale}/{$name} must match lang/en/{$name}.",
                    );
                }
            }

            // --- JSON frontend bundle ---
            $enJson = $this->flatten($this->readJson(lang_path('en.json')));
            $translatedJsonPath = lang_path("{$locale}.json");
            $this->assertFileExists(
                $translatedJsonPath,
                "I18N-CI-1: lang/{$locale}.json frontend bundle must exist.",
            );
            $translatedJson = $this->flatten($this->readJson($translatedJsonPath));

            $this->assertSame(
                array_keys($enJson),
                array_keys($translatedJson),
                "I18N-CI-1: lang/{$locale}.json key set must match lang/en.json exactly.",
            );
            foreach ($enJson as $key => $enValue) {
                $this->assertSame(
                    $this->placeholders($enValue),
                    $this->placeholders($translatedJson[$key]),
                    "I18N-CI-1: placeholder tokens for '{$key}' in lang/{$locale}.json must match lang/en.json.",
                );
            }
        }
    }

    public function test_i18n_wiring_is_intact(): void
    {
        $bootstrap = file_get_contents(base_path('bootstrap/app.php'));
        $this->assertStringContainsString(
            'App\Http\Middleware\SetLocale::class',
            $bootstrap,
            'I18N-CI-1: SetLocale middleware must stay registered.',
        );

        $inertia = file_get_contents(app_path('Http/Middleware/HandleInertiaRequests.php'));
        foreach (["'locale'", "'availableLocales'", "'i18n'"] as $prop) {
            $this->assertStringContainsString(
                $prop,
                $inertia,
                "I18N-CI-1: HandleInertiaRequests must keep sharing {$prop}.",
            );
        }

        $appJs = file_get_contents(resource_path('js/app.js'));
        $this->assertStringContainsString(
            'createI18n(',
            $appJs,
            'I18N-CI-1: app.js must keep wiring the vue-i18n engine.',
        );

        $this->assertIsArray(
            config('app.available_locales'),
            'I18N-CI-1: config(app.available_locales) must remain the supported-locale source of truth.',
        );
    }

    /**
     * @param  array<string, mixed>  $array
     * @return array<string, mixed>
     */
    private function flatten(array $array, string $prefix = ''): array
    {
        $result = [];
        foreach ($array as $key => $value) {
            $compound = $prefix === '' ? (string) $key : "{$prefix}.{$key}";
            if (is_array($value)) {
                $result += $this->flatten($value, $compound);
            } else {
                $result[$compound] = $value;
            }
        }

        return $result;
    }

    /**
     * Sorted list of placeholder tokens in a string — Laravel's
     * :token style and vue-i18n's {token} style.
     *
     * @return array<int, string>
     */
    private function placeholders(mixed $value): array
    {
        if (! is_string($value)) {
            return [];
        }
        preg_match_all('/:[a-zA-Z_]+|\{[a-zA-Z_]+\}/', $value, $matches);
        $tokens = array_unique($matches[0]);
        sort($tokens);

        return array_values($tokens);
    }

    /**
     * @return array<string, mixed>
     */
    private function readJson(string $path): array
    {
        return json_decode(file_get_contents($path), true) ?: [];
    }
}
