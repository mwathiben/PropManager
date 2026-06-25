<?php

declare(strict_types=1);

namespace Tests\Feature\Agreements;

use App\Enums\AgreementSignatureStatus;
use App\Enums\AgreementStatus;
use App\Jobs\FinalizeDocumensoSignatureJob;
use App\Models\AgreementSignature;
use App\Models\ManagementAgreement;
use App\Models\PropertyOwner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Slice-2 PR-2.4b: the Documenso completion webhook is the authoritative trigger
 * that seals the evidence + activates the fee. It is unauthenticated, gated only
 * by the shared secret, so its security + idempotency are exercised directly.
 */
class DocumensoWebhookTest extends TestCase
{
    use RefreshDatabase;

    private string $secret = 'whk_secret_test';

    protected function setUp(): void
    {
        parent::setUp();
        config(['documenso.webhook_secret' => $this->secret]);
    }

    private function pendingSignature(int $documentId = 42): AgreementSignature
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $owner = PropertyOwner::factory()->create(['landlord_id' => $manager->id]);
        $agreement = ManagementAgreement::factory()->create([
            'landlord_id' => $manager->id,
            'property_owner_id' => $owner->id,
            'status' => AgreementStatus::Sent,
        ]);

        return AgreementSignature::factory()->create([
            'management_agreement_id' => $agreement->id,
            'landlord_id' => $manager->id,
            'status' => AgreementSignatureStatus::Pending,
            'documenso_document_id' => $documentId,
        ]);
    }

    private function postWebhook(array $body, ?string $secret = null): TestResponse
    {
        return $this->withHeaders(['X-Documenso-Secret' => $secret ?? $this->secret])
            ->postJson('/api/webhooks/documenso', $body);
    }

    public function test_completed_event_dispatches_finalize_job(): void
    {
        Bus::fake();
        $signature = $this->pendingSignature(42);

        $this->postWebhook([
            'event' => 'DOCUMENT_COMPLETED',
            'payload' => ['id' => 42, 'envelopeId' => 'env_abc'],
        ])->assertOk();

        Bus::assertDispatched(
            FinalizeDocumensoSignatureJob::class,
            fn (FinalizeDocumensoSignatureJob $job) => $job->signatureId === $signature->id
                && $job->documentId === 42
                && $job->envelopeId === 'env_abc',
        );
    }

    public function test_wrong_secret_is_rejected(): void
    {
        Bus::fake();
        $this->pendingSignature(42);

        $this->postWebhook(['event' => 'DOCUMENT_COMPLETED', 'payload' => ['id' => 42]], 'wrong-secret')
            ->assertUnauthorized();

        Bus::assertNotDispatched(FinalizeDocumensoSignatureJob::class);
    }

    public function test_missing_secret_is_rejected(): void
    {
        Bus::fake();
        $this->pendingSignature(42);

        $this->postJson('/api/webhooks/documenso', ['event' => 'DOCUMENT_COMPLETED', 'payload' => ['id' => 42]])
            ->assertUnauthorized();

        Bus::assertNotDispatched(FinalizeDocumensoSignatureJob::class);
    }

    public function test_non_completed_events_are_ignored(): void
    {
        Bus::fake();
        $this->pendingSignature(42);

        $this->postWebhook(['event' => 'DOCUMENT_OPENED', 'payload' => ['id' => 42]])->assertOk();

        Bus::assertNotDispatched(FinalizeDocumensoSignatureJob::class);
    }

    public function test_unknown_document_is_acked_without_dispatch(): void
    {
        Bus::fake();
        $this->pendingSignature(42);

        $this->postWebhook(['event' => 'DOCUMENT_COMPLETED', 'payload' => ['id' => 999]])->assertOk();

        Bus::assertNotDispatched(FinalizeDocumensoSignatureJob::class);
    }

    public function test_already_signed_signature_is_idempotent(): void
    {
        Bus::fake();
        $signature = $this->pendingSignature(42);
        $signature->update(['status' => AgreementSignatureStatus::Signed]);

        $this->postWebhook(['event' => 'DOCUMENT_COMPLETED', 'payload' => ['id' => 42]])->assertOk();

        Bus::assertNotDispatched(FinalizeDocumensoSignatureJob::class);
    }

    public function test_declined_signature_is_not_reprocessed(): void
    {
        Bus::fake();
        $signature = $this->pendingSignature(42);
        $signature->update(['status' => AgreementSignatureStatus::Declined]);

        // A late completion must not resurrect a declined signature.
        $this->postWebhook(['event' => 'DOCUMENT_COMPLETED', 'payload' => ['id' => 42]])->assertOk();

        Bus::assertNotDispatched(FinalizeDocumensoSignatureJob::class);
    }

    public function test_webhook_route_is_rate_limited(): void
    {
        // The shared secret is the sole auth gate; a throttle caps brute-force.
        $route = collect(app('router')->getRoutes()->getRoutes())
            ->first(fn ($r) => $r->uri() === 'api/webhooks/documenso');

        $this->assertNotNull($route, 'the documenso webhook route must exist');
        $this->assertContains('throttle:60,1', $route->middleware());
    }
}
