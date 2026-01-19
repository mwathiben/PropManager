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
            default => "YEAR({$column})",
        };
    }

    /**
     * Get SQL to format a date column with a specific format.
     *
     * Common formats supported:
     * - '%Y-%m-%d' : Full date (2024-01-15)
     * - '%Y-%m'    : Year and month (2024-01)
     * - '%Y-%W'    : Year and week number (2024-03)
     *
     * @param  string  $column  The date column name
     * @param  string  $format  Date format string (MySQL DATE_FORMAT style)
     * @return string SQL expression that formats the date
     */
    protected function getDateFormatSql(string $column, string $format): string
    {
        $driver = DB::getDriverName();

        return match ($driver) {
            'sqlite' => match ($format) {
                '%Y-%m-%d' => "strftime('%Y-%m-%d', {$column})",
                '%Y-%m' => "strftime('%Y-%m', {$column})",
                '%Y-%W', '%Y-%u' => "strftime('%Y-%W', {$column})",
                default => "strftime('{$format}', {$column})",
            },
            'pgsql' => match ($format) {
                '%Y-%m-%d' => "TO_CHAR({$column}, 'YYYY-MM-DD')",
                '%Y-%m' => "TO_CHAR({$column}, 'YYYY-MM')",
                '%Y-%W', '%Y-%u' => "TO_CHAR({$column}, 'IYYY-IW')",
                default => "TO_CHAR({$column}, '{$format}')",
            },
            default => "DATE_FORMAT({$column}, '{$format}')",
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
