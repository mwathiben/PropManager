<?php

declare(strict_types=1);

namespace Tests\Feature\Onboarding;

use App\Models\EmergencyContact;
use App\Models\LandlordProfile;
use App\Models\User;
use App\Services\Onboarding\MirrorAuditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * Phase-46 CANONICAL-AUDIT-1: scan + scanOne shape + drift detection
 * for each registered mirror.
 */
class Phase46MirrorAuditServiceTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    public function test_scan_returns_one_row_per_registered_mirror(): void
    {
        $results = app(MirrorAuditService::class)->scan();

        $this->assertCount(
            count(config('onboarding.mirrors')),
            $results,
            'CANONICAL-AUDIT-1: scan must return one row per mirror.',
        );

        foreach ($results as $row) {
            $this->assertArrayHasKey('mirror', $row);
            $this->assertArrayHasKey('canonical', $row);
            $this->assertArrayHasKey('pinned', $row);
            $this->assertArrayHasKey('drift_count', $row);
        }
    }

    public function test_landlord_profile_photo_drift_is_detected(): void
    {
        $landlord = User::factory()->create([
            'role' => 'landlord',
            'profile_photo_path' => 'old/photo.jpg',
        ]);

        LandlordProfile::create([
            'user_id' => $landlord->id,
            'profile_photo_path' => 'new/photo.jpg',
        ]);

        $row = app(MirrorAuditService::class)->scanOne([
            'column' => 'users.profile_photo_path',
            'canonical' => 'landlord_profiles.profile_photo_path',
            'key' => 'landlord_profiles.user_id',
            'role_scope' => ['landlord'],
            'pinned' => true,
        ]);

        $this->assertSame(1, $row['drift_count'], 'drift between users + landlord_profiles must be detected');
    }

    public function test_matched_profile_photo_produces_zero_drift(): void
    {
        $landlord = User::factory()->create([
            'role' => 'landlord',
            'profile_photo_path' => 'same/photo.jpg',
        ]);

        LandlordProfile::create([
            'user_id' => $landlord->id,
            'profile_photo_path' => 'same/photo.jpg',
        ]);

        $row = app(MirrorAuditService::class)->scanOne([
            'column' => 'users.profile_photo_path',
            'canonical' => 'landlord_profiles.profile_photo_path',
            'key' => 'landlord_profiles.user_id',
            'role_scope' => ['landlord'],
            'pinned' => true,
        ]);

        $this->assertSame(0, $row['drift_count']);
    }

    public function test_null_safe_equality_treats_both_null_as_match(): void
    {
        $landlord = User::factory()->create([
            'role' => 'landlord',
            'profile_photo_path' => null,
        ]);

        LandlordProfile::create([
            'user_id' => $landlord->id,
            'profile_photo_path' => null,
        ]);

        $row = app(MirrorAuditService::class)->scanOne([
            'column' => 'users.profile_photo_path',
            'canonical' => 'landlord_profiles.profile_photo_path',
            'key' => 'landlord_profiles.user_id',
            'role_scope' => ['landlord'],
            'pinned' => true,
        ]);

        $this->assertSame(0, $row['drift_count'], 'NULL <=> NULL must NOT count as drift');
    }

    public function test_null_vs_value_counts_as_drift(): void
    {
        $landlord = User::factory()->create([
            'role' => 'landlord',
            'profile_photo_path' => null,
        ]);

        LandlordProfile::create([
            'user_id' => $landlord->id,
            'profile_photo_path' => 'set/photo.jpg',
        ]);

        $row = app(MirrorAuditService::class)->scanOne([
            'column' => 'users.profile_photo_path',
            'canonical' => 'landlord_profiles.profile_photo_path',
            'key' => 'landlord_profiles.user_id',
            'role_scope' => ['landlord'],
            'pinned' => true,
        ]);

        $this->assertSame(1, $row['drift_count']);
    }

    public function test_role_scope_excludes_off_role_users(): void
    {
        // A tenant with a profile_photo_path that mismatches a (nonexistent)
        // LandlordProfile row should NOT register as drift because the
        // mirror is scoped to landlord/caretaker.
        $tenant = User::factory()->create([
            'role' => 'tenant',
            'profile_photo_path' => 'tenant/photo.jpg',
        ]);

        $row = app(MirrorAuditService::class)->scanOne([
            'column' => 'users.profile_photo_path',
            'canonical' => 'landlord_profiles.profile_photo_path',
            'key' => 'landlord_profiles.user_id',
            'role_scope' => ['landlord'],
            'pinned' => true,
        ]);

        $this->assertSame(0, $row['drift_count']);
    }

    public function test_canonical_filter_applies_to_emergency_contact_primary(): void
    {
        $setup = $this->createLandlordWithFullSetup();
        ['tenant' => $tenant] = $this->createTenantWithActiveLease(
            $setup['landlord'],
            $setup['units']->first(),
        );

        // Create a non-primary contact with a phone different from the user mirror.
        // The audit should ignore it because the canonical_filter requires is_primary=true.
        EmergencyContact::create([
            'landlord_id' => $setup['landlord']->id,
            'tenant_id' => $tenant->id,
            'name' => 'Friend',
            'relationship' => 'friend',
            'phone' => '+254700111111',
            'is_primary' => false,
        ]);
        $tenant->update(['emergency_contact_phone' => '+254700222222']);

        $row = app(MirrorAuditService::class)->scanOne([
            'column' => 'users.emergency_contact_phone',
            'canonical' => 'emergency_contacts.phone',
            'key' => 'emergency_contacts.tenant_id',
            'canonical_filter' => ['is_primary' => true],
            'role_scope' => ['tenant'],
            'pinned' => true,
        ]);

        // Zero because no is_primary=true row exists at all.
        $this->assertSame(0, $row['drift_count']);
    }

    public function test_audit_cron_emits_metric_and_succeeds(): void
    {
        $this->artisan('onboarding:dedupe-audit')->assertExitCode(0);
    }
}
