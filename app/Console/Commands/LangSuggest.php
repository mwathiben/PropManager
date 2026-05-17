<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\I18n\TranslationSuggestionService;
use App\Support\LangBundleLoader;
use Illuminate\Console\Command;

/**
 * Phase-43 LANG-AUDIT-3: seeds missing translations for a given
 * namespace using the configured suggestion driver. Output is a
 * ready-to-paste PHP array fragment the contributor edits and
 * commits.
 *
 * Default driver=stub emits [TODO:<locale>] <english> placeholders
 * so the workflow proves out without API credentials.
 */
class LangSuggest extends Command
{
    protected $signature = 'lang:suggest {namespace} {--source=en} {--target=sw} {--driver=}';

    protected $description = 'Phase-43 LANG-AUDIT-3: emit suggested translations for missing keys in a namespace.';

    public function handle(LangBundleLoader $loader): int
    {
        $namespace = (string) $this->argument('namespace');
        $sourceLocale = (string) $this->option('source');
        $targetLocale = (string) $this->option('target');
        $driverOverride = $this->option('driver');

        $source = $loader->load($sourceLocale);
        $sourceSection = $source[$namespace] ?? null;
        if (! is_array($sourceSection)) {
            $this->error("Namespace `{$namespace}` not found in `{$sourceLocale}` bundle.");

            return self::FAILURE;
        }

        $target = $loader->load($targetLocale);
        $targetSection = is_array($target[$namespace] ?? null) ? $target[$namespace] : [];

        $service = $driverOverride !== null
            ? new TranslationSuggestionService(driver: (string) $driverOverride)
            : TranslationSuggestionService::fromConfig();

        $sourceKeys = $loader->flatten($sourceSection, $namespace);
        $targetKeys = $loader->flatten($targetSection, $namespace);
        $missing = array_values(array_diff($sourceKeys, $targetKeys));

        if ($missing === []) {
            $this->info("No missing keys for {$targetLocale}/{$namespace}.");

            return self::SUCCESS;
        }

        $this->line("<?php");
        $this->line("// Suggestions for lang/{$targetLocale}/{$namespace}.php (Phase-43 LANG-AUDIT-3 stub output).");
        $this->line("// Replace each [TODO:{$targetLocale}] line with the human translation.");
        $this->line('return [');
        foreach ($missing as $dottedKey) {
            $segments = explode('.', $dottedKey);
            // Drop the namespace prefix; we're inside a return [...] for that ns.
            array_shift($segments);
            $arrayPath = implode("']['", $segments);
            $sourceValue = $this->dataGet($sourceSection, $segments);
            if (! is_string($sourceValue)) {
                continue;
            }
            $suggestion = $service->suggest($sourceValue, $sourceLocale, $targetLocale);
            $escaped = addcslashes($suggestion, "\\'");
            $this->line(sprintf("    '%s' => '%s',", $arrayPath, $escaped));
        }
        $this->line('];');

        return self::SUCCESS;
    }

    /**
     * @param  array<int, string>  $segments
     */
    private function dataGet(array $haystack, array $segments): mixed
    {
        $cursor = $haystack;
        foreach ($segments as $segment) {
            if (! is_array($cursor) || ! array_key_exists($segment, $cursor)) {
                return null;
            }
            $cursor = $cursor[$segment];
        }

        return $cursor;
    }
}
