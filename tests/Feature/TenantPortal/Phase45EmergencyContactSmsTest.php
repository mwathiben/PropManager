<?php

declare(strict_types=1);

namespace Tests\Feature\TenantPortal;

use App\Models\EmergencyContact;
use App\Models\User;
use App\Services\Sms\Contracts\SmsDriver;
use App\Services\Sms\SmsOtpService;
use App\Services\Sms\StubSmsDriver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * Phase-45 EMERGENCY-CONTACT-SMS-1/2/3 watchdog suite.
 */
class Phase45EmergencyContactSmsTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    private User $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $setup = $this->createLandlordWithFullSetup();
        ['tenant' => $this->tenant] = $this->createTenantWithActiveLease(
            $setup['landlord'],
            $setup['units']->first(),
        );

        // Ensure the container always returns the stub driver in this suite.
        StubSmsDriver::$sent = [];
        $this->app->bind(SmsDriver::class, fn () => new StubSmsDriver);
    }

    public function test_otp_service_caches_six_digit_code_and_calls_driver(): void
    {
        $service = app(SmsOtpService::class);
        $ref = $service->generateAndSend('+254712345678', 'otp:test:1');

        $this->assertNotEmpty($ref);
        $this->assertCount(1, StubSmsDriver::$sent);

        $cached = Cache::get('otp:test:1');
        $this->assertIsString($cached);
        $this->assertMatchesRegularExpression('/^\d{6}$/', $cached);
    }

    public function test_otp_service_verify_consumes_cache_entry(): void
    {
        $service = app(SmsOtpService::class);
        $service->generateAndSend('+254712345678', 'otp:test:1');
        $code = Cache::get('otp:test:1');

        $service->verify('otp:test:1', $code);

        $this->assertNull(Cache::get('otp:test:1'), 'verify must consume the entry');
    }

    public function test_otp_service_rejects_invalid_or_expired_code(): void
    {
        $service = app(SmsOtpService::class);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $service->verify('otp:test:does-not-exist', '123456');
    }

    public function test_endpoint_sends_otp_increments_attempt_counter_and_caches_code(): void
    {
        $contact = EmergencyContact::create([
            'landlord_id' => $this->tenant->landlord_id,
            'tenant_id' => $this->tenant->id,
            'name' => 'Mama Jane',
            'relationship' => 'mother',
            'phone' => '+254712345678',
            'is_primary' => true,
        ]);

        $response = $this->actingAs($this->tenant)
            ->post(route('tenant.emergency-contacts.send-otp', $contact));

        $response->assertRedirect();
        $contact->refresh();
        $this->assertSame(1, $contact->verification_attempts_24h);
        $this->assertNotNull($contact->last_otp_sent_at);
        $this->assertNotNull(Cache::get('otp:contact:'.$contact->id));
    }

    public function test_endpoint_rate_limits_after_3_sends_in_24h(): void
    {
        $contact = EmergencyContact::create([
            'landlord_id' => $this->tenant->landlord_id,
            'tenant_id' => $this->tenant->id,
            'name' => 'Mama Jane',
            'relationship' => 'mother',
            'phone' => '+254712345678',
            'is_primary' => true,
            'verification_attempts_24h' => 3,
            'last_otp_sent_at' => now()->subHours(2),
        ]);

        $response = $this->actingAs($this->tenant)
            ->post(route('tenant.emergency-contacts.send-otp', $contact));

        // Rate-limited responses come back as withErrors (back redirect).
        $response->assertSessionHasErrors('phone');
    }

    public function test_endpoint_resets_counter_after_24h_window(): void
    {
        $contact = EmergencyContact::create([
            'landlord_id' => $this->tenant->landlord_id,
            'tenant_id' => $this->tenant->id,
            'name' => 'Mama Jane',
            'relationship' => 'mother',
            'phone' => '+254712345678',
            'is_primary' => true,
            'verification_attempts_24h' => 3,
            'last_otp_sent_at' => now()->subHours(25),
        ]);

        $this->actingAs($this->tenant)
            ->post(route('tenant.emergency-contacts.send-otp', $contact))
            ->assertRedirect();

        $contact->refresh();
        $this->assertSame(1, $contact->verification_attempts_24h, 'counter resets to 1 after >24h');
    }

    public function test_endpoint_marks_verified_on_correct_code(): void
    {
        $contact = EmergencyContact::create([
            'landlord_id' => $this->tenant->landlord_id,
            'tenant_id' => $this->tenant->id,
            'name' => 'Mama Jane',
            'relationship' => 'mother',
            'phone' => '+254712345678',
            'is_primary' => true,
        ]);

        $this->actingAs($this->tenant)
            ->post(route('tenant.emergency-contacts.send-otp', $contact));

        $code = Cache::get('otp:contact:'.$contact->id);

        $response = $this->actingAs($this->tenant)
            ->post(route('tenant.emergency-contacts.verify-otp', $contact), [
                'code' => $code,
            ]);

        $response->assertRedirect();
        $contact->refresh();
        $this->assertNotNull($contact->verified_at);
    }

    public function test_endpoint_rejects_wrong_code(): void
    {
        $contact = EmergencyContact::create([
            'landlord_id' => $this->tenant->landlord_id,
            'tenant_id' => $this->tenant->id,
            'name' => 'Mama Jane',
            'relationship' => 'mother',
            'phone' => '+254712345678',
            'is_primary' => true,
        ]);

        $this->actingAs($this->tenant)
            ->post(route('tenant.emergency-contacts.send-otp', $contact));

        $response = $this->actingAs($this->tenant)
            ->post(route('tenant.emergency-contacts.verify-otp', $contact), [
                'code' => '000000',
            ]);

        $response->assertSessionHasErrors('code');
        $contact->refresh();
        $this->assertNull($contact->verified_at);
    }

    public function test_saving_primary_contact_syncs_users_mirror(): void
    {
        EmergencyContact::create([
            'landlord_id' => $this->tenant->landlord_id,
            'tenant_id' => $this->tenant->id,
            'name' => 'Mama Jane',
            'relationship' => 'mother',
            'phone' => '+254712345678',
            'is_primary' => true,
        ]);

        $this->tenant->refresh();
        $this->assertSame('Mama Jane', $this->tenant->emergency_contact_name);
        $this->assertSame('+254712345678', $this->tenant->emergency_contact_phone);
    }

    public function test_saving_new_primary_unflags_other_rows(): void
    {
        $first = EmergencyContact::create([
            'landlord_id' => $this->tenant->landlord_id,
            'tenant_id' => $this->tenant->id,
            'name' => 'Mama Jane',
            'relationship' => 'mother',
            'phone' => '+254712345678',
            'is_primary' => true,
        ]);

        EmergencyContact::create([
            'landlord_id' => $this->tenant->landlord_id,
            'tenant_id' => $this->tenant->id,
            'name' => 'Baba Joe',
            'relationship' => 'father',
            'phone' => '+254712999999',
            'is_primary' => true,
        ]);

        $first->refresh();
        $this->assertFalse($first->is_primary, 'old primary must be cleared');
    }

    public function test_stranger_cannot_trigger_otp_for_another_users_contact(): void
    {
        $stranger = User::factory()->create(['role' => 'tenant']);
        $contact = EmergencyContact::create([
            'landlord_id' => $this->tenant->landlord_id,
            'tenant_id' => $this->tenant->id,
            'name' => 'Mama Jane',
            'relationship' => 'mother',
            'phone' => '+254712345678',
            'is_primary' => true,
        ]);

        $response = $this->actingAs($stranger)
            ->post(route('tenant.emergency-contacts.send-otp', $contact));

        $response->assertForbidden();
    }
}
