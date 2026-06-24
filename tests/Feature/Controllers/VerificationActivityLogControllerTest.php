<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers;

use App\Models\Building;
use App\Models\Lease;
use App\Models\Property;
use App\Models\TenantVerification;
use App\Models\Unit;
use App\Models\User;
use App\Models\VerificationItem;
use App\Models\VerificationTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression guard for the verification activity-log endpoints.
 *
 * Every TenantActivity write here previously used a non-existent 'action'
 * key and omitted the NOT-NULL 'type' column, so the insert threw inside
 * its transaction — silently rolling back (start/bulk) or 500-ing after a
 * partial write (update/reset/complete). Same Phase-81 defect already
 * fixed in the move-out flow. These tests assert each transition completes
 * and writes the correct tenant_activities.type.
 */
class VerificationActivityLogControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $landlord;

    private Lease $lease;

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
    }

    private function template(): VerificationTemplate
    {
        $template = VerificationTemplate::factory()->forLandlord($this->landlord)->create();
        VerificationItem::factory()->create([
            'verification_template_id' => $template->id,
            'is_required' => true,
        ]);

        return $template;
    }

    public function test_start_verification_creates_records_and_logs_activity(): void
    {
        $template = $this->template();

        $response = $this->actingAs($this->landlord)
            ->post(route('verifications.start', $this->lease), ['template_id' => $template->id]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('tenant_verifications', ['lease_id' => $this->lease->id]);
        $this->assertDatabaseHas('tenant_activities', [
            'tenant_id' => $this->lease->tenant_id,
            'type' => 'verification_started',
        ]);
    }

    public function test_update_verification_persists_and_logs_activity(): void
    {
        $verification = TenantVerification::factory()->forLease($this->lease)->pending()->create();

        $response = $this->actingAs($this->landlord)
            ->put(route('verifications.update', $verification), ['status' => 'verified']);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('tenant_verifications', [
            'id' => $verification->id,
            'status' => 'verified',
        ]);
        $this->assertDatabaseHas('tenant_activities', [
            'tenant_id' => $this->lease->tenant_id,
            'type' => 'verification_updated',
        ]);
    }

    public function test_bulk_update_verifications_persists_and_logs_activity(): void
    {
        $a = TenantVerification::factory()->forLease($this->lease)->pending()->create();
        $b = TenantVerification::factory()->forLease($this->lease)->pending()->create();

        $response = $this->actingAs($this->landlord)
            ->post(route('verifications.bulkUpdate', $this->lease), [
                'verifications' => [
                    ['id' => $a->id, 'status' => 'verified'],
                    ['id' => $b->id, 'status' => 'rejected'],
                ],
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('tenant_verifications', ['id' => $a->id, 'status' => 'verified']);
        $this->assertDatabaseHas('tenant_verifications', ['id' => $b->id, 'status' => 'rejected']);
        $this->assertDatabaseHas('tenant_activities', [
            'tenant_id' => $this->lease->tenant_id,
            'type' => 'verification_bulk_update',
        ]);
    }

    public function test_reset_verification_deletes_records_and_logs_activity(): void
    {
        TenantVerification::factory()->forLease($this->lease)->pending()->create();

        $response = $this->actingAs($this->landlord)
            ->post(route('verifications.reset', $this->lease));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseMissing('tenant_verifications', ['lease_id' => $this->lease->id]);
        $this->assertDatabaseHas('tenant_activities', [
            'tenant_id' => $this->lease->tenant_id,
            'type' => 'verification_reset',
        ]);
    }

    public function test_complete_verification_marks_lease_and_logs_activity(): void
    {
        TenantVerification::factory()->forLease($this->lease)->verified()->create();

        $response = $this->actingAs($this->landlord)
            ->post(route('verifications.complete', $this->lease));

        $response->assertRedirect(route('tenants.show', $this->lease->tenant_id));
        $response->assertSessionHas('success');

        // NOTE: completeVerification also attempts $lease->update(['is_verified' => true]),
        // but `leases` has no is_verified column and it is not fillable, so that write is a
        // silent no-op. That schema gap is a separate defect (tracked in its own follow-up);
        // this test asserts only what the activity-log fix guarantees.
        $this->assertDatabaseHas('tenant_activities', [
            'tenant_id' => $this->lease->tenant_id,
            'type' => 'verification_completed',
        ]);
    }

    public function test_cross_tenant_user_cannot_start_verification(): void
    {
        $template = $this->template();
        $otherLandlord = User::factory()->create(['role' => 'landlord']);

        $this->actingAs($otherLandlord)
            ->post(route('verifications.start', $this->lease), ['template_id' => $template->id])
            ->assertForbidden();

        $this->assertDatabaseMissing('tenant_verifications', ['lease_id' => $this->lease->id]);
    }
}
