<?php

declare(strict_types=1);

namespace Tests\Feature\TenantPortal;

use App\Models\NotificationPreference;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * Phase-28 TENANT-PROFILE-1/2/3 watchdog suite.
 *
 * Covers:
 *  - dedicated /tenant/profile surface (not the landlord page)
 *  - profile field updates with tenant-only fields
 *  - password change with current-password verification
 *  - per-user locale persistence
 *  - NotificationPreference matrix updates via tenant.profile.notification-prefs
 *  - cross-role isolation (landlord cannot reach tenant routes)
 */
class Phase28ProfileTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    private User $landlord;

    private User $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $setup = $this->createLandlordWithFullSetup();
        $this->landlord = $setup['landlord'];
        ['tenant' => $this->tenant] = $this->createTenantWithActiveLease(
            $this->landlord,
            $setup['units']->first(),
        );
    }

    public function test_landlord_profile_edit_redirects_tenants_to_dedicated_surface(): void
    {
        $response = $this->actingAs($this->tenant)->get(route('profile.edit'));

        $response->assertRedirect(route('tenant.profile.edit'));
    }

    public function test_tenant_profile_edit_renders_dedicated_page(): void
    {
        $response = $this->actingAs($this->tenant)->get(route('tenant.profile.edit'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Tenant/Profile')
            ->where('user.id', $this->tenant->id)
            ->where('user.email', $this->tenant->email)
            ->has('supportedLocales')
            ->has('notificationPreference')
        );
    }

    public function test_landlord_cannot_reach_tenant_profile_route(): void
    {
        $response = $this->actingAs($this->landlord)->get(route('tenant.profile.edit'));

        $response->assertForbidden();
    }

    public function test_tenant_can_update_personal_and_emergency_fields(): void
    {
        $response = $this->actingAs($this->tenant)
            ->patch(route('tenant.profile.update'), [
                'name' => 'Updated Tenant',
                'email' => 'updated.tenant@example.test',
                'mobile_number' => '+254712345678',
                'emergency_contact_name' => 'Jane Doe',
                'emergency_contact_phone' => '+254700000000',
            ]);

        $response->assertRedirect(route('tenant.profile.edit'));
        $response->assertSessionHas('success');

        $this->tenant->refresh();
        $this->assertSame('Updated Tenant', $this->tenant->name);
        $this->assertSame('updated.tenant@example.test', $this->tenant->email);
        $this->assertSame('Jane Doe', $this->tenant->emergency_contact_name);
        $this->assertSame('+254700000000', $this->tenant->emergency_contact_phone);
    }

    public function test_password_update_requires_correct_current_password(): void
    {
        $this->tenant->update(['password' => Hash::make('correct-old-password')]);

        $response = $this->actingAs($this->tenant)
            ->from(route('tenant.profile.edit'))
            ->patch(route('tenant.profile.password'), [
                'current_password' => 'wrong-password',
                'password' => 'NewStrongPassword!1',
                'password_confirmation' => 'NewStrongPassword!1',
            ]);

        $response->assertSessionHasErrors('current_password');
        $this->assertTrue(Hash::check('correct-old-password', $this->tenant->fresh()->password));
    }

    public function test_password_update_succeeds_with_correct_current_password(): void
    {
        $this->tenant->update(['password' => Hash::make('correct-old-password')]);

        $response = $this->actingAs($this->tenant)
            ->patch(route('tenant.profile.password'), [
                'current_password' => 'correct-old-password',
                'password' => 'NewStrongPassword!1',
                'password_confirmation' => 'NewStrongPassword!1',
            ]);

        $response->assertRedirect(route('tenant.profile.edit'));
        $this->assertTrue(Hash::check('NewStrongPassword!1', $this->tenant->fresh()->password));
    }

    public function test_notification_prefs_update_persists_to_notification_preferences(): void
    {
        $payload = [
            'rent_reminder_enabled' => true,
            'arrears_notice_enabled' => false,
            'invoice_enabled' => true,
            'receipt_enabled' => true,
            'lease_expiry_enabled' => false,
            'lease_renewal_enabled' => true,
            'maintenance_notice_enabled' => true,
            'general_enabled' => false,
            'email_enabled' => true,
            'sms_enabled' => true,
            'whatsapp_enabled' => false,
            'push_enabled' => false,
            'in_app_enabled' => true,
            'whatsapp_number' => null,
        ];

        $response = $this->actingAs($this->tenant)
            ->patch(route('tenant.profile.notification-prefs'), $payload);

        $response->assertRedirect(route('tenant.profile.edit'));

        $pref = NotificationPreference::where('user_id', $this->tenant->id)
            ->where('landlord_id', $this->landlord->id)
            ->firstOrFail();

        $this->assertTrue($pref->rent_reminder_enabled);
        $this->assertFalse($pref->arrears_notice_enabled);
        $this->assertTrue($pref->sms_enabled);
        $this->assertFalse($pref->push_enabled);
        $this->assertTrue(
            $pref->canReceive('rent_reminder', 'sms'),
            'sms should be a valid channel for rent_reminder after enabling both flags',
        );
        $this->assertFalse(
            $pref->canReceive('arrears_notice', 'sms'),
            'arrears_notice toggled off must block delivery on every channel',
        );
    }

    public function test_locale_update_persists_to_users_table(): void
    {
        $this->actingAs($this->tenant)
            ->patch(route('locale.update'), ['locale' => 'sw'])
            ->assertRedirect();

        $this->assertSame('sw', $this->tenant->fresh()->locale);
    }

    public function test_tenant_profile_routes_require_authentication(): void
    {
        $this->get(route('tenant.profile.edit'))->assertRedirect(route('login'));
        $this->patch(route('tenant.profile.update'))->assertRedirect(route('login'));
        $this->patch(route('tenant.profile.password'))->assertRedirect(route('login'));
        $this->patch(route('tenant.profile.notification-prefs'))->assertRedirect(route('login'));
    }
}
