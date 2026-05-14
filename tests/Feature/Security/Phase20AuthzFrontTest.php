<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Models\User;
use App\Support\AuthAbilities;
use App\Support\UserDto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * Phase-20 Phase 1 coverage (AUTHZ-FRONT HIGH severity):
 *   AUTHZ-FRONT-1: Inertia abilities map shared via auth.user.abilities
 *   AUTHZ-FRONT-6: User shipped as slim DTO (not full Eloquent model)
 */
class Phase20AuthzFrontTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    public function test_auth_abilities_returns_flat_boolean_map_for_super_admin(): void
    {
        $superAdmin = User::factory()->create(['role' => 'super_admin']);

        $abilities = AuthAbilities::for($superAdmin);

        $this->assertIsArray($abilities);
        $this->assertSame(
            [
                'access-admin', 'view-audit-logs', 'view-security-logs',
                'manage-subscription', 'export-data', 'request-deletion',
                'integration:webhook',
                // Phase-21 DEFER-AUTHZ-1: management Gates.
                'tenants:manage', 'invoices:manage', 'payments:manage',
                'properties:manage', 'buildings:manage', 'units:manage',
                'documents:manage', 'settings:manage', 'team:manage',
                'templates:manage', 'finances:manage', 'imports:manage',
            ],
            array_keys($abilities),
            'AuthAbilities::for must emit a stable key set — adding/removing keys is a Phase-20+ change (sync with useAuth.ts).',
        );

        $this->assertTrue($abilities['access-admin']);
        $this->assertTrue($abilities['view-audit-logs']);
        $this->assertTrue($abilities['view-security-logs']);
    }

    public function test_auth_abilities_returns_role_appropriate_for_landlord(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        $abilities = AuthAbilities::for($landlord);

        $this->assertFalse($abilities['access-admin'], 'Landlord must NOT have access-admin.');
        $this->assertTrue($abilities['view-audit-logs'], 'Landlord MAY view audit logs (own scope per Phase-19 POLICY-6).');
        $this->assertFalse($abilities['view-security-logs'], 'Landlord must NOT view security logs.');
        $this->assertTrue($abilities['manage-subscription'], 'Landlord must manage subscription (Phase-19 POLICY-5).');
        $this->assertTrue($abilities['export-data'], 'GDPR export-data is allowed for all users.');
        $this->assertTrue($abilities['request-deletion'], 'GDPR request-deletion is allowed for all users.');
    }

    public function test_auth_abilities_denies_restricted_super_admin_write_abilities(): void
    {
        // AUTHZ-FRONT-1 must respect Phase-13 DPA-4 — restricted user's
        // abilities map shows false for any ability NOT on the DPA-4
        // allow-list. Even super-admin.
        $restrictedSuperAdmin = User::factory()->create([
            'role' => 'super_admin',
            'restricted_at' => now(),
        ]);

        $abilities = AuthAbilities::for($restrictedSuperAdmin);

        // Read-side abilities on the DPA-4 allow-list — still true.
        $this->assertTrue($abilities['view-audit-logs']);
        $this->assertTrue($abilities['view-security-logs']);
        $this->assertTrue($abilities['access-admin']);
        $this->assertTrue($abilities['export-data']);
        $this->assertTrue($abilities['request-deletion']);

        // manage-subscription is NOT on the DPA-4 allow-list → denied.
        $this->assertFalse(
            $abilities['manage-subscription'],
            'DPA-4 restricted super-admin must NOT pass manage-subscription (write-side ability).',
        );

        // integration:webhook is NOT on the DPA-4 allow-list → denied.
        $this->assertFalse(
            $abilities['integration:webhook'],
            'DPA-4 restricted super-admin must NOT pass integration:webhook (write-side ability).',
        );
    }

    public function test_user_dto_has_stable_slim_key_set(): void
    {
        // AUTHZ-FRONT-6: the slim DTO key set is the contract with the
        // Inertia frontend. Adding a field here means updating
        // resources/js/composables/useAuth.ts User type in the SAME
        // commit. This test pins the exact key set.
        $landlord = User::factory()->create(['role' => 'landlord']);

        $dto = UserDto::from($landlord);

        $this->assertSame(
            ['id', 'name', 'email', 'role', 'landlord_id', 'profile_photo_url', 'is_restricted', 'abilities'],
            array_keys($dto),
            'UserDto::from must emit a stable key set — drift between PHP DTO + TS interface causes runtime breakage.',
        );
    }

    public function test_user_dto_does_not_leak_sensitive_eloquent_fields(): void
    {
        // AUTHZ-FRONT-6: full Eloquent User has remember_token, password,
        // 2FA secrets, restricted_at timestamp, etc. The slim DTO must
        // NOT include any of those — only display-relevant + abilities.
        $user = User::factory()->create(['role' => 'landlord']);
        $dto = UserDto::from($user);

        $forbidden = [
            'password',
            'remember_token',
            'two_factor_secret',
            'two_factor_recovery_codes',
            'restricted_at',
            'paystack_customer_code',
            'phone',
            'created_at',
            'updated_at',
            'email_verified_at',
        ];

        foreach ($forbidden as $field) {
            $this->assertArrayNotHasKey(
                $field,
                $dto,
                "UserDto must NOT leak {$field} to the frontend.",
            );
        }
    }

    public function test_user_dto_is_restricted_reflects_phase13_dpa4_state(): void
    {
        $unrestricted = User::factory()->create(['role' => 'landlord']);
        $restricted = User::factory()->create(['role' => 'landlord', 'restricted_at' => now()]);

        $this->assertFalse(UserDto::from($unrestricted)['is_restricted']);
        $this->assertTrue(UserDto::from($restricted)['is_restricted']);
    }

    public function test_inertia_share_payload_includes_slim_user_with_abilities(): void
    {
        // End-to-end functional check: actingAs + visit a route that
        // returns Inertia and assert the props.auth.user shape. We use
        // /two-factor (auth-only middleware) to avoid the dashboard's
        // verified+payment.verified+kyc.complete middleware chain that
        // a brand-new factory user doesn't satisfy.
        $landlord = User::factory()->create([
            'role' => 'landlord',
            'email_verified_at' => now(),
        ]);

        $response = $this->actingAs($landlord)->get('/two-factor');

        $response->assertOk();

        $authUser = $response->viewData('page')['props']['auth']['user'];

        $this->assertIsArray($authUser, 'auth.user must be a slim DTO (array), not an Eloquent model.');
        $this->assertSame($landlord->id, $authUser['id']);
        $this->assertSame('landlord', $authUser['role']);
        $this->assertFalse($authUser['is_restricted']);
        $this->assertIsArray($authUser['abilities']);
        $this->assertTrue($authUser['abilities']['manage-subscription']);
        $this->assertFalse($authUser['abilities']['access-admin']);
    }

    public function test_inertia_share_payload_user_is_null_when_unauthenticated(): void
    {
        $response = $this->get('/login');

        // /login is an unauthenticated Inertia route; auth.user must
        // be null (not throw + not leak abilities). Inertia responds
        // to a regular GET with HTML; viewData('page') reads the
        // server-rendered page payload from the Inertia middleware.
        $auth = $response->viewData('page')['props']['auth'];

        $this->assertArrayHasKey('user', $auth, 'auth.user key must exist even when unauthenticated.');
        $this->assertNull(
            $auth['user'],
            'Unauthenticated requests must share auth.user=null, not an empty array or User instance.',
        );
    }
}
