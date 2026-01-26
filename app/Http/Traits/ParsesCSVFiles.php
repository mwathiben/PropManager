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
        $rows = [];

        if (empty($lines)) {
            return [];
        }

        // Parse headers from first line
        $headers = str_getcsv(array_shift($lines));

        if (empty($headers) || $headers === [null]) {
            return [];
        }

        // Clean headers (trim, lowercase, replace spaces with underscores)
        $headers = array_map(
            fn ($h) => strtolower(trim(str_replace(' ', '_', (string) $h))),
            $headers
        );

        // Parse remaining rows
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
