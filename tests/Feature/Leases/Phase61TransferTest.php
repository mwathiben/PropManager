<?php

declare(strict_types=1);

namespace Tests\Feature\Leases;

use App\Events\LeaseTransferRequested;
use App\Exceptions\ShortNoticeException;
use App\Models\Lease;
use App\Models\LeaseTransfer;
use App\Models\Unit;
use App\Models\User;
use App\Services\Lease\LeaseTransferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * Phase-61 TRANSFER-1/2/3: lease assignment / sublet workflow.
 */
class Phase61TransferTest extends TestCase
{
    use RefreshDatabase;

    private function makeLease(): Lease
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        $tenant = User::factory()->create(['role' => 'tenant', 'landlord_id' => $landlord->id]);
        $unit = Unit::factory()->create();

        return Lease::factory()->create([
            'unit_id' => $unit->id,
            'tenant_id' => $tenant->id,
            'landlord_id' => $landlord->id,
            'is_active' => true,
        ]);
    }

    public function test_service_request_creates_row_with_requested_status(): void
    {
        Event::fake();
        $lease = $this->makeLease();
        $incoming = User::factory()->create(['role' => 'tenant', 'landlord_id' => $lease->landlord_id]);

        $transfer = app(LeaseTransferService::class)->request(
            $lease,
            $lease->tenant,
            $incoming,
            ['transfer_date' => now()->addDays(30)->toDateString()],
        );

        $this->assertSame(LeaseTransfer::STATUS_REQUESTED, $transfer->status);
        $this->assertSame($lease->tenant_id, $transfer->outgoing_tenant_id);
        $this->assertSame($incoming->id, $transfer->incoming_tenant_id);
        Event::assertDispatched(LeaseTransferRequested::class);
    }

    public function test_service_request_rejects_short_notice(): void
    {
        $lease = $this->makeLease();
        $incoming = User::factory()->create(['role' => 'tenant', 'landlord_id' => $lease->landlord_id]);

        $this->expectException(ShortNoticeException::class);
        app(LeaseTransferService::class)->request(
            $lease,
            $lease->tenant,
            $incoming,
            ['transfer_date' => now()->addDays(5)->toDateString()],
        );
    }

    public function test_approve_sets_landlord_approved_at(): void
    {
        $lease = $this->makeLease();
        $transfer = LeaseTransfer::create([
            'lease_id' => $lease->id,
            'landlord_id' => $lease->landlord_id,
            'outgoing_tenant_id' => $lease->tenant_id,
            'incoming_tenant_id' => User::factory()->create()->id,
            'initiated_by' => $lease->tenant_id,
            'transfer_date' => now()->addDays(30)->toDateString(),
            'status' => LeaseTransfer::STATUS_REQUESTED,
        ]);

        app(LeaseTransferService::class)->approve($transfer);

        $this->assertSame(LeaseTransfer::STATUS_LANDLORD_APPROVED, $transfer->fresh()->status);
        $this->assertNotNull($transfer->fresh()->landlord_approved_at);
    }

    public function test_complete_swaps_lease_tenant_id(): void
    {
        $lease = $this->makeLease();
        $incoming = User::factory()->create(['role' => 'tenant', 'landlord_id' => $lease->landlord_id]);
        $outgoing = $lease->tenant;

        $transfer = LeaseTransfer::create([
            'lease_id' => $lease->id,
            'landlord_id' => $lease->landlord_id,
            'outgoing_tenant_id' => $outgoing->id,
            'incoming_tenant_id' => $incoming->id,
            'initiated_by' => $outgoing->id,
            'transfer_date' => now()->addDays(30)->toDateString(),
            'status' => LeaseTransfer::STATUS_LANDLORD_APPROVED,
            'landlord_approved_at' => now(),
        ]);

        app(LeaseTransferService::class)->complete($transfer);

        $this->assertSame(LeaseTransfer::STATUS_COMPLETED, $transfer->fresh()->status);
        $this->assertSame($incoming->id, $lease->fresh()->tenant_id);
    }

    public function test_route_only_outgoing_tenant_can_request(): void
    {
        $lease = $this->makeLease();
        $other = User::factory()->create(['role' => 'tenant', 'landlord_id' => $lease->landlord_id]);
        $incoming = User::factory()->create(['role' => 'tenant', 'email' => 'new@example.test']);

        $response = $this->actingAs($other)
            ->from(route('leases.show', $lease))
            ->post(route('leases.transfer', $lease), [
                'incoming_tenant_email' => $incoming->email,
                'transfer_date' => now()->addDays(30)->toDateString(),
            ]);

        $response->assertForbidden();
    }

    public function test_route_persists_transfer_on_success(): void
    {
        $lease = $this->makeLease();
        $incoming = User::factory()->create(['role' => 'tenant', 'email' => 'new@example.test']);

        $response = $this->actingAs($lease->tenant)
            ->from(route('leases.show', $lease))
            ->post(route('leases.transfer', $lease), [
                'incoming_tenant_email' => $incoming->email,
                'transfer_date' => now()->addDays(30)->toDateString(),
                'transfer_fee_amount' => '500.00',
                'reason_text' => 'relocating',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('lease_transfers', [
            'lease_id' => $lease->id,
            'incoming_tenant_id' => $incoming->id,
            'status' => LeaseTransfer::STATUS_REQUESTED,
        ]);
    }
}
