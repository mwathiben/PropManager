<?php

declare(strict_types=1);

namespace Tests\Feature\Caretaker;

use App\Models\Ticket;
use App\Models\User;
use App\Services\Maintenance\CaretakerPerformanceService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * Phase-80 CARETAKER-PERF: landlord-side caretaker performance metrics + page.
 */
class Phase80CaretakerPerfTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    private User $landlord;

    private User $caretaker;

    private $building;

    protected function setUp(): void
    {
        parent::setUp();
        $setup = $this->createLandlordWithFullSetup();
        $this->landlord = $setup['landlord'];
        $this->building = $setup['building'];
        $this->caretaker = $this->createCaretakerForLandlord($this->landlord, $this->building);
    }

    private function ticket(array $attrs): Ticket
    {
        return Model::withoutEvents(fn () => Ticket::factory()->create(array_merge([
            'landlord_id' => $this->landlord->id,
            'building_id' => $this->building->id,
            'reporter_id' => $this->landlord->id,
            'assigned_to' => $this->caretaker->id,
        ], $attrs)));
    }

    public function test_service_computes_within_sla_and_overdue(): void
    {
        // Resolved within SLA.
        $this->ticket([
            'status' => 'resolved',
            'created_at' => now()->subDays(2),
            'first_response_at' => now()->subDays(2)->addHours(1),
            'resolved_at' => now()->subDay(),
            'resolution_due_at' => now()->subDay()->addHours(2),
        ]);
        // Open and overdue.
        $this->ticket([
            'status' => 'in_progress',
            'resolution_due_at' => now()->subDay(),
        ]);

        $rows = app(CaretakerPerformanceService::class)->forLandlord($this->landlord->id, 90);
        $row = collect($rows)->firstWhere('caretaker_id', $this->caretaker->id);

        $this->assertNotNull($row);
        $this->assertSame(1, $row['resolved_count']);
        $this->assertSame(100.0, $row['within_sla_pct']);
        $this->assertSame(1, $row['open_overdue']);
        $this->assertNotNull($row['avg_first_response_hours']);
    }

    public function test_route_renders_for_landlord(): void
    {
        $response = $this->actingAs($this->landlord)->get(route('maintenance.caretaker-performance'));
        $response->assertOk();
        $props = $response->viewData('page')['props'];
        $this->assertArrayHasKey('caretakers', $props);
        $this->assertSame(90, $props['window']);
    }

    public function test_caretaker_cannot_access_the_landlord_page(): void
    {
        $this->actingAs($this->caretaker)
            ->get(route('maintenance.caretaker-performance'))
            ->assertForbidden();
    }

    public function test_rollup_command_exits_zero(): void
    {
        $this->artisan('caretaker:performance-rollup')->assertExitCode(0);
    }
}
