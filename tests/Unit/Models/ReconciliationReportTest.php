<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\ReconciliationReport;
use App\Models\User;
use App\ValueObjects\ReconciliationDiscrepancy;
use App\ValueObjects\ReconciliationResult;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReconciliationReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_from_result_creates_completed_report(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        $from = CarbonImmutable::parse('2026-02-01');
        $to = CarbonImmutable::parse('2026-02-11');

        $result = new ReconciliationResult(
            discrepancies: [
                ReconciliationDiscrepancy::missingLocally('REF_001', 5000.00, 'KES', 'success'),
            ],
            localCount: 10,
            remoteCount: 11,
            matchedCount: 10,
            reconciledAt: now()->toIso8601String(),
        );

        $report = ReconciliationReport::storeFromResult($landlord->id, 'paystack', $result, [$from, $to]);

        $this->assertDatabaseHas('reconciliation_reports', [
            'id' => $report->id,
            'landlord_id' => $landlord->id,
            'provider' => 'paystack',
            'status' => 'completed',
            'local_count' => 10,
            'remote_count' => 11,
            'matched_count' => 10,
            'discrepancy_count' => 1,
            'alert_sent' => false,
        ]);
        $this->assertEquals('2026-02-01', $report->period_from->toDateString());
        $this->assertEquals('2026-02-11', $report->period_to->toDateString());
        $this->assertIsArray($report->result_data);
        $this->assertCount(1, $report->result_data);
    }

    public function test_store_failed_creates_failed_report(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        $from = CarbonImmutable::parse('2026-02-01');
        $to = CarbonImmutable::parse('2026-02-11');

        $report = ReconciliationReport::storeFailed(
            $landlord->id,
            'paystack',
            'Connection timeout after 30s',
            [$from, $to],
        );

        $this->assertDatabaseHas('reconciliation_reports', [
            'id' => $report->id,
            'landlord_id' => $landlord->id,
            'provider' => 'paystack',
            'status' => 'failed',
            'error_message' => 'Connection timeout after 30s',
            'discrepancy_count' => 0,
        ]);
    }

    public function test_has_discrepancies_true_when_count_above_zero(): void
    {
        $report = ReconciliationReport::factory()->withDiscrepancies(3)->create();

        $this->assertTrue($report->hasDiscrepancies());
    }

    public function test_has_discrepancies_false_when_zero(): void
    {
        $report = ReconciliationReport::factory()->completed()->create([
            'discrepancy_count' => 0,
        ]);

        $this->assertFalse($report->hasDiscrepancies());
    }

    public function test_result_data_cast_to_array(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        $from = CarbonImmutable::parse('2026-02-01');
        $to = CarbonImmutable::parse('2026-02-11');

        $result = new ReconciliationResult(
            discrepancies: [
                ReconciliationDiscrepancy::amountMismatch('REF_002', 5000.00, 5100.00, 'KES', 'success'),
            ],
            localCount: 5,
            remoteCount: 5,
            matchedCount: 4,
            reconciledAt: now()->toIso8601String(),
        );

        $report = ReconciliationReport::storeFromResult($landlord->id, 'paystack', $result, [$from, $to]);
        $report->refresh();

        $this->assertIsArray($report->result_data);
        $this->assertEquals('amount_mismatch', $report->result_data[0]['type']);
        $this->assertEquals('REF_002', $report->result_data[0]['reference']);
        $this->assertEquals(5000.00, $report->result_data[0]['local_amount']);
        $this->assertEquals(5100.00, $report->result_data[0]['remote_amount']);
    }

    public function test_landlord_relationship(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        $report = ReconciliationReport::factory()->create(['landlord_id' => $landlord->id]);

        $this->assertTrue($report->landlord->is($landlord));
    }
}
