<?php

declare(strict_types=1);

namespace Tests\Feature\Onboarding;

use App\Events\MilestoneRecorded;
use App\Models\OnboardingMilestone;
use App\Models\Property;
use App\Models\User;
use App\Services\Onboarding\OnboardingMilestoneRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

class Phase31MilestoneTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    public function test_recorder_is_idempotent_for_same_milestone(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        $recorder = app(OnboardingMilestoneRecorder::class);

        $first = $recorder->record($landlord->id, OnboardingMilestone::FIRST_INVOICE);
        $second = $recorder->record($landlord->id, OnboardingMilestone::FIRST_INVOICE);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, OnboardingMilestone::query()
            ->withoutGlobalScopes()
            ->where('landlord_id', $landlord->id)
            ->where('milestone', OnboardingMilestone::FIRST_INVOICE)
            ->count());
    }

    public function test_recorder_rejects_unknown_milestone(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        $this->expectException(\InvalidArgumentException::class);
        app(OnboardingMilestoneRecorder::class)->record($landlord->id, 'first_yacht');
    }

    public function test_milestone_recorded_event_fires_on_first_write_only(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        Event::fake([MilestoneRecorded::class]);
        $recorder = app(OnboardingMilestoneRecorder::class);

        $recorder->record($landlord->id, OnboardingMilestone::FIRST_PROPERTY);
        $recorder->record($landlord->id, OnboardingMilestone::FIRST_PROPERTY);

        Event::assertDispatched(MilestoneRecorded::class, fn (MilestoneRecorded $e) => $e->milestone->milestone === OnboardingMilestone::FIRST_PROPERTY);
        Event::assertDispatchedTimes(MilestoneRecorded::class, 1);
    }

    public function test_landlord_creation_records_signed_up(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);

        $this->assertDatabaseHas('onboarding_milestones', [
            'landlord_id' => $landlord->id,
            'milestone' => OnboardingMilestone::SIGNED_UP,
        ]);
    }

    public function test_property_creation_records_first_property(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        Property::create([
            'name' => 'Phase 31 Plot',
            'address' => 'Eldoret',
            'type' => 'apartment',
            'landlord_id' => $landlord->id,
        ]);

        $this->assertDatabaseHas('onboarding_milestones', [
            'landlord_id' => $landlord->id,
            'milestone' => OnboardingMilestone::FIRST_PROPERTY,
        ]);
    }

    public function test_full_funnel_records_all_six_milestones(): void
    {
        $setup = $this->createLandlordWithFullSetup();
        $landlord = $setup['landlord'];
        ['lease' => $lease] = $this->createTenantWithActiveLease(
            $landlord,
            $setup['units']->first(),
        );

        $invoice = $this->createInvoiceForLease($lease, 'sent');

        \App\Models\Payment::create([
            'invoice_id' => $invoice->id,
            'landlord_id' => $landlord->id,
            'lease_id' => $lease->id,
            'amount' => 1_000.00,
            'payment_method' => 'mpesa',
            'payment_date' => now()->toDateString(),
            'reference' => 'TTFI-TEST-'.uniqid(),
        ]);

        $hits = OnboardingMilestone::query()
            ->withoutGlobalScopes()
            ->where('landlord_id', $landlord->id)
            ->pluck('milestone')
            ->all();

        foreach (OnboardingMilestone::FUNNEL as $m) {
            $this->assertContains($m, $hits, "Missing funnel milestone: {$m}");
        }
    }

    public function test_activation_audit_runs_successfully_with_data(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        app(OnboardingMilestoneRecorder::class)->record($landlord->id, OnboardingMilestone::FIRST_INVOICE);

        $this->artisan('activation:audit')->assertSuccessful();

        // Recorder confirmed both milestones exist for the landlord.
        $this->assertDatabaseHas('onboarding_milestones', [
            'landlord_id' => $landlord->id,
            'milestone' => OnboardingMilestone::SIGNED_UP,
        ]);
        $this->assertDatabaseHas('onboarding_milestones', [
            'landlord_id' => $landlord->id,
            'milestone' => OnboardingMilestone::FIRST_INVOICE,
        ]);
    }
}
