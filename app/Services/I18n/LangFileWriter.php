<?php

declare(strict_types=1);

namespace App\Services\I18n;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Phase-52 APPLY-WORKFLOW-1/3: safe writer for lang/<locale>/<ns>.php
 * files.
 *
 * Two contracts:
 *   1. NEVER overwrite an existing translation. The merge is deep but
 *      additive — existing keys win.
 *   2. Always snapshot the previous file content to
 *      <path>.bak.<unix-timestamp> BEFORE writing. Backup failure
 *      logs but doesn't block the write (handles first-run case where
 *      the target file doesn't exist yet).
 *
 * The write itself is atomic: var_export'd payload goes to a sibling
 * tmp file then atomic rename. Concurrent runs are not safe — callers
 * (lang:suggest --apply) hold a flock or simply don't parallelise.
 */
final class LangFileWriter
{
    public function load(string $absolutePath): array
    {
        if (! File::exists($absolutePath)) {
            return [];
        }

        $loaded = require $absolutePath;

        return is_array($loaded) ? $loaded : [];
    }

    /**
     * Deep-merge $suggestions into $existing. Existing keys NEVER
     * overwrite — that's the safety invariant.
     */
    public function merge(array $existing, array $suggestions): array
    {
        $out = $existing;
        foreach ($suggestions as $key => $value) {
            if (is_array($value) && isset($out[$key]) && is_array($out[$key])) {
                $out[$key] = $this->merge($out[$key], $value);

                continue;
            }
            if (array_key_exists($key, $out)) {
                continue;
            }
            $out[$key] = $value;
        }

        return $out;
    }

    public function write(string $absolutePath, array $payload, string $header = ''): void
    {
        $this->backup($absolutePath);

        $php = "<?php\n\n";
        if ($header !== '') {
            $php .= "/*\n * {$header}\n */\n\n";
        }
        $php .= 'return '.$this->varExport($payload).";\n";

        $tmp = $absolutePath.'.tmp.'.bin2hex(random_bytes(4));
        if (File::put($tmp, $php) === false) {
            throw new RuntimeException("Failed to write tmp file: {$tmp}");
        }
        if (! @rename($tmp, $absolutePath)) {
            @unlink($tmp);
            throw new RuntimeException("Failed to atomically rename {$tmp} -> {$absolutePath}");
        }
    }

    public function backup(string $absolutePath): ?string
    {
        if (! File::exists($absolutePath)) {
            return null;
        }

        $backupPath = $absolutePath.'.bak.'.time();
        try {
            File::copy($absolutePath, $backupPath);

            return $backupPath;
        } catch (\Throwable $e) {
            Log::warning('LangFileWriter backup failed; continuing with write', [
                'path' => $absolutePath,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function varExport(mixed $value, int $depth = 0): string
    {
        if (is_array($value)) {
            return $this->exportArray($value, $depth);
        }

        return $this->exportScalar($value);
    }

    private function exportArray(array $value, int $depth): string
    {
        $indent = str_repeat('    ', $depth);
        $isList = array_keys($value) === range(0, count($value) - 1);
        $lines = ['['];

        foreach ($value as $k => $v) {
            $lines[] = $this->exportArrayEntry($k, $v, $depth + 1, $isList);
        }

        $lines[] = "{$indent}]";

        return implode("\n", $lines);
    }

    private function exportArrayEntry(mixed $k, mixed $v, int $depth, bool $isList): string
    {
        $inner = str_repeat('    ', $depth);
        $rendered = $this->varExport($v, $depth);

        if ($isList) {
            return "{$inner}{$rendered},";
        }

        return sprintf("%s'%s' => %s,", $inner, addcslashes((string) $k, "\\'"), $rendered);
    }

    private function exportScalar(mixed $value): string
    {
        if (is_string($value)) {
            return "'".addcslashes($value, "\\'")."'";
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (is_null($value)) {
            return 'null';
        }

        return var_export($value, true);
    }
}
