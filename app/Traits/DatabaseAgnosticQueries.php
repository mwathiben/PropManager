<?php

namespace App\Traits;

use Illuminate\Support\Facades\DB;

/**
 * Provides database-agnostic SQL generation for date operations.
 *
 * This trait abstracts the differences between SQLite, MySQL, and PostgreSQL
 * date functions, allowing services to write queries that work across all
 * supported database drivers.
 *
 * @example
 * class MyService
 * {
 *     use DatabaseAgnosticQueries;
 *
 *     public function getMonthlyStats()
 *     {
 *         $monthSql = $this->getMonthSql('payment_date');
 *         $yearSql = $this->getYearSql('payment_date');
 *
 *         return Payment::selectRaw("{$monthSql} as month, {$yearSql} as year, SUM(amount) as total")
 *             ->groupByRaw("{$monthSql}, {$yearSql}")
 *             ->get();
 *     }
 * }
 */
trait DatabaseAgnosticQueries
{
    /**
     * Get SQL to extract month from a date column.
     *
     * SQLite: strftime('%m', column) returns '01'-'12' as string
     * MySQL: MONTH(column) returns 1-12 as integer
     *
     * @param  string  $column  The date column name (e.g., 'payment_date', 'created_at')
     * @return string SQL expression that extracts the month as an integer
     */
    protected function getMonthSql(string $column): string
    {
        return match (DB::getDriverName()) {
            'sqlite' => "CAST(strftime('%m', {$column}) AS INTEGER)",
            'pgsql' => "CAST(EXTRACT(MONTH FROM {$column}) AS INTEGER)",
            default => "MONTH({$column})",
        };
    }

    /**
     * Get SQL to extract year from a date column.
     *
     * SQLite: strftime('%Y', column) returns '2024' as string
     * MySQL: YEAR(column) returns 2024 as integer
     *
     * @param  string  $column  The date column name
     * @return string SQL expression that extracts the year as an integer
     */
    protected function getYearSql(string $column): string
    {
        return match (DB::getDriverName()) {
            'sqlite' => "CAST(strftime('%Y', {$column}) AS INTEGER)",
            'pgsql' => "CAST(EXTRACT(YEAR FROM {$column}) AS INTEGER)",
            default => "YEAR({$column})",
        };
    }

    /**
     * Token mapping from MySQL DATE_FORMAT tokens to other database formats.
     *
     * Supported tokens:
     * - %Y : 4-digit year (2024)
     * - %m : 2-digit month (01-12)
     * - %d : 2-digit day (01-31)
     * - %u : ISO week number (01-53)
     *        - SQLite: %W (week 00-53, Monday as first day, but uses calendar year not ISO year)
     *        - PostgreSQL: IW (ISO week 01-53)
     *        - MySQL: %v (ISO week 01-53; note: %u is week 01-53 with Monday start but NOT ISO)
     * - %W : Weekday name (Monday..Sunday) in MySQL - DO NOT use for week number
     *
     * Note: For year+week formatting, use '%Y-%u' (ISO week number).
     * '%Y-%W' is NOT supported as %W means weekday name in MySQL.
     *
     * WARNING: When using '%u' token alone (not as '%Y-%u'), SQLite will use calendar year
     * which may differ from ISO year at year boundaries (e.g., Dec 31 or Jan 1).
     * The predefined '%Y-%u' format handles this correctly for MySQL/PostgreSQL but
     * SQLite lacks native ISO week-year support.
     */
    private const FORMAT_TOKEN_MAP = [
        // Token => [sqlite, pgsql, mysql]
        '%Y' => ['%Y', 'YYYY', '%Y'],
        '%m' => ['%m', 'MM', '%m'],
        '%d' => ['%d', 'DD', '%d'],
        '%u' => ['%W', 'IW', '%v'],  // ISO week number (MySQL %v is ISO week, %u is NOT ISO)
        '%H' => ['%H', 'HH24', '%H'],
        '%i' => ['%M', 'MI', '%i'],
        '%s' => ['%S', 'SS', '%s'],
        '%j' => ['%j', 'DDD', '%j'], // Day of year
    ];

    /**
     * Get SQL to format a date column with a specific format.
     *
     * Common formats supported:
     * - '%Y-%m-%d' : Full date (2024-01-15)
     * - '%Y-%m'    : Year and month (2024-01)
     * - '%Y-%u'    : Year and ISO week number (2024-03)
     *
     * @param  string  $column  The date column name
     * @param  string  $format  Date format string (MySQL DATE_FORMAT style)
     * @return string SQL expression that formats the date
     *
     * @throws \InvalidArgumentException If format contains unsupported tokens
     */
    protected function getDateFormatSql(string $column, string $format): string
    {
        $driver = DB::getDriverName();

        // Handle common pre-defined formats for performance and clarity
        $predefinedFormats = $this->getPredefinedDateFormats($column, $format, $driver);
        if ($predefinedFormats !== null) {
            return $predefinedFormats;
        }

        // For custom formats, translate tokens based on driver
        return $this->translateDateFormat($column, $format, $driver);
    }

    /**
     * Get predefined date format SQL for common formats.
     *
     * NOTE ON ISO WEEK-YEAR:
     * - PostgreSQL IYYY-IW and MySQL %x-%v correctly return ISO week-year (e.g., "2025-01" for Dec 29, 2024)
     * - SQLite strftime('%Y-%W', ...) returns CALENDAR year with week number, NOT ISO week-year.
     *   This means dates near year boundaries may show different year values in SQLite vs MySQL/PostgreSQL.
     *   Example: Dec 31, 2024 (ISO week 1 of 2025) returns "2024-53" in SQLite but "2025-01" in MySQL/PG.
     *   If precise ISO week-year semantics are required for SQLite, consider computing ISO year separately
     *   or using application-level date handling instead of database functions.
     */
    private function getPredefinedDateFormats(string $column, string $format, string $driver): ?string
    {
        return match ($driver) {
            // SQLite: Uses strftime with format specifiers
            // LIMITATION: '%Y-%W' uses calendar year, not ISO week-year (see note above)
            'sqlite' => match ($format) {
                '%Y-%m-%d' => "strftime('%Y-%m-%d', {$column})",
                '%Y-%m' => "strftime('%Y-%m', {$column})",
                '%Y-%u' => "strftime('%Y-%W', {$column})",  // WARNING: Calendar year, not ISO week-year
                default => null,
            },
            // PostgreSQL: Uses TO_CHAR with IYYY for ISO week-year, IW for ISO week
            'pgsql' => match ($format) {
                '%Y-%m-%d' => "TO_CHAR({$column}, 'YYYY-MM-DD')",
                '%Y-%m' => "TO_CHAR({$column}, 'YYYY-MM')",
                '%Y-%u' => "TO_CHAR({$column}, 'IYYY-IW')",  // ISO week-year + ISO week
                default => null,
            },
            // MySQL: Uses DATE_FORMAT with %x for ISO week-year, %v for ISO week
            default => match ($format) {
                '%Y-%m-%d' => "DATE_FORMAT({$column}, '%Y-%m-%d')",
                '%Y-%m' => "DATE_FORMAT({$column}, '%Y-%m')",
                '%Y-%u' => "DATE_FORMAT({$column}, '%x-%v')",  // ISO week-year + ISO week
                default => null,
            },
        };
    }

    /**
     * Translate date format tokens for the given driver.
     *
     * @throws \InvalidArgumentException If format contains unsupported tokens
     */
    private function translateDateFormat(string $column, string $format, string $driver): string
    {
        // Validate and translate tokens
        $translatedFormat = $format;
        $driverIndex = match ($driver) {
            'sqlite' => 0,
            'pgsql' => 1,
            default => 2,  // MySQL
        };

        // Find all tokens in the format string
        preg_match_all('/%[a-zA-Z]/', $format, $matches);
        $tokens = $matches[0] ?? [];

        foreach ($tokens as $token) {
            if (! isset(self::FORMAT_TOKEN_MAP[$token])) {
                throw new \InvalidArgumentException(
                    "Unsupported date format token '{$token}' in format '{$format}'. "
                    .'Supported tokens: '.implode(', ', array_keys(self::FORMAT_TOKEN_MAP))
                );
            }
            $translatedFormat = str_replace(
                $token,
                self::FORMAT_TOKEN_MAP[$token][$driverIndex],
                $translatedFormat
            );
        }

        return match ($driver) {
            'sqlite' => "strftime('{$translatedFormat}', {$column})",
            'pgsql' => "TO_CHAR({$column}, '{$translatedFormat}')",
            default => "DATE_FORMAT({$column}, '{$translatedFormat}')",
        };
    }

    /**
     * Get SQL to calculate the difference in days between two dates.
     *
     * Positive result means the first date is in the past relative to the reference.
     * If no second column is provided, uses current date as reference.
     *
     * @param  string  $column  The date column to compare (typically the older date)
     * @param  string|null  $column2  Optional reference date column (defaults to today)
     * @return string SQL expression that returns integer days difference
     */
    protected function getDateDiffSql(string $column, ?string $column2 = null): string
    {
        $driver = DB::getDriverName();
        $reference = $column2 ?? "'".now()->format('Y-m-d')."'";

        return match ($driver) {
            'sqlite' => "CAST(JULIANDAY({$reference}) - JULIANDAY({$column}) AS INTEGER)",
            'pgsql' => "({$reference}::date - {$column}::date)",
            default => "DATEDIFF({$reference}, {$column})",
        };
    }

    /**
     * Get SQL to calculate days between a column and a specific reference date.
     *
     * This is useful when the reference date is a literal value known at query time.
     *
     * @param  string  $column  The date column name
     * @param  string  $referenceDate  The reference date in Y-m-d format
     * @return string SQL expression that returns integer days difference
     */
    protected function getDaysBetweenSql(string $column, string $referenceDate): string
    {
        $driver = DB::getDriverName();

        return match ($driver) {
            'sqlite' => "CAST(JULIANDAY('{$referenceDate}') - JULIANDAY({$column}) AS INTEGER)",
            'pgsql' => "('{$referenceDate}'::date - {$column}::date)",
            default => "DATEDIFF('{$referenceDate}', {$column})",
        };
    }
}
