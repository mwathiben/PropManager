<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers;

use App\Models\ReconciliationReport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class ReconciliationReportDisplayTest extends TestCase
{
    use RefreshDatabase;

    public function test_reconciliation_tab_includes_paystack_report_when_exists(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);

        ReconciliationReport::factory()->create([
            'landlord_id' => $landlord->id,
            'provider' => 'paystack',
            'status' => 'completed',
            'discrepancy_count' => 2,
            'local_count' => 10,
            'remote_count' => 12,
            'matched_count' => 10,
        ]);

        $response = $this->actingAs($landlord)
            ->get(route('finances.reconciliation'));

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->has('paystackReport')
            ->where('paystackReport.provider', 'paystack')
            ->where('paystackReport.status', 'completed')
            ->where('paystackReport.discrepancy_count', 2)
        );
    }

    public function test_reconciliation_tab_handles_no_reports_gracefully(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);

        $response = $this->actingAs($landlord)
            ->get(route('finances.reconciliation'));

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->where('paystackReport', null)
        );
    }
}
