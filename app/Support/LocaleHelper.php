<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Phase-43 RTL-PREP-1: locale-direction + alternate-link helpers.
 * Reads config('i18n.rtl_locales') for the RTL set; Phase-44
 * [I18N-RTL] is the mass-component-class migration cycle that
 * actually flips layouts under dir='rtl'.
 */
final class LocaleHelper
{
    public function isRtl(?string $locale = null): bool
    {
        $locale = $locale ?? (string) app()->getLocale();
        $rtl = (array) config('i18n.rtl_locales', ['ar', 'he', 'fa', 'ur']);

        return in_array($locale, $rtl, true)
            || in_array($this->primarySubtag($locale), $rtl, true);
    }

    public function dir(?string $locale = null): string
    {
        return $this->isRtl($locale) ? 'rtl' : 'ltr';
    }

    /**
     * @return array<int, array{locale: string, label: string, url: string}>
     */
    public function alternates(string $currentUrl): array
    {
        $available = (array) config('app.available_locales', ['en' => 'English']);
        if (! is_array($available) || $available === []) {
            $available = ['en' => 'English'];
        }

        $rows = [];
        foreach ($available as $code => $label) {
            $code = (string) $code;
            $url = $this->appendQueryParam($currentUrl, 'locale', $code);
            $rows[] = [
                'locale' => $code,
                'label' => (string) $label,
                'url' => $url,
            ];
        }

        return $rows;
    }

    private function primarySubtag(string $locale): string
    {
        $locale = str_replace('_', '-', $locale);

        return strtolower(explode('-', $locale, 2)[0] ?? $locale);
    }

    private function appendQueryParam(string $url, string $key, string $value): string
    {
        $separator = str_contains($url, '?') ? '&' : '?';

        return $url.$separator.$key.'='.urlencode($value);
    }
}
