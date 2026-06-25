<?php

declare(strict_types=1);

namespace Tests\Feature\Agreements;

use App\Enums\AgreementSignatureStatus;
use App\Enums\AgreementStatus;
use App\Mail\OwnerSignatureRequest;
use App\Models\AgreementSignature;
use App\Models\ManagementAgreement;
use App\Models\PropertyOwner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Slice-2 PR-2.3c (send half): a manager sends a draft management agreement to
 * the owner for in-house e-signature — Draft -> Sent, a single-use signing
 * invitation is created, and the owner is emailed the link.
 */
class SendAgreementForSignatureTest extends TestCase
{
    use RefreshDatabase;

    private function managerWithDraft(array $ownerAttrs = []): array
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $owner = PropertyOwner::factory()->forLandlord($manager)->create(array_merge([
            'email' => 'owner@example.com',
            'phone' => '254712345678',
        ], $ownerAttrs));
        $agreement = ManagementAgreement::factory()->create([
            'landlord_id' => $manager->id,
            'property_owner_id' => $owner->id,
            'status' => AgreementStatus::Draft,
            'rendered_body' => 'Agreement body',
            'content_hash' => hash('sha256', 'Agreement body'),
        ]);

        return compact('manager', 'owner', 'agreement');
    }

    public function test_manager_sends_a_draft_agreement_for_signature(): void
    {
        Mail::fake();
        ['manager' => $manager, 'owner' => $owner, 'agreement' => $agreement] = $this->managerWithDraft();

        $this->actingAs($manager)
            ->post(route('agreements.send', $agreement))
            ->assertRedirect();

        $this->assertSame(AgreementStatus::Sent, $agreement->fresh()->status);
        $this->assertNotNull($agreement->fresh()->sent_at);

        $signature = AgreementSignature::where('management_agreement_id', $agreement->id)->first();
        $this->assertNotNull($signature);
        $this->assertSame(AgreementSignatureStatus::Pending, $signature->status);
        $this->assertSame($owner->name, $signature->signer_name);
        $this->assertSame('owner@example.com', $signature->signer_email);
        $this->assertSame(64, strlen($signature->token));

        Mail::assertQueued(OwnerSignatureRequest::class);
    }

    public function test_cannot_send_a_non_draft_agreement(): void
    {
        Mail::fake();
        ['manager' => $manager, 'agreement' => $agreement] = $this->managerWithDraft();
        $agreement->forceFill(['status' => AgreementStatus::Active])->save();

        $this->actingAs($manager)
            ->post(route('agreements.send', $agreement))
            ->assertSessionHasErrors();

        $this->assertDatabaseCount('agreement_signatures', 0);
        Mail::assertNothingQueued();
    }

    public function test_send_requires_owner_email(): void
    {
        Mail::fake();
        ['manager' => $manager, 'agreement' => $agreement] = $this->managerWithDraft(['email' => null]);

        $this->actingAs($manager)
            ->post(route('agreements.send', $agreement))
            ->assertSessionHasErrors();

        $this->assertSame(AgreementStatus::Draft, $agreement->fresh()->status);
        $this->assertDatabaseCount('agreement_signatures', 0);
        Mail::assertNothingQueued();
    }

    public function test_cross_tenant_manager_cannot_send_another_managers_agreement(): void
    {
        Mail::fake();
        ['agreement' => $agreement] = $this->managerWithDraft();
        $intruder = User::factory()->create(['role' => 'manager']);

        // TenantScope hides the agreement from the intruder's route binding (404);
        // were it resolvable, the policy would 403. Either way: no mutation, no mail.
        $status = $this->actingAs($intruder)
            ->post(route('agreements.send', $agreement))->getStatusCode();
        $this->assertContains($status, [403, 404]);

        $this->assertSame(AgreementStatus::Draft, $agreement->fresh()->status);
        Mail::assertNothingQueued();
    }

    public function test_a_landlord_cannot_send_agreements(): void
    {
        Mail::fake();
        ['agreement' => $agreement] = $this->managerWithDraft();
        $landlord = User::factory()->create(['role' => 'landlord']);

        $status = $this->actingAs($landlord)
            ->post(route('agreements.send', $agreement))->getStatusCode();
        $this->assertContains($status, [403, 404]);

        $this->assertSame(AgreementStatus::Draft, $agreement->fresh()->status);
        Mail::assertNothingQueued();
    }
}
