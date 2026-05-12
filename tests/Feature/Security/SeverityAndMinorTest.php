<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Models\SecurityIncident;
use App\Services\KenyaDpaService;
use Tests\TestCase;

/**
 * Phase-13 BREACH-8 + DPA-10 regression coverage.
 *   - classifySeverity returns the right level for each combination
 *   - assessBreachSeverity now derives financial_data from data types
 *   - isMinor handles known DOBs and fails safe on garbage input
 */
class SeverityAndMinorTest extends TestCase
{
    public function test_classify_severity_critical_path(): void
    {
        $dpa = app(KenyaDpaService::class);
        $this->assertSame(
            SecurityIncident::SEVERITY_CRITICAL,
            $dpa->classifySeverity(affectedCount: 200, sensitiveData: true, financialData: false),
        );
    }

    public function test_classify_severity_high_via_sensitive_data(): void
    {
        $dpa = app(KenyaDpaService::class);
        $this->assertSame(
            SecurityIncident::SEVERITY_HIGH,
            $dpa->classifySeverity(affectedCount: 5, sensitiveData: true, financialData: false),
        );
    }

    public function test_classify_severity_high_via_large_affected_count(): void
    {
        $dpa = app(KenyaDpaService::class);
        $this->assertSame(
            SecurityIncident::SEVERITY_HIGH,
            $dpa->classifySeverity(affectedCount: 600, sensitiveData: false, financialData: false),
        );
    }

    public function test_classify_severity_medium_via_financial_data(): void
    {
        $dpa = app(KenyaDpaService::class);
        $this->assertSame(
            SecurityIncident::SEVERITY_MEDIUM,
            $dpa->classifySeverity(affectedCount: 5, sensitiveData: false, financialData: true),
        );
    }

    public function test_classify_severity_low_default(): void
    {
        $dpa = app(KenyaDpaService::class);
        $this->assertSame(
            SecurityIncident::SEVERITY_LOW,
            $dpa->classifySeverity(affectedCount: 1, sensitiveData: false, financialData: false),
        );
    }

    public function test_is_minor_handles_known_dates(): void
    {
        $dpa = app(KenyaDpaService::class);
        $twentyYearsAgo = now()->subYears(20)->toDateString();
        $tenYearsAgo = now()->subYears(10)->toDateString();

        $this->assertFalse($dpa->isMinor($twentyYearsAgo));
        $this->assertTrue($dpa->isMinor($tenYearsAgo));
    }

    public function test_is_minor_fails_safe_on_garbage_input(): void
    {
        $dpa = app(KenyaDpaService::class);
        // Malformed DOB — must return true so the gate trips and
        // forces operator review.
        $this->assertTrue($dpa->isMinor('not-a-date'));
        $this->assertTrue($dpa->isMinor(''));
    }

    public function test_is_minor_with_custom_minimum_age(): void
    {
        $dpa = app(KenyaDpaService::class);
        $fifteenYearsAgo = now()->subYears(15)->toDateString();

        // 15-year-old is a minor at threshold 18 but not at 13.
        $this->assertTrue($dpa->isMinor($fifteenYearsAgo, 18));
        $this->assertFalse($dpa->isMinor($fifteenYearsAgo, 13));
    }
}
