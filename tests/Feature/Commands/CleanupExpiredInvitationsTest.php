<?php

namespace Tests\Feature\Commands;

use App\Models\KycRequirement;
use App\Models\Lease;
use App\Models\TenantInvitation;
use App\Models\TenantPaymentVerification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

class CleanupExpiredInvitationsTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    // --- Phase 1: Pending Invitation Cleanup ---

    public function test_marks_pending_invitation_as_expired_after_30_days(): void
    {
        $invitation = TenantInvitation::factory()->create([
            'status' => 'pending',
            'expires_at' => now()->subDays(31),
        ]);

        $this->artisan('tenant-invitations:cleanup')
            ->assertSuccessful();

        $this->assertDatabaseHas('tenant_invitations', [
            'id' => $invitation->id,
            'status' => 'expired',
        ]);
    }

    public function test_does_not_expire_pending_invitation_less_than_30_days(): void
    {
        $invitation = TenantInvitation::factory()->create([
            'status' => 'pending',
            'expires_at' => now()->subDays(29),
        ]);

        $this->artisan('tenant-invitations:cleanup')
            ->assertSuccessful();

        $this->assertDatabaseHas('tenant_invitations', [
            'id' => $invitation->id,
            'status' => 'pending',
        ]);
    }

    public function test_does_not_modify_already_accepted_invitations_in_phase_1(): void
    {
        $invitation = TenantInvitation::factory()->accepted()->create([
            'expires_at' => now()->subDays(60),
        ]);

        $this->artisan('tenant-invitations:cleanup')
            ->assertSuccessful();

        $this->assertDatabaseHas('tenant_invitations', [
            'id' => $invitation->id,
            'status' => 'accepted',
        ]);
    }

    public function test_does_not_modify_declined_invitations(): void
    {
        $invitation = TenantInvitation::factory()->declined()->create();

        $this->artisan('tenant-invitations:cleanup')
            ->assertSuccessful();

        $this->assertDatabaseHas('tenant_invitations', [
            'id' => $invitation->id,
            'status' => 'declined',
        ]);
    }

    // --- Phase 2: Incomplete User Archival ---

    public function test_archives_user_with_accepted_invite_no_kyc_no_payment(): void
    {
        // Create a global KYC requirement so hasCompletedKyc() returns false for incomplete users
        KycRequirement::create([
            'landlord_id' => null,
            'building_id' => null,
            'requirement_type' => 'selfie',
            'label' => 'Profile Photo',
            'description' => 'A clear photo of your face',
            'is_required' => true,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $setup = $this->createLandlordWithFullSetup();
        $unit = $setup['units']->first();

        $user = User::factory()->create([
            'role' => 'tenant',
            'landlord_id' => $setup['landlord']->id,
            'is_archived' => false,
        ]);

        TenantInvitation::factory()->create([
            'landlord_id' => $setup['landlord']->id,
            'unit_id' => $unit->id,
            'status' => 'accepted',
            'accepted_at' => now()->subDays(45),
            'existing_user_id' => $user->id,
        ]);

        // No KYC submissions, no lease, no payment verification

        $this->artisan('tenant-invitations:cleanup')
            ->assertSuccessful();

        $user->refresh();
        $this->assertTrue($user->is_archived);
        $this->assertNotNull($user->archived_at);
    }

    public function test_does_not_archive_user_with_completed_kyc(): void
    {
        $setup = $this->createLandlordWithFullSetup();
        $unit = $setup['units']->first();

        $user = User::factory()->create([
            'role' => 'tenant',
            'landlord_id' => $setup['landlord']->id,
            'kyc_completed_at' => now(),
        ]);

        TenantInvitation::factory()->create([
            'landlord_id' => $setup['landlord']->id,
            'unit_id' => $unit->id,
            'status' => 'accepted',
            'accepted_at' => now()->subDays(45),
            'existing_user_id' => $user->id,
        ]);

        $this->artisan('tenant-invitations:cleanup')
            ->assertSuccessful();

        $user->refresh();
        $this->assertFalse($user->is_archived);
    }

    public function test_does_not_archive_user_with_verified_payment(): void
    {
        $setup = $this->createLandlordWithFullSetup();
        $unit = $setup['units']->first();

        $user = User::factory()->create([
            'role' => 'tenant',
            'landlord_id' => $setup['landlord']->id,
        ]);

        $lease = Lease::create([
            'unit_id' => $unit->id,
            'tenant_id' => $user->id,
            'landlord_id' => $setup['landlord']->id,
            'rent_amount' => 25000,
            'deposit_amount' => 25000,
            'start_date' => now(),
            'is_active' => false, // Inactive lease
        ]);

        TenantPaymentVerification::create([
            'lease_id' => $lease->id,
            'landlord_id' => $setup['landlord']->id,
            'status' => TenantPaymentVerification::STATUS_PAYMENT_VERIFIED,
            'total_required' => 50000,
            'amount_paid' => 50000,
        ]);

        TenantInvitation::factory()->create([
            'landlord_id' => $setup['landlord']->id,
            'unit_id' => $unit->id,
            'status' => 'accepted',
            'accepted_at' => now()->subDays(45),
            'existing_user_id' => $user->id,
        ]);

        $this->artisan('tenant-invitations:cleanup')
            ->assertSuccessful();

        $user->refresh();
        $this->assertFalse($user->is_archived);
    }

    public function test_does_not_archive_user_with_active_lease(): void
    {
        $setup = $this->createLandlordWithFullSetup();
        $tenantData = $this->createTenantWithActiveLease($setup['landlord'], $setup['units']->first());
        $user = $tenantData['tenant'];

        TenantInvitation::factory()->create([
            'landlord_id' => $setup['landlord']->id,
            'unit_id' => $setup['units']->first()->id,
            'status' => 'accepted',
            'accepted_at' => now()->subDays(45),
            'existing_user_id' => $user->id,
        ]);

        $this->artisan('tenant-invitations:cleanup')
            ->assertSuccessful();

        $user->refresh();
        $this->assertFalse($user->is_archived);
    }

    // --- Logging & Edge Cases ---

    public function test_logs_expired_invitations(): void
    {
        Log::shouldReceive('info')
            ->once()
            ->withArgs(fn ($msg, $ctx) => str_contains($msg, 'Tenant invitation marked as expired') &&
                isset($ctx['invitation_id'])
            );

        TenantInvitation::factory()->create([
            'status' => 'pending',
            'expires_at' => now()->subDays(31),
        ]);

        $this->artisan('tenant-invitations:cleanup')
            ->assertSuccessful();
    }

    public function test_handles_empty_results_gracefully(): void
    {
        // No invitations in database

        $this->artisan('tenant-invitations:cleanup')
            ->assertSuccessful()
            ->expectsOutput('Expired 0 pending invitation(s).')
            ->expectsOutput('Archived 0 incomplete user(s).');
    }

    public function test_command_returns_success_status(): void
    {
        $this->artisan('tenant-invitations:cleanup')
            ->assertExitCode(0);
    }

    // --- Edge Cases: Multiple Invitations & PII ---

    public function test_handles_multiple_invitations_for_same_user(): void
    {
        // Create a global KYC requirement so hasCompletedKyc() returns false
        KycRequirement::create([
            'landlord_id' => null,
            'building_id' => null,
            'requirement_type' => 'selfie',
            'label' => 'Profile Photo',
            'description' => 'A clear photo of your face',
            'is_required' => true,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $setup = $this->createLandlordWithFullSetup();
        $unit1 = $setup['units']->first();
        $unit2 = $setup['units']->skip(1)->first();

        $user = User::factory()->create([
            'role' => 'tenant',
            'landlord_id' => $setup['landlord']->id,
            'is_archived' => false,
        ]);

        // Two invitations for same user (different units)
        $invitation1 = TenantInvitation::factory()->create([
            'landlord_id' => $setup['landlord']->id,
            'unit_id' => $unit1->id,
            'status' => 'accepted',
            'accepted_at' => now()->subDays(45),
            'existing_user_id' => $user->id,
        ]);

        $invitation2 = TenantInvitation::factory()->create([
            'landlord_id' => $setup['landlord']->id,
            'unit_id' => $unit2->id,
            'status' => 'accepted',
            'accepted_at' => now()->subDays(45),
            'existing_user_id' => $user->id,
        ]);

        $this->artisan('tenant-invitations:cleanup')
            ->assertSuccessful();

        $user->refresh();
        $this->assertTrue($user->is_archived);

        // Both invitations should be expired
        $this->assertDatabaseHas('tenant_invitations', [
            'id' => $invitation1->id,
            'status' => 'expired',
        ]);
        $this->assertDatabaseHas('tenant_invitations', [
            'id' => $invitation2->id,
            'status' => 'expired',
        ]);
    }

    public function test_logs_do_not_contain_pii(): void
    {
        Log::shouldReceive('info')
            ->once()
            ->withArgs(function ($msg, $ctx) {
                // Verify no email or phone in context
                return str_contains($msg, 'Tenant invitation marked as expired')
                    && isset($ctx['invitation_id'])
                    && ! isset($ctx['email'])
                    && ! isset($ctx['phone'])
                    && ! isset($ctx['tenant_phone']);
            });

        TenantInvitation::factory()->create([
            'status' => 'pending',
            'expires_at' => now()->subDays(31),
        ]);

        $this->artisan('tenant-invitations:cleanup')
            ->assertSuccessful();
    }
}
