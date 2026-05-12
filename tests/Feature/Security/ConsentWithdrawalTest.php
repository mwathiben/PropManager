<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Http\Controllers\ConsentController;
use App\Models\AuditLog;
use App\Models\Consent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase-13 DPA-1 regression coverage. GDPR Article 7(3) / Kenya DPA
 * Section 32: withdrawing consent must be as easy as granting it.
 *
 *   - withdrawing a withdrawable type marks the existing consent and
 *     writes the consent_withdrawn audit row
 *   - mandatory types (terms, privacy) are rejected by the validator
 *   - withdrawing a type with no active consent is a no-op (no error,
 *     no audit row)
 */
class ConsentWithdrawalTest extends TestCase
{
    use RefreshDatabase;

    public function test_marketing_consent_can_be_withdrawn(): void
    {
        $user = User::factory()->create();
        $consent = Consent::record($user, Consent::TYPE_MARKETING, '1.0');

        $response = $this
            ->actingAs($user)
            ->post('/consent/withdraw', ['type' => Consent::TYPE_MARKETING]);

        $response->assertRedirect();
        $consent->refresh();
        $this->assertFalse($consent->is_granted);
        $this->assertNotNull($consent->withdrawn_at);

        $audit = AuditLog::where('event_type', 'consent_withdrawn')->first();
        $this->assertNotNull($audit);
        $this->assertSame(Consent::TYPE_MARKETING, $audit->metadata['consent_type']);
    }

    public function test_data_processing_consent_can_be_withdrawn(): void
    {
        $user = User::factory()->create();
        Consent::record($user, Consent::TYPE_DATA_PROCESSING, '1.0');

        $response = $this
            ->actingAs($user)
            ->post('/consent/withdraw', ['type' => Consent::TYPE_DATA_PROCESSING]);

        $response->assertRedirect();
        $this->assertFalse(Consent::hasValidConsent($user, Consent::TYPE_DATA_PROCESSING));
    }

    public function test_third_party_sharing_consent_can_be_withdrawn(): void
    {
        $user = User::factory()->create();
        Consent::record($user, Consent::TYPE_THIRD_PARTY_SHARING, '1.0');

        $response = $this
            ->actingAs($user)
            ->post('/consent/withdraw', ['type' => Consent::TYPE_THIRD_PARTY_SHARING]);

        $response->assertRedirect();
        $this->assertFalse(Consent::hasValidConsent($user, Consent::TYPE_THIRD_PARTY_SHARING));
    }

    public function test_terms_consent_cannot_be_withdrawn_via_this_route(): void
    {
        $user = User::factory()->create();
        Consent::record($user, Consent::TYPE_TERMS, '1.0');

        $response = $this
            ->actingAs($user)
            ->post('/consent/withdraw', ['type' => Consent::TYPE_TERMS]);

        $response->assertSessionHasErrors('type');
        $this->assertTrue(Consent::hasValidConsent($user, Consent::TYPE_TERMS));
    }

    public function test_privacy_consent_cannot_be_withdrawn_via_this_route(): void
    {
        $user = User::factory()->create();
        Consent::record($user, Consent::TYPE_PRIVACY, '1.0');

        $response = $this
            ->actingAs($user)
            ->post('/consent/withdraw', ['type' => Consent::TYPE_PRIVACY]);

        $response->assertSessionHasErrors('type');
        $this->assertTrue(Consent::hasValidConsent($user, Consent::TYPE_PRIVACY));
    }

    public function test_unknown_consent_type_is_rejected(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->post('/consent/withdraw', ['type' => 'invented_type_xyz']);

        $response->assertSessionHasErrors('type');
    }

    public function test_withdraw_without_active_consent_is_a_no_op(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->post('/consent/withdraw', ['type' => Consent::TYPE_MARKETING]);

        $response->assertRedirect();
        $this->assertSame(0, AuditLog::where('event_type', 'consent_withdrawn')->count());
    }

    public function test_withdrawable_types_list_includes_known_consent_categories(): void
    {
        $this->assertContains(Consent::TYPE_MARKETING, ConsentController::WITHDRAWABLE_CONSENT_TYPES);
        $this->assertContains(Consent::TYPE_DATA_PROCESSING, ConsentController::WITHDRAWABLE_CONSENT_TYPES);
        $this->assertContains(Consent::TYPE_THIRD_PARTY_SHARING, ConsentController::WITHDRAWABLE_CONSENT_TYPES);
        $this->assertNotContains(Consent::TYPE_TERMS, ConsentController::WITHDRAWABLE_CONSENT_TYPES);
        $this->assertNotContains(Consent::TYPE_PRIVACY, ConsentController::WITHDRAWABLE_CONSENT_TYPES);
    }
}
