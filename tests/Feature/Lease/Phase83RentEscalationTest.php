<?php

declare(strict_types=1);

namespace Tests\Feature\Lease;

use App\Models\Lease;
use App\Models\RentEscalation;
use App\Models\User;
use App\Services\Lease\LeaseRenewalAutoService;
use App\Services\Lease\RentEscalationService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * Phase-83 RENT-ESCALATION: scheduling, applying (cron), auto-renew folding.
 */
class Phase83RentEscalationTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    private User $landlord;

    private Lease $lease;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $setup = $this->createLandlordWithFullSetup();
        $this->landlord = $setup['landlord'];
        $this->lease = Model::withoutEvents(
            fn () => $this->createTenantWithActiveLease($this->landlord, $setup['units']->get(0))['lease'],
        );
        $this->lease->update(['rent_amount' => 10000]);
    }

    private function escalation(array $attrs = []): RentEscalation
    {
        return Model::withoutEvents(fn () => RentEscalation::factory()->create(array_merge([
            'lease_id' => $this->lease->id,
            'landlord_id' => $this->landlord->id,
        ], $attrs)));
    }

    public function test_preview_computes_percentage_and_fixed(): void
    {
        $pct = $this->escalation(['escalation_type' => 'percentage', 'amount' => 10]);
        $fixed = $this->escalation(['escalation_type' => 'fixed_amount', 'amount' => 2500]);

        $service = app(RentEscalationService::class);
        $this->assertSame(11000.0, $service->preview($pct->fresh()));
        $this->assertSame(12500.0, $service->preview($fixed->fresh()));
    }

    public function test_apply_due_updates_rent_writes_history_and_is_idempotent(): void
    {
        $esc = $this->escalation([
            'escalation_type' => 'percentage',
            'amount' => 10,
            'effective_date' => now()->subDay()->toDateString(),
        ]);

        $applied = app(RentEscalationService::class)->applyAllDue();
        $this->assertSame(1, $applied);

        $esc->refresh();
        $this->assertSame(RentEscalation::STATUS_APPLIED, $esc->status);
        $this->assertSame('11000.00', (string) $this->lease->fresh()->rent_amount);
        $this->assertNotNull($esc->rent_history_id);
        $this->assertDatabaseHas('rent_histories', [
            'lease_id' => $this->lease->id,
            'old_amount' => 10000,
            'new_amount' => 11000,
        ]);

        // Second run is a no-op (status guard).
        $this->assertSame(0, app(RentEscalationService::class)->applyAllDue());
        $this->assertSame('11000.00', (string) $this->lease->fresh()->rent_amount);
    }

    public function test_future_dated_escalation_is_not_applied(): void
    {
        $this->escalation(['effective_date' => now()->addMonth()->toDateString()]);

        $this->assertSame(0, app(RentEscalationService::class)->applyAllDue());
        $this->assertSame('10000.00', (string) $this->lease->fresh()->rent_amount);
    }

    public function test_store_and_cancel_are_owner_gated(): void
    {
        $this->actingAs($this->landlord)
            ->post(route('rent-escalations.store', $this->lease->id), [
                'escalation_type' => 'percentage',
                'amount' => 7.5,
                'effective_date' => now()->addMonths(2)->toDateString(),
            ])
            ->assertRedirect();
        $this->assertDatabaseHas('rent_escalations', [
            'lease_id' => $this->lease->id,
            'amount' => 7.5,
            'status' => RentEscalation::STATUS_SCHEDULED,
        ]);

        // A different landlord is denied — either 404 (TenantScope hides the
        // resource from route-model binding) or 403 (the controller's
        // abort_unless defence-in-depth), depending on scope-boot order. Either
        // way it must not mutate.
        $other = Model::withoutEvents(fn () => User::factory()->create(['role' => 'landlord']));
        $storeResp = $this->actingAs($other)
            ->post(route('rent-escalations.store', $this->lease->id), [
                'escalation_type' => 'percentage',
                'amount' => 5,
                'effective_date' => now()->addMonths(2)->toDateString(),
            ]);
        $this->assertContains($storeResp->status(), [403, 404]);
        $this->assertDatabaseMissing('rent_escalations', ['lease_id' => $this->lease->id, 'amount' => 5]);

        $esc = $this->escalation();
        $destroyResp = $this->actingAs($other)->delete(route('rent-escalations.destroy', $esc->id));
        $this->assertContains($destroyResp->status(), [403, 404]);
        $this->assertSame(RentEscalation::STATUS_SCHEDULED, $esc->fresh()->status);

        $this->actingAs($this->landlord)
            ->delete(route('rent-escalations.destroy', $esc->id))
            ->assertRedirect();
        $this->assertSame(RentEscalation::STATUS_CANCELLED, $esc->fresh()->status);
    }

    public function test_auto_renew_folds_due_escalation_into_renewed_rent(): void
    {
        $this->lease->update([
            'start_date' => now()->subYear()->toDateString(),
            'end_date' => now()->addDays(10)->toDateString(),
            'auto_renew' => true,
        ]);
        // Effective on/before the new term start (old end + 1 day).
        $this->escalation([
            'escalation_type' => 'percentage',
            'amount' => 10,
            'effective_date' => now()->addDays(11)->toDateString(),
        ]);

        $created = app(LeaseRenewalAutoService::class)->scanExpiring(30);

        $this->assertCount(1, $created);
        $this->assertSame('11000.00', (string) $created[0]->fresh()->rent_amount);
        $this->assertDatabaseHas('rent_histories', [
            'lease_id' => $created[0]->id,
            'new_amount' => 11000,
        ]);
    }
}
