<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Models\Consent;
use App\Models\LegalDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Slice 1 — the Platform consent gate going LIVE.
 *
 * The Consent + LegalDocument system already existed but was triple-dormant:
 * EnsureLegalAcceptance was wired nowhere, no current documents were seeded, and
 * accept() validated a different type vocabulary than the gate checked. These tests
 * pin the gate's live, coherent behaviour: a user must hold a valid consent for the
 * current version of every required document before reaching the app; a new version
 * forces re-consent; the consent record is tamper-evidence-bound to the exact
 * document content (hash). The gate stays inert when no documents are published, so
 * the rest of the suite is unaffected.
 */
class PlatformConsentGateTest extends TestCase
{
    use RefreshDatabase;

    private function publishDoc(string $type, string $version = '1.0', string $content = 'Body'): LegalDocument
    {
        return LegalDocument::create([
            'type' => $type,
            'version' => $version,
            'title' => ucfirst($type),
            'content' => $content,
            'summary' => 'summary',
            'is_active' => true,
            'effective_date' => now()->toDateString(),
        ]);
    }

    private function seedRequiredDocs(): void
    {
        $this->publishDoc('terms', '1.0', 'Terms body');
        $this->publishDoc('privacy', '1.0', 'Privacy body');
    }

    public function test_authenticated_user_without_consent_is_redirected_to_consent_required(): void
    {
        $this->seedRequiredDocs();
        $user = User::factory()->create(['role' => 'landlord']);

        $this->actingAs($user)->get('/dashboard')
            ->assertRedirect(route('consent.required'));
    }

    public function test_user_with_all_required_consents_passes_the_gate(): void
    {
        $this->seedRequiredDocs();
        $user = User::factory()->create(['role' => 'landlord']);
        Consent::record($user, 'terms', '1.0');
        Consent::record($user, 'privacy', '1.0');

        $location = $this->actingAs($user)->get('/dashboard')->headers->get('Location');
        $this->assertNotSame(route('consent.required'), $location);
    }

    public function test_a_new_document_version_forces_reconsent(): void
    {
        $this->seedRequiredDocs();
        $user = User::factory()->create(['role' => 'landlord']);
        Consent::record($user, 'terms', '1.0');
        Consent::record($user, 'privacy', '1.0');

        LegalDocument::where('type', 'terms')->update(['is_active' => false]);
        $this->publishDoc('terms', '2.0', 'Terms body v2');

        $this->actingAs($user)->get('/dashboard')
            ->assertRedirect(route('consent.required'));
    }

    public function test_gate_is_inert_when_no_active_documents_exist(): void
    {
        $user = User::factory()->create(['role' => 'landlord']);

        $location = $this->actingAs($user)->get('/dashboard')->headers->get('Location');
        $this->assertNotSame(route('consent.required'), $location);
    }

    public function test_consent_required_page_is_reachable_while_pending(): void
    {
        $this->seedRequiredDocs();
        $user = User::factory()->create(['role' => 'landlord']);

        $this->actingAs($user)->get(route('consent.required'))->assertOk();
    }

    public function test_accept_records_canonical_consent_bound_to_the_document_hash(): void
    {
        $this->seedRequiredDocs();
        $user = User::factory()->create(['role' => 'landlord']);

        $this->actingAs($user)->post(route('consent.accept'), [
            'consents' => ['terms:1.0', 'privacy:1.0'],
        ])->assertRedirect();

        $this->assertDatabaseHas('consents', [
            'user_id' => $user->id,
            'consent_type' => 'terms',
            'version' => '1.0',
            'is_granted' => true,
        ]);

        $consent = Consent::where('user_id', $user->id)
            ->where('consent_type', 'terms')
            ->first();

        $this->assertSame(
            hash('sha256', 'Terms body'),
            $consent->metadata['document_hash'] ?? null,
            'consent must be bound to the exact document content it accepted',
        );
    }

    public function test_accept_returns_422_for_non_active_document_version(): void
    {
        // Seed a document that exists but is inactive (stale version)
        LegalDocument::create([
            'type' => 'terms',
            'version' => '0.9',
            'title' => 'Terms (Draft)',
            'content' => 'Old draft',
            'summary' => 'summary',
            'is_active' => false,
            'effective_date' => now()->toDateString(),
        ]);

        $user = User::factory()->create(['role' => 'landlord']);

        $this->actingAs($user)->post(route('consent.accept'), [
            'consents' => ['terms:0.9'],
        ])->assertStatus(422);

        $this->assertDatabaseMissing('consents', [
            'user_id' => $user->id,
            'consent_type' => 'terms',
        ]);
    }

    public function test_accept_rejects_a_malformed_consent_key_via_the_form_request(): void
    {
        $user = User::factory()->create(['role' => 'landlord']);

        $this->actingAs($user)
            ->from(route('consent.required'))
            ->post(route('consent.accept'), ['consents' => ['not-a-valid-key']])
            ->assertStatus(302)
            ->assertSessionHasErrors('consents.0');
    }
}
