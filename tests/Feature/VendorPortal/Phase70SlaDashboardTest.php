<?php

declare(strict_types=1);

namespace Tests\Feature\VendorPortal;

use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\User;
use App\Models\Vendor;
use App\Services\Vendors\VendorSlaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase-70 SLA-DASHBOARD: a vendor's own resolution-within-SLA %, breach
 * and open-overdue counts, computed from their tickets, vendor-scoped.
 */
class Phase70SlaDashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $landlord;

    protected function setUp(): void
    {
        parent::setUp();
        $this->landlord = User::factory()->create(['role' => 'landlord']);
    }

    private function vendor(): Vendor
    {
        return Vendor::create([
            'landlord_id' => $this->landlord->id,
            'name' => 'Acme',
            'email' => 'acme@c.test',
            'is_active' => true,
        ]);
    }

    private function resolved(Vendor $vendor, bool $withinSla): Ticket
    {
        $due = now()->subDays(5);

        return Ticket::factory()->create([
            'landlord_id' => $vendor->landlord_id,
            'reporter_id' => $vendor->landlord_id,
            'vendor_id' => $vendor->id,
            'status' => TicketStatus::Resolved,
            'created_at' => now()->subDays(10),
            'resolution_due_at' => $due,
            'resolved_at' => $withinSla ? $due->copy()->subHours(2) : $due->copy()->addHours(2),
        ]);
    }

    private function openOverdue(Vendor $vendor): Ticket
    {
        return Ticket::factory()->create([
            'landlord_id' => $vendor->landlord_id,
            'reporter_id' => $vendor->landlord_id,
            'vendor_id' => $vendor->id,
            'status' => TicketStatus::InProgress,
            'resolution_due_at' => now()->subDays(2),
            'resolved_at' => null,
        ]);
    }

    public function test_sla_math_is_correct_and_vendor_scoped(): void
    {
        $vendor = $this->vendor();
        $this->resolved($vendor, withinSla: true);
        $this->resolved($vendor, withinSla: true);
        $this->resolved($vendor, withinSla: false);
        $this->openOverdue($vendor);

        // Another vendor's data must not bleed in.
        $other = $this->vendor();
        $this->resolved($other, withinSla: false);
        $this->openOverdue($other);

        $m = app(VendorSlaService::class)->forVendor($vendor, 90);

        $this->assertSame(3, $m['total_resolved']);
        $this->assertSame(3, $m['with_due']);
        $this->assertSame(2, $m['within_sla']);
        $this->assertSame(1, $m['breached']);
        $this->assertEqualsWithDelta(66.7, $m['within_sla_pct'], 0.1);
        $this->assertSame(1, $m['open_overdue']);
    }

    public function test_window_excludes_old_resolutions(): void
    {
        $vendor = $this->vendor();
        // Resolved 200 days ago — outside a 90-day window.
        Ticket::factory()->create([
            'landlord_id' => $vendor->landlord_id,
            'reporter_id' => $vendor->landlord_id,
            'vendor_id' => $vendor->id,
            'status' => TicketStatus::Resolved,
            'created_at' => now()->subDays(210),
            'resolution_due_at' => now()->subDays(201),
            'resolved_at' => now()->subDays(200),
        ]);

        $m = app(VendorSlaService::class)->forVendor($vendor, 90);
        $this->assertSame(0, $m['total_resolved']);
    }

    public function test_endpoint_is_session_scoped(): void
    {
        $vendor = $this->vendor();
        $this->resolved($vendor, withinSla: true);

        $this->withSession(['vendor_portal_id' => $vendor->id])
            ->get('/v/portal/sla')
            ->assertOk()
            ->assertInertia(fn ($p) => $p->component('VendorPortal/Sla')->where('metrics.total_resolved', 1));

        $this->flushSession();
        $this->get('/v/portal/sla')->assertForbidden();
    }

    public function test_no_resolved_tickets_yields_null_pct(): void
    {
        $vendor = $this->vendor();

        $m = app(VendorSlaService::class)->forVendor($vendor, 90);
        $this->assertSame(0, $m['total_resolved']);
        $this->assertNull($m['within_sla_pct']);
        $this->assertNull($m['avg_resolution_hours']);
    }
}
