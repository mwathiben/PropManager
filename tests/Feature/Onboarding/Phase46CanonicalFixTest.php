<?php

declare(strict_types=1);

namespace Tests\Feature\Onboarding;

use App\Enums\KycSubmissionStatus;
use App\Models\KycRequirement;
use App\Models\LandlordProfile;
use App\Models\TenantKycSubmission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * Phase-46 CANONICAL-FIX-1/2/3 watchdog suite.
 */
class Phase46CanonicalFixTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    public function test_landlord_profile_saved_listener_syncs_users_profile_photo_path(): void
    {
        $landlord = User::factory()->create([
            'role' => 'landlord',
            'profile_photo_path' => null,
        ]);

        LandlordProfile::create([
            'user_id' => $landlord->id,
            'profile_photo_path' => 'profiles/landlord-1.jpg',
        ]);

        $landlord->refresh();
        $this->assertSame('profiles/landlord-1.jpg', $landlord->profile_photo_path);
    }

    public function test_landlord_profile_update_propagates_to_users(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        $profile = LandlordProfile::create([
            'user_id' => $landlord->id,
            'profile_photo_path' => 'old.jpg',
        ]);

        $profile->update(['profile_photo_path' => 'new.jpg']);

        $landlord->refresh();
        $this->assertSame('new.jpg', $landlord->profile_photo_path);
    }

    public function test_kyc_completed_at_is_no_longer_written_by_profile_controller(): void
    {
        // Read the controller source — the writer line should be gone.
        $src = file_get_contents(app_path('Http/Controllers/ProfileController.php'));
        $this->assertStringNotContainsString(
            "'kyc_completed_at' => now()",
            $src,
            'CANONICAL-FIX-1: ProfileController must not write kyc_completed_at — column is deprecated.',
        );
    }

    public function test_kyc_completed_at_is_listed_in_mirror_exempt(): void
    {
        $exempt = collect(config('onboarding.mirror_exempt'))
            ->where('column', 'users.kyc_completed_at')
            ->first();

        $this->assertNotNull($exempt, 'CANONICAL-FIX-1: kyc_completed_at must be in mirror_exempt.');
        $this->assertSame('2026-08-17', $exempt['remove_at']);
    }

    public function test_kyc_verified_at_accessor_returns_max_reviewed_at(): void
    {
        $setup = $this->createLandlordWithFullSetup();
        ['tenant' => $tenant] = $this->createTenantWithActiveLease(
            $setup['landlord'],
            $setup['units']->first(),
        );

        $idReq = KycRequirement::create([
            'landlord_id' => $setup['landlord']->id,
            'requirement_type' => 'national_id',
            'label' => 'National ID',
            'is_required' => true,
            'is_active' => true,
        ]);

        $selfieReq = KycRequirement::create([
            'landlord_id' => $setup['landlord']->id,
            'requirement_type' => 'selfie',
            'label' => 'Selfie',
            'is_required' => true,
            'is_active' => true,
        ]);

        TenantKycSubmission::create([
            'landlord_id' => $setup['landlord']->id,
            'user_id' => $tenant->id,
            'requirement_id' => $idReq->id,
            'status' => KycSubmissionStatus::Approved,
            'submission_value' => '12345678',
            'reviewed_at' => '2026-05-10 10:00:00',
        ]);

        TenantKycSubmission::create([
            'landlord_id' => $setup['landlord']->id,
            'user_id' => $tenant->id,
            'requirement_id' => $selfieReq->id,
            'status' => KycSubmissionStatus::Approved,
            'submission_value' => 'selfie.jpg',
            'reviewed_at' => '2026-05-15 09:00:00',
        ]);

        $verifiedAt = $tenant->kycVerifiedAt();

        $this->assertNotNull($verifiedAt);
        $this->assertSame('2026-05-15 09:00:00', $verifiedAt->format('Y-m-d H:i:s'));
    }

    public function test_kyc_verified_at_returns_null_when_no_approved_submissions(): void
    {
        $setup = $this->createLandlordWithFullSetup();
        ['tenant' => $tenant] = $this->createTenantWithActiveLease(
            $setup['landlord'],
            $setup['units']->first(),
        );

        $this->assertNull($tenant->kycVerifiedAt());
    }

    public function test_canonical_national_id_prefers_approved_submission_over_user_column(): void
    {
        $setup = $this->createLandlordWithFullSetup();
        ['tenant' => $tenant] = $this->createTenantWithActiveLease(
            $setup['landlord'],
            $setup['units']->first(),
        );

        $tenant->update(['national_id' => 'STALE-ID']);

        $requirement = KycRequirement::create([
            'landlord_id' => $setup['landlord']->id,
            'requirement_type' => 'national_id',
            'label' => 'National ID',
            'is_required' => true,
            'is_active' => true,
        ]);

        TenantKycSubmission::create([
            'landlord_id' => $setup['landlord']->id,
            'user_id' => $tenant->id,
            'requirement_id' => $requirement->id,
            'status' => KycSubmissionStatus::Approved,
            'submission_value' => 'AUDIT-GRADE-ID',
            'reviewed_at' => now()->subDay(),
        ]);

        $this->assertSame('AUDIT-GRADE-ID', $tenant->canonicalNationalId());
    }

    public function test_canonical_national_id_falls_back_to_user_column_when_no_submission(): void
    {
        $setup = $this->createLandlordWithFullSetup();
        ['tenant' => $tenant] = $this->createTenantWithActiveLease(
            $setup['landlord'],
            $setup['units']->first(),
        );

        $tenant->update(['national_id' => 'USER-COL-ID']);

        $this->assertSame('USER-COL-ID', $tenant->canonicalNationalId());
    }
}
