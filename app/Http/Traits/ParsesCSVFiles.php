<?php

declare(strict_types=1);

namespace App\Http\Traits;

use Illuminate\Support\Facades\Storage;

/**
 * Provides CSV parsing functionality using Laravel's Storage facade.
 *
 * Use this trait instead of raw fopen/fgetcsv/fclose patterns to ensure
 * consistent file access through the Storage abstraction layer.
 */
trait ParsesCSVFiles
{
    /**
     * Parse a CSV file from storage into an array of associative arrays.
     *
     * @param  string  $path  Relative path within the storage disk
     * @param  string  $disk  Storage disk name (default: 'local')
     * @return array<int, array<string, string>> Array of rows with headers as keys
     */
    protected function parseCSVFromStorage(string $path, string $disk = 'local'): array
    {
        $content = Storage::disk($disk)->get($path);

        if ($content === null) {
            return [];
        }

        return $this->parseCSVContent($content);
    }

    /**
     * Parse CSV content string into an array of associative arrays.
     *
     * @param  string  $content  Raw CSV content
     * @return array<int, array<string, string>> Array of rows with headers as keys
     */
    protected function parseCSVContent(string $content): array
    {
        $lines = explode("\n", str_replace("\r\n", "\n", $content));

        if (empty($lines)) {
            return [];
        }

        $headers = $this->extractHeaders($lines);

        if ($headers === null) {
            return [];
        }

        return $this->parseDataRows($lines, $headers);
    }

    /**
     * Extract and normalise the header row from the lines array (mutates $lines via array_shift).
     *
     * @param  array<int, string>  $lines
     * @return array<int, string>|null Normalised headers, or null when the header row is empty/invalid.
     */
    private function extractHeaders(array &$lines): ?array
    {
        $headers = str_getcsv(array_shift($lines));

        if (empty($headers) || $headers === [null]) {
            return null;
        }

        return array_map(
            fn ($h) => strtolower(trim(str_replace(' ', '_', (string) $h))),
            $headers
        );
    }

    /**
     * Parse the data rows using the provided headers.
     *
     * @param  array<int, string>  $lines
     * @param  array<int, string>  $headers
     * @return array<int, array<string, string>>
     */
    private function parseDataRows(array $lines, array $headers): array
    {
        $rows = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }

            $row = str_getcsv($line);

            if (count($row) === count($headers)) {
                $rows[] = array_combine($headers, $row);
            }
        }

        return $rows;
    }

    /**
     * Get the absolute path for a storage file.
     *
     * @param  string  $path  Relative path within the storage disk
     * @param  string  $disk  Storage disk name (default: 'local')
     */
    protected function getStoragePath(string $path, string $disk = 'local'): string
    {
        return Storage::disk($disk)->path($path);
    }
}
