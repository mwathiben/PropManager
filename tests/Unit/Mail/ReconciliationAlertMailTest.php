<?php

declare(strict_types=1);

namespace Tests\Unit\Mail;

use App\Mail\ReconciliationAlert;
use App\Models\ReconciliationReport;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReconciliationAlertMailTest extends TestCase
{
    use RefreshDatabase;

    public function test_subject_contains_discrepancy_count(): void
    {
        $report = ReconciliationReport::factory()->withDiscrepancies(5)->create();

        $mailable = new ReconciliationAlert($report);

        $mailable->assertHasSubject('Payment Reconciliation Alert - 5 discrepancies found');
    }

    public function test_content_includes_period_and_provider(): void
    {
        $report = ReconciliationReport::factory()->create([
            'provider' => 'paystack',
            'period_from' => '2026-02-01',
            'period_to' => '2026-02-11',
            'discrepancy_count' => 3,
            'result_data' => [
                ['type' => 'missing_locally', 'reference' => 'REF_001', 'local_amount' => null, 'remote_amount' => 5000.00, 'currency' => 'KES', 'remote_status' => 'success'],
                ['type' => 'missing_remotely', 'reference' => 'REF_002', 'local_amount' => 3000.00, 'remote_amount' => null, 'currency' => 'KES', 'remote_status' => null],
                ['type' => 'amount_mismatch', 'reference' => 'REF_003', 'local_amount' => 2000.00, 'remote_amount' => 2100.00, 'currency' => 'KES', 'remote_status' => 'success'],
            ],
        ]);

        $mailable = new ReconciliationAlert($report);

        $mailable->assertSeeInHtml('Paystack');
        $mailable->assertSeeInHtml('Feb 01, 2026');
        $mailable->assertSeeInHtml('Feb 11, 2026');
    }

    public function test_mailable_is_queued_with_after_commit(): void
    {
        $this->assertTrue(
            in_array(ShouldQueue::class, class_implements(ReconciliationAlert::class)),
            'ReconciliationAlert should implement ShouldQueue'
        );

        $report = ReconciliationReport::factory()->create();
        $mailable = new ReconciliationAlert($report);

        $this->assertTrue($mailable->afterCommit, 'ReconciliationAlert should have afterCommit = true');
    }
}
