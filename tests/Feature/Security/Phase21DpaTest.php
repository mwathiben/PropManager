<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Models\User;
use App\Services\KenyaDpaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase-21 DEFER-DPA-1 (closes Phase-13 DPA-10 deferral):
 * Kenya DPA Article 8 / Section 33 — children's data special handling.
 * Phase 13 shipped KenyaDpaService::isMinor() but the dob +
 * parental_consent columns never landed; Phase 21 adds the schema +
 * validation + the minorRequiresConsent() gate predicate.
 */
class Phase21DpaTest extends TestCase
{
    use RefreshDatabase;

    public function test_users_table_has_dob_and_parental_consent_columns(): void
    {
        $user = User::factory()->create([
            'role' => 'tenant',
            'dob' => '2020-01-15',
            'parental_consent_artefact_url' => 'https://drive.example.com/consent.pdf',
            'parental_consent_provided_at' => now(),
        ]);

        $fresh = $user->fresh();

        $this->assertNotNull($fresh->dob);
        $this->assertSame('2020-01-15', $fresh->dob->format('Y-m-d'));
        $this->assertSame('https://drive.example.com/consent.pdf', $fresh->parental_consent_artefact_url);
        $this->assertNotNull($fresh->parental_consent_provided_at);
    }

    public function test_minor_requires_consent_returns_false_when_dob_is_null(): void
    {
        $tenant = User::factory()->create(['role' => 'tenant', 'dob' => null]);

        $this->assertFalse(
            app(KenyaDpaService::class)->minorRequiresConsent($tenant),
            'No dob = no minor determination — operator process resolves.',
        );
    }

    public function test_minor_requires_consent_returns_false_for_adult(): void
    {
        $tenant = User::factory()->create([
            'role' => 'tenant',
            'dob' => '1990-01-15',
        ]);

        $this->assertFalse(
            app(KenyaDpaService::class)->minorRequiresConsent($tenant),
            'Adult dob — no consent required.',
        );
    }

    public function test_minor_requires_consent_returns_true_for_minor_without_consent(): void
    {
        $tenant = User::factory()->create([
            'role' => 'tenant',
            'dob' => now()->subYears(10)->format('Y-m-d'),
            'parental_consent_provided_at' => null,
        ]);

        $this->assertTrue(
            app(KenyaDpaService::class)->minorRequiresConsent($tenant),
            'Minor without consent — gate must trip.',
        );
    }

    public function test_minor_requires_consent_returns_false_when_consent_provided(): void
    {
        $tenant = User::factory()->create([
            'role' => 'tenant',
            'dob' => now()->subYears(10)->format('Y-m-d'),
            'parental_consent_artefact_url' => 'https://drive.example.com/consent.pdf',
            'parental_consent_provided_at' => now()->subDay(),
        ]);

        $this->assertFalse(
            app(KenyaDpaService::class)->minorRequiresConsent($tenant),
            'Minor with consent artefact + timestamp — gate clears.',
        );
    }

    public function test_update_tenant_request_rejects_minor_dob_without_consent(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        $tenant = User::factory()->create([
            'role' => 'tenant',
            'landlord_id' => $landlord->id,
        ]);

        $payload = [
            'name' => $tenant->name,
            'email' => $tenant->email,
            'phone' => '0712345678',
            'id_number' => '12345678',
            'dob' => now()->subYears(10)->format('Y-m-d'),
            // intentionally missing parental_consent_artefact_url
        ];

        $response = $this->actingAs($landlord)->put(
            route('tenants.update', $tenant),
            $payload,
        );

        $response->assertSessionHasErrors('parental_consent_artefact_url');
    }

    public function test_update_tenant_request_accepts_minor_dob_with_consent(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        $tenant = User::factory()->create([
            'role' => 'tenant',
            'landlord_id' => $landlord->id,
        ]);

        $payload = [
            'name' => $tenant->name,
            'email' => $tenant->email,
            'phone' => '0712345678',
            'id_number' => '12345678',
            'dob' => now()->subYears(10)->format('Y-m-d'),
            'parental_consent_artefact_url' => 'https://drive.example.com/consent.pdf',
            'parental_consent_provided_at' => now()->subDay()->toIso8601String(),
        ];

        $response = $this->actingAs($landlord)->put(
            route('tenants.update', $tenant),
            $payload,
        );

        $response->assertSessionHasNoErrors();
        $this->assertNotNull($tenant->fresh()->dob);
    }

    public function test_audit_minor_consent_command_succeeds_when_no_drift(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        User::factory()->create([
            'role' => 'tenant',
            'landlord_id' => $landlord->id,
            'dob' => '1990-01-15',
        ]);
        User::factory()->create([
            'role' => 'tenant',
            'landlord_id' => $landlord->id,
            'dob' => now()->subYears(10)->format('Y-m-d'),
            'parental_consent_artefact_url' => 'https://drive.example.com/consent.pdf',
            'parental_consent_provided_at' => now()->subDay(),
        ]);

        $exitCode = $this->artisan('tenants:audit-minor-consent')->run();

        $this->assertSame(0, $exitCode, 'No drift = SUCCESS exit.');
    }

    public function test_audit_minor_consent_command_fails_when_minor_lacks_consent(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        $minor = User::factory()->create([
            'role' => 'tenant',
            'landlord_id' => $landlord->id,
            'dob' => now()->subYears(10)->format('Y-m-d'),
            'parental_consent_provided_at' => null,
        ]);

        $exitCode = $this->artisan('tenants:audit-minor-consent')->run();

        $this->assertSame(1, $exitCode, 'Drift detected = FAILURE exit so cron monitoring alerts.');
    }

    public function test_audit_minor_consent_ignores_soft_deleted_and_non_tenant_users(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);
        // Soft-deleted minor without consent — must NOT trip the audit.
        $deletedMinor = User::factory()->create([
            'role' => 'tenant',
            'landlord_id' => $landlord->id,
            'dob' => now()->subYears(10)->format('Y-m-d'),
            'parental_consent_provided_at' => null,
        ]);
        $deletedMinor->delete();

        // Caretaker with minor dob (unusual but possible) — only role=tenant
        // is in scope per Kenya DPA tenant-data definition.
        User::factory()->create([
            'role' => 'caretaker',
            'landlord_id' => $landlord->id,
            'dob' => now()->subYears(10)->format('Y-m-d'),
            'parental_consent_provided_at' => null,
        ]);

        $exitCode = $this->artisan('tenants:audit-minor-consent')->run();

        $this->assertSame(0, $exitCode, 'Soft-deleted + non-tenant minors must not trip the audit.');
    }
}
