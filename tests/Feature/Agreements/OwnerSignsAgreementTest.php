<?php

declare(strict_types=1);

namespace Tests\Feature\Agreements;

use App\Enums\AgreementSignatureStatus;
use App\Enums\AgreementStatus;
use App\Enums\ManagementFeeType;
use App\Models\AgreementSignature;
use App\Models\Clause;
use App\Models\ManagementAgreement;
use App\Models\PropertyOwner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Slice-2 PR-2.3c (sign half): the owner opens the token link, verifies an SMS
 * OTP, and signs — recording tamper-evident evidence and driving the agreement
 * Sent -> Signed -> active with the governed fee LOCKED. This is where the slice
 * goes live, so the money + evidence assertions are explicit.
 */
class OwnerSignsAgreementTest extends TestCase
{
    use RefreshDatabase;

    private function sentAgreementWithInvitation(): array
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $owner = PropertyOwner::factory()->forLandlord($manager)->create([
            'email' => 'owner@example.com',
            'phone' => '254712345678',
            'management_fee_type' => ManagementFeeType::None,
        ]);
        $agreement = ManagementAgreement::factory()->create([
            'landlord_id' => $manager->id,
            'property_owner_id' => $owner->id,
            'status' => AgreementStatus::Sent,
            'rendered_body' => 'The agreement text',
            'content_hash' => hash('sha256', 'The agreement text'),
            'sent_at' => now(),
        ]);
        $agreement->agreementClauses()->create([
            'clause_id' => Clause::factory()->managementFee()->create()->id,
            'params' => ['type' => 'percentage', 'value' => 8, 'base' => 'collected'],
            'position' => 0,
        ]);
        $signature = AgreementSignature::factory()->for($agreement, 'agreement')->create([
            'landlord_id' => $manager->id,
            'signer_phone' => '254712345678',
            'status' => AgreementSignatureStatus::Pending,
        ]);

        return compact('manager', 'owner', 'agreement', 'signature');
    }

    private function requestedCode(string $token): string
    {
        $this->post(route('agreements.sign.otp', $token))->assertRedirect();
        $code = Cache::get("otp:agreement-sign:{$token}");
        $this->assertNotNull($code, 'an OTP should be cached after requesting one');

        return $code;
    }

    public function test_owner_can_view_the_agreement_by_token(): void
    {
        ['agreement' => $agreement, 'signature' => $signature] = $this->sentAgreementWithInvitation();

        $this->get(route('agreements.sign.show', $signature->token))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Agreements/Sign')
                ->where('signed', false)
                ->where('agreement.title', $agreement->title)
                ->where('agreement.rendered_body', 'The agreement text'));
    }

    public function test_an_unknown_token_404s(): void
    {
        $this->get(route('agreements.sign.show', str_repeat('x', 64)))->assertNotFound();
    }

    public function test_valid_otp_signs_activates_and_locks_the_fee(): void
    {
        ['owner' => $owner, 'agreement' => $agreement, 'signature' => $signature] = $this->sentAgreementWithInvitation();

        $code = $this->requestedCode($signature->token);

        $this->post(route('agreements.sign', $signature->token), [
            'code' => $code,
            'content_hash' => $agreement->content_hash,
            'agree' => true,
        ])->assertRedirect();

        $this->assertSame(AgreementStatus::Active, $agreement->fresh()->status);
        $this->assertNotNull($agreement->fresh()->signed_at);

        $owner->refresh();
        $this->assertSame(ManagementFeeType::Percentage, $owner->management_fee_type);
        $this->assertEquals(8.0, (float) $owner->management_fee_value);
        $this->assertNotNull($owner->management_fee_locked_at, 'fee must be locked after signing');

        $sig = $signature->fresh();
        $this->assertSame(AgreementSignatureStatus::Signed, $sig->status);
        $this->assertNotNull($sig->signed_at);
        $this->assertNotNull($sig->otp_verified_at);
        $this->assertSame($agreement->content_hash, $sig->content_hash);
        $this->assertNotNull($sig->signed_ip);
    }

    public function test_wrong_otp_is_rejected_and_nothing_activates(): void
    {
        ['owner' => $owner, 'agreement' => $agreement, 'signature' => $signature] = $this->sentAgreementWithInvitation();

        $this->requestedCode($signature->token);

        $this->from(route('agreements.sign.show', $signature->token))
            ->post(route('agreements.sign', $signature->token), [
                'code' => '000000',
                'content_hash' => $agreement->content_hash,
                'agree' => true,
            ])
            ->assertSessionHasErrors('code');

        $this->assertSame(AgreementStatus::Sent, $agreement->fresh()->status);
        $this->assertNull($owner->fresh()->management_fee_locked_at);
        $this->assertSame(AgreementSignatureStatus::Pending, $signature->fresh()->status);
    }

    public function test_a_changed_snapshot_is_rejected(): void
    {
        ['agreement' => $agreement, 'signature' => $signature] = $this->sentAgreementWithInvitation();

        $code = $this->requestedCode($signature->token);

        $this->from(route('agreements.sign.show', $signature->token))
            ->post(route('agreements.sign', $signature->token), [
                'code' => $code,
                'content_hash' => 'stale-hash',
                'agree' => true,
            ])
            ->assertSessionHasErrors('content_hash');

        $this->assertSame(AgreementStatus::Sent, $agreement->fresh()->status);
    }

    public function test_a_signed_invitation_shows_thanks_and_cannot_be_reused(): void
    {
        ['agreement' => $agreement, 'signature' => $signature] = $this->sentAgreementWithInvitation();
        $code = $this->requestedCode($signature->token);
        $this->post(route('agreements.sign', $signature->token), [
            'code' => $code,
            'content_hash' => $agreement->content_hash,
            'agree' => true,
        ])->assertRedirect();

        $this->get(route('agreements.sign.show', $signature->token))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Agreements/Sign')->where('signed', true));

        // A replay against the consumed (now non-pending) invitation is refused.
        $this->post(route('agreements.sign', $signature->token), [
            'code' => $code,
            'content_hash' => $agreement->content_hash,
            'agree' => true,
        ])->assertNotFound();
    }
}
