<?php

declare(strict_types=1);

namespace Tests\Feature\LegalHold;

use App\Events\LeaseTerminationInitiated;
use App\Listeners\HoldOnLeaseTermination;
use App\Models\LandlordHoldSettings;
use App\Models\Lease;
use App\Models\LeaseTermination;
use App\Models\LegalHold;
use App\Models\LegalMatter;
use App\Models\MessageThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * Phase-72 HOLD-SETTINGS (auto-hold rule): the LeaseTerminationInitiated
 * listener auto-preserves a tenant's records only when the landlord opted in,
 * idempotently per termination.
 */
class Phase72AutoHoldTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    private User $landlord;

    private User $tenant;

    private Lease $lease;

    protected function setUp(): void
    {
        parent::setUp();

        $setup = $this->createLandlordWithFullSetup();
        $this->landlord = $setup['landlord'];
        ['tenant' => $this->tenant, 'lease' => $this->lease] = $this->createTenantWithActiveLease(
            $this->landlord,
            $setup['units']->first(),
        );

        // Give the tenant a holdable record.
        $thread = MessageThread::create(['landlord_id' => $this->landlord->id]);
        $thread->participants()->attach($this->tenant->id, ['role' => MessageThread::ROLE_TENANT]);
    }

    private function termination(): LeaseTermination
    {
        return LeaseTermination::create([
            'lease_id' => $this->lease->id,
            'landlord_id' => $this->landlord->id,
            'initiated_by' => $this->landlord->id,
            'termination_reason' => 'other',
            'termination_date' => now()->addDays(30)->toDateString(),
            'notice_given_at' => now(),
            'status' => LeaseTermination::STATUS_PENDING,
        ]);
    }

    private function fire(LeaseTermination $termination): void
    {
        app(HoldOnLeaseTermination::class)->handle(new LeaseTerminationInitiated($termination));
    }

    public function test_auto_hold_off_is_a_noop(): void
    {
        $this->fire($this->termination());

        $this->assertSame(0, LegalMatter::withoutGlobalScopes()->count());
        $this->assertSame(0, LegalHold::count());
    }

    public function test_auto_hold_on_creates_a_matter_and_holds(): void
    {
        LandlordHoldSettings::create(['landlord_id' => $this->landlord->id, 'auto_hold_on_eviction' => true]);
        $termination = $this->termination();

        $this->fire($termination);

        $matter = LegalMatter::withoutGlobalScopes()->where('matter_reference', 'AUTO-TERM-'.$termination->id)->first();
        $this->assertNotNull($matter);
        $this->assertSame('tenant_dispute', $matter->situation_type);
        $this->assertGreaterThan(0, LegalHold::where('legal_matter_id', $matter->id)->count());
    }

    public function test_auto_hold_is_idempotent_per_termination(): void
    {
        LandlordHoldSettings::create(['landlord_id' => $this->landlord->id, 'auto_hold_on_eviction' => true]);
        $termination = $this->termination();

        $this->fire($termination);
        $this->fire($termination);

        $this->assertSame(1, LegalMatter::withoutGlobalScopes()->where('matter_reference', 'AUTO-TERM-'.$termination->id)->count());
    }
}
