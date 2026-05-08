<?php

namespace Tests\Unit\Traits;

use App\Traits\DatabaseAgnosticQueries;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DatabaseAgnosticQueriesTest extends TestCase
{
    use DatabaseAgnosticQueries;

    public function test_get_month_sql_returns_correct_sql_for_sqlite(): void
    {
        DB::shouldReceive('getDriverName')->andReturn('sqlite');

        $result = $this->getMonthSql('payment_date');

        $this->assertEquals("CAST(strftime('%m', payment_date) AS INTEGER)", $result);
    }

    public function test_get_month_sql_returns_correct_sql_for_mysql(): void
    {
        DB::shouldReceive('getDriverName')->andReturn('mysql');

        $result = $this->getMonthSql('payment_date');

        $this->assertEquals('MONTH(payment_date)', $result);
    }

    public function test_get_month_sql_returns_correct_sql_for_pgsql(): void
    {
        DB::shouldReceive('getDriverName')->andReturn('pgsql');

        $result = $this->getMonthSql('created_at');

        $this->assertEquals('CAST(EXTRACT(MONTH FROM created_at) AS INTEGER)', $result);
    }

    public function test_get_year_sql_returns_correct_sql_for_sqlite(): void
    {
        DB::shouldReceive('getDriverName')->andReturn('sqlite');

        $result = $this->getYearSql('payment_date');

        $this->assertEquals("CAST(strftime('%Y', payment_date) AS INTEGER)", $result);
    }

    public function test_get_year_sql_returns_correct_sql_for_mysql(): void
    {
        DB::shouldReceive('getDriverName')->andReturn('mysql');

        $result = $this->getYearSql('payment_date');

        $this->assertEquals('YEAR(payment_date)', $result);
    }

    public function test_get_year_sql_returns_correct_sql_for_pgsql(): void
    {
        DB::shouldReceive('getDriverName')->andReturn('pgsql');

        $result = $this->getYearSql('created_at');

        $this->assertEquals('CAST(EXTRACT(YEAR FROM created_at) AS INTEGER)', $result);
    }

    public function test_get_date_format_sql_returns_full_date_for_sqlite(): void
    {
        DB::shouldReceive('getDriverName')->andReturn('sqlite');

        $result = $this->getDateFormatSql('payment_date', '%Y-%m-%d');

        $this->assertEquals("strftime('%Y-%m-%d', payment_date)", $result);
    }

    public function test_get_date_format_sql_returns_full_date_for_mysql(): void
    {
        DB::shouldReceive('getDriverName')->andReturn('mysql');

        $result = $this->getDateFormatSql('payment_date', '%Y-%m-%d');

        $this->assertEquals("DATE_FORMAT(payment_date, '%Y-%m-%d')", $result);
    }

    public function test_get_date_format_sql_returns_year_month_for_sqlite(): void
    {
        DB::shouldReceive('getDriverName')->andReturn('sqlite');

        $result = $this->getDateFormatSql('payment_date', '%Y-%m');

        $this->assertEquals("strftime('%Y-%m', payment_date)", $result);
    }

    public function test_get_date_format_sql_returns_year_month_for_mysql(): void
    {
        DB::shouldReceive('getDriverName')->andReturn('mysql');

        $result = $this->getDateFormatSql('payment_date', '%Y-%m');

        $this->assertEquals("DATE_FORMAT(payment_date, '%Y-%m')", $result);
    }

    public function test_get_date_format_sql_returns_year_week_for_sqlite(): void
    {
        DB::shouldReceive('getDriverName')->andReturn('sqlite');

        $result = $this->getDateFormatSql('payment_date', '%Y-%u');

        $this->assertEquals("strftime('%Y-%W', payment_date)", $result);
    }

    public function test_get_date_format_sql_returns_year_week_for_mysql(): void
    {
        DB::shouldReceive('getDriverName')->andReturn('mysql');

        $result = $this->getDateFormatSql('payment_date', '%Y-%u');

        $this->assertEquals("DATE_FORMAT(payment_date, '%x-%v')", $result);
    }

    public function test_get_date_format_sql_handles_pgsql(): void
    {
        DB::shouldReceive('getDriverName')->andReturn('pgsql');

        $this->assertEquals("TO_CHAR(payment_date, 'YYYY-MM-DD')", $this->getDateFormatSql('payment_date', '%Y-%m-%d'));
        $this->assertEquals("TO_CHAR(payment_date, 'YYYY-MM')", $this->getDateFormatSql('payment_date', '%Y-%m'));
        $this->assertEquals("TO_CHAR(payment_date, 'IYYY-IW')", $this->getDateFormatSql('payment_date', '%Y-%u'));
    }

    public function test_get_date_format_sql_handles_custom_format_for_sqlite(): void
    {
        DB::shouldReceive('getDriverName')->andReturn('sqlite');

        $result = $this->getDateFormatSql('created_at', '%Y-%j');

        $this->assertEquals("strftime('%Y-%j', created_at)", $result);
    }

    public function test_get_date_diff_sql_returns_correct_sql_for_sqlite(): void
    {
        $today = now()->format('Y-m-d');
        DB::shouldReceive('getDriverName')->andReturn('sqlite');

        $result = $this->getDateDiffSql('due_date');

        $this->assertEquals("CAST(JULIANDAY('{$today}') - JULIANDAY(due_date) AS INTEGER)", $result);
    }

    public function test_get_date_diff_sql_returns_correct_sql_for_mysql(): void
    {
        $today = now()->format('Y-m-d');
        DB::shouldReceive('getDriverName')->andReturn('mysql');

        $result = $this->getDateDiffSql('due_date');

        $this->assertEquals("DATEDIFF('{$today}', due_date)", $result);
    }

    public function test_get_date_diff_sql_returns_correct_sql_for_pgsql(): void
    {
        $today = now()->format('Y-m-d');
        DB::shouldReceive('getDriverName')->andReturn('pgsql');

        $result = $this->getDateDiffSql('due_date');

        $this->assertEquals("('{$today}'::date - due_date::date)", $result);
    }

    public function test_get_date_diff_sql_with_two_columns_for_sqlite(): void
    {
        DB::shouldReceive('getDriverName')->andReturn('sqlite');

        $result = $this->getDateDiffSql('start_date', 'end_date');

        $this->assertEquals('CAST(JULIANDAY(end_date) - JULIANDAY(start_date) AS INTEGER)', $result);
    }

    public function test_get_date_diff_sql_with_two_columns_for_mysql(): void
    {
        DB::shouldReceive('getDriverName')->andReturn('mysql');

        $result = $this->getDateDiffSql('start_date', 'end_date');

        $this->assertEquals('DATEDIFF(end_date, start_date)', $result);
    }

    public function test_get_days_between_sql_returns_correct_sql_for_sqlite(): void
    {
        DB::shouldReceive('getDriverName')->andReturn('sqlite');

        $result = $this->getDaysBetweenSql('due_date', '2024-12-31');

        $this->assertEquals("CAST(JULIANDAY('2024-12-31') - JULIANDAY(due_date) AS INTEGER)", $result);
    }

    public function test_get_days_between_sql_returns_correct_sql_for_mysql(): void
    {
        DB::shouldReceive('getDriverName')->andReturn('mysql');

        $result = $this->getDaysBetweenSql('due_date', '2024-12-31');

        $this->assertEquals("DATEDIFF('2024-12-31', due_date)", $result);
    }

    public function test_get_days_between_sql_returns_correct_sql_for_pgsql(): void
    {
        DB::shouldReceive('getDriverName')->andReturn('pgsql');

        $result = $this->getDaysBetweenSql('due_date', '2024-12-31');

        $this->assertEquals("('2024-12-31'::date - due_date::date)", $result);
    }
}
