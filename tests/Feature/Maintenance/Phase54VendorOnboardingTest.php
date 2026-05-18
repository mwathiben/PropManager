<?php

declare(strict_types=1);

namespace Tests\Feature\Maintenance;

use App\Mail\VendorCreatedMailable;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Phase-54 VENDOR-ONBOARDING-1/2/3 watchdog.
 */
class Phase54VendorOnboardingTest extends TestCase
{
    use RefreshDatabase;

    public function test_observer_queues_welcome_mail_on_vendor_creation(): void
    {
        Mail::fake();

        $landlord = User::factory()->create(['role' => 'landlord']);
        $vendor = Vendor::create([
            'landlord_id' => $landlord->id,
            'name' => 'Acme Plumbing',
            'contact_person' => 'Joe',
            'email' => 'joe@acme.test',
            'phone' => '0712345678',
            'is_active' => true,
        ]);

        Mail::assertQueued(VendorCreatedMailable::class, function ($mail) use ($vendor) {
            return $mail->hasTo($vendor->email);
        });
    }

    public function test_observer_skips_when_vendor_email_is_null(): void
    {
        Mail::fake();

        $landlord = User::factory()->create(['role' => 'landlord']);
        Vendor::create([
            'landlord_id' => $landlord->id,
            'name' => 'Phone-only vendor',
            'email' => null,
            'phone' => '0712345678',
            'is_active' => true,
        ]);

        Mail::assertNothingQueued();
    }

    public function test_signed_url_grants_access_to_profile_edit(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        $vendor = Vendor::create([
            'landlord_id' => $landlord->id,
            'name' => 'Acme Plumbing',
            'email' => 'a@b.test',
            'phone' => '0712345678',
            'is_active' => true,
        ]);

        $url = URL::signedRoute('vendor.profile.edit', ['vendor' => $vendor->id], now()->addDays(7));

        $this->get($url)
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Vendor/Profile')
                ->where('vendor.id', $vendor->id)
                ->where('vendor.name', 'Acme Plumbing'));
    }

    public function test_unsigned_url_is_rejected(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        $vendor = Vendor::create([
            'landlord_id' => $landlord->id,
            'name' => 'Acme Plumbing',
            'email' => 'a@b.test',
            'phone' => '0712345678',
            'is_active' => true,
        ]);

        $this->get('/v/profile/'.$vendor->id)
            ->assertForbidden();
    }

    public function test_signed_url_can_update_phone_and_specialties(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        $vendor = Vendor::create([
            'landlord_id' => $landlord->id,
            'name' => 'Acme Plumbing',
            'email' => 'a@b.test',
            'phone' => '0700000000',
            'is_active' => true,
        ]);

        $url = URL::signedRoute(
            'vendor.profile.update',
            ['vendor' => $vendor->id],
            now()->addDays(7),
        );

        $this->patch($url, [
            'phone' => '0722333444',
            'notes' => 'Plumbing + electrical, Nairobi CBD',
        ])->assertRedirect();

        $vendor->refresh();
        $this->assertSame('0722333444', $vendor->phone);
        $this->assertSame('Plumbing + electrical, Nairobi CBD', $vendor->notes);
    }

    public function test_vendor_email_is_immutable_from_signed_url(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        $vendor = Vendor::create([
            'landlord_id' => $landlord->id,
            'name' => 'Acme Plumbing',
            'email' => 'a@b.test',
            'phone' => '0700000000',
            'is_active' => true,
        ]);

        $url = URL::signedRoute(
            'vendor.profile.update',
            ['vendor' => $vendor->id],
            now()->addDays(7),
        );

        // The validator does not list 'email' as an allowed field, so the
        // mutation is silently dropped — the row keeps its original value.
        $this->patch($url, [
            'email' => 'attacker@evil.test',
            'phone' => '0722333444',
        ])->assertRedirect();

        $this->assertSame('a@b.test', $vendor->fresh()->email);
    }

    public function test_lang_vendor_onboarding_keys_have_identity_parity(): void
    {
        $en = require base_path('lang/en/maintenance.php');
        $sw = require base_path('lang/sw/maintenance.php');
        $ar = require base_path('lang/ar/maintenance.php');

        $this->assertSame(array_keys($en['vendor_onboarding']), array_keys($sw['vendor_onboarding']));
        $this->assertSame(array_keys($en['vendor_onboarding']), array_keys($ar['vendor_onboarding']));
        $this->assertSame(array_keys($en['vendor_onboarding']['form']), array_keys($sw['vendor_onboarding']['form']));
        $this->assertSame(array_keys($en['vendor_onboarding']['form']), array_keys($ar['vendor_onboarding']['form']));
    }
}
