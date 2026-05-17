<?php

declare(strict_types=1);

namespace Tests\Feature\Reports;

use App\Models\SavedReport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase-50 REAL-TIME-PREVIEW-3: the /reports/scheduled/preview route
 * must run an ad-hoc report for the owning landlord AND must reject
 * cross-tenant saved_report_id with 403 — the saved report row leaks
 * landlord-scoped config + filter values that another landlord can't
 * be allowed to see.
 */
class Phase50PreviewOwnershipTest extends TestCase
{
    use RefreshDatabase;

    public function test_landlord_can_preview_own_saved_report(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        $report = SavedReport::create([
            'landlord_id' => $landlord->id,
            'name' => 'My collections',
            'config' => [
                'table' => 'payments',
                'fields' => ['payment.amount'],
                'limit' => 10,
            ],
        ]);

        $this->actingAs($landlord)
            ->postJson(route('reports.scheduled.preview'), ['saved_report_id' => $report->id])
            ->assertOk()
            ->assertJsonStructure(['report_id', 'report_name', 'rows', 'previewed_at'])
            ->assertJsonPath('report_id', $report->id);
    }

    public function test_cross_tenant_saved_report_id_rejected(): void
    {
        $landlordA = User::factory()->create(['role' => 'landlord']);
        $landlordB = User::factory()->create(['role' => 'landlord']);
        $reportA = SavedReport::create([
            'landlord_id' => $landlordA->id,
            'name' => 'A private report',
            'config' => [
                'table' => 'payments',
                'fields' => ['payment.amount'],
                'limit' => 10,
            ],
        ]);

        $this->actingAs($landlordB)
            ->postJson(route('reports.scheduled.preview'), ['saved_report_id' => $reportA->id])
            ->assertForbidden();
    }

    public function test_unknown_saved_report_id_rejected(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);

        $this->actingAs($landlord)
            ->postJson(route('reports.scheduled.preview'), ['saved_report_id' => 999999])
            ->assertForbidden();
    }

    public function test_non_landlord_user_blocked_by_middleware(): void
    {
        $tenant = User::factory()->create(['role' => 'tenant']);

        $this->actingAs($tenant)
            ->postJson(route('reports.scheduled.preview'), ['saved_report_id' => 1])
            ->assertForbidden();
    }

    public function test_missing_saved_report_id_validation(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);

        $this->actingAs($landlord)
            ->postJson(route('reports.scheduled.preview'), [])
            ->assertStatus(422);
    }
}
