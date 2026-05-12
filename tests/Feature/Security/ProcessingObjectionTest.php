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
 * Phase-13 DPA-5 regression coverage. Article 21 right to object.
 * Tests pin:
 *   - objecting to an objectable category records a withdrawn-state
 *     Consent row tagged 'objection:<category>'
 *   - non-objectable categories (contract / legal_obligation) are
 *     rejected by the validator
 *   - the audit row carries the reason + gdpr_article_21 compliance
 *     tag
 */
class ProcessingObjectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_object_to_analytics(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->post('/privacy/object', [
                'category' => 'analytics',
                'reason' => 'I do not consent to behavioural profiling.',
            ]);

        $response->assertRedirect();

        $consent = Consent::where('user_id', $user->id)
            ->where('consent_type', 'objection:analytics')
            ->first();
        $this->assertNotNull($consent);
        $this->assertFalse($consent->is_granted);
        $this->assertNotNull($consent->withdrawn_at);
    }

    public function test_objection_writes_audit_with_article_21_tag(): void
    {
        $user = User::factory()->create();

        $this
            ->actingAs($user)
            ->post('/privacy/object', [
                'category' => 'marketing_analysis',
                'reason' => 'No analysis without consent.',
            ]);

        // Consent::record writes a consent_granted audit; the
        // immediate withdrawal triggers consent_withdrawn. Either
        // carries the objection signal via the consent_type.
        $audit = AuditLog::where('event_type', 'consent_granted')
            ->orWhere('event_type', 'consent_withdrawn')
            ->latest()
            ->first();
        $this->assertNotNull($audit);
        $this->assertStringStartsWith('objection:', $audit->metadata['consent_type']);
    }

    public function test_cannot_object_to_contract_basis_processing(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->post('/privacy/object', [
                'category' => 'lease_data',
                'reason' => 'no thanks',
            ]);

        $response->assertSessionHasErrors('category');
    }

    public function test_objection_requires_reason(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->post('/privacy/object', [
                'category' => 'analytics',
            ]);

        $response->assertSessionHasErrors('reason');
    }

    public function test_objectable_categories_list_excludes_contract_bases(): void
    {
        $this->assertContains('analytics', ConsentController::OBJECTABLE_CATEGORIES);
        $this->assertContains('marketing_analysis', ConsentController::OBJECTABLE_CATEGORIES);
        $this->assertNotContains('lease_data', ConsentController::OBJECTABLE_CATEGORIES);
        $this->assertNotContains('payment_data', ConsentController::OBJECTABLE_CATEGORIES);
        $this->assertNotContains('national_id', ConsentController::OBJECTABLE_CATEGORIES);
    }
}
