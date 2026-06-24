<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers;

use App\Enums\MoveOutStatus;
use App\Models\Building;
use App\Models\Lease;
use App\Models\MoveOut;
use App\Models\Property;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers the inspection-completion and cancellation lifecycle endpoints.
 *
 * Regression guard: completeInspection() and cancel() previously wrote the
 * TenantActivity with an 'action' key (not a column / not fillable) and omitted
 * the NOT-NULL 'type' column, which threw inside the transaction and silently
 * rolled back the whole state change — the same Phase-81 defect already fixed
 * in complete(). These tests lock the transitions in.
 */
class MoveOutLifecycleControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $landlord;

    private Lease $lease;

    private MoveOut $moveOut;

    protected function setUp(): void
    {
        parent::setUp();

        $this->landlord = User::factory()->create(['role' => 'landlord']);

        $property = Property::factory()->create(['landlord_id' => $this->landlord->id]);
        $building = Building::factory()->create([
            'property_id' => $property->id,
            'landlord_id' => $this->landlord->id,
        ]);
        $unit = Unit::factory()->create([
            'building_id' => $building->id,
            'landlord_id' => $this->landlord->id,
        ]);
        $tenant = User::factory()->create([
            'role' => 'tenant',
            'landlord_id' => $this->landlord->id,
        ]);

        $this->lease = Lease::factory()->create([
            'unit_id' => $unit->id,
            'tenant_id' => $tenant->id,
            'landlord_id' => $this->landlord->id,
        ]);

        $this->moveOut = MoveOut::factory()
            ->forLease($this->lease)
            ->inspectionPending()
            ->create();
    }

    public function test_complete_inspection_moves_to_settlement_pending_and_logs_activity(): void
    {
        $response = $this->actingAs($this->landlord)
            ->post(route('move-outs.complete-inspection', $this->moveOut), [
                'inspection_notes' => 'Walls scuffed, carpet stained.',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertSame(MoveOutStatus::SettlementPending, $this->moveOut->fresh()->status);
        $this->assertDatabaseHas('move_outs', [
            'id' => $this->moveOut->id,
            'inspection_notes' => 'Walls scuffed, carpet stained.',
        ]);
        $this->assertDatabaseHas('tenant_activities', [
            'tenant_id' => $this->lease->tenant_id,
            'type' => 'move_out_inspection_complete',
        ]);
    }

    public function test_cross_tenant_user_cannot_complete_inspection(): void
    {
        $otherLandlord = User::factory()->create(['role' => 'landlord']);

        $this->actingAs($otherLandlord)
            ->post(route('move-outs.complete-inspection', $this->moveOut), [
                'inspection_notes' => 'Should be blocked.',
            ])
            ->assertForbidden();

        $this->assertSame(MoveOutStatus::InspectionPending, $this->moveOut->fresh()->status);
    }

    public function test_cancel_marks_move_out_cancelled_and_logs_activity(): void
    {
        $response = $this->actingAs($this->landlord)
            ->post(route('move-outs.cancel', $this->moveOut), [
                'cancellation_reason' => 'Tenant changed their mind.',
            ]);

        $response->assertRedirect(route('tenants.show', $this->lease->tenant_id));
        $response->assertSessionHas('success');

        $this->assertSame(MoveOutStatus::Cancelled, $this->moveOut->fresh()->status);
        $this->assertDatabaseHas('tenant_activities', [
            'tenant_id' => $this->lease->tenant_id,
            'type' => 'move_out_cancelled',
        ]);
    }

    public function test_cancel_is_rejected_for_completed_move_out(): void
    {
        $this->moveOut->update(['status' => MoveOutStatus::Completed]);

        $this->actingAs($this->landlord)
            ->post(route('move-outs.cancel', $this->moveOut), [
                'cancellation_reason' => 'Too late.',
            ])
            ->assertSessionHasErrors('move_out');

        $this->assertSame(MoveOutStatus::Completed, $this->moveOut->fresh()->status);
    }

    public function test_cross_tenant_user_cannot_cancel(): void
    {
        $otherLandlord = User::factory()->create(['role' => 'landlord']);

        $this->actingAs($otherLandlord)
            ->post(route('move-outs.cancel', $this->moveOut), [
                'cancellation_reason' => 'Should be blocked.',
            ])
            ->assertForbidden();

        $this->assertNotSame(MoveOutStatus::Cancelled, $this->moveOut->fresh()->status);
    }
}
