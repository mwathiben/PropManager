<?php

declare(strict_types=1);

namespace App\Services\Agreements;

use App\Enums\AgreementSignatureStatus;
use App\Enums\AgreementStatus;
use App\Exceptions\DataIntegrityException;
use App\Mail\OwnerSignatureRequest;
use App\Models\AgreementSignature;
use App\Models\ManagementAgreement;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * Slice-2 PR-2.3c: send a draft management agreement to its owner for in-house
 * e-signature. Transitions Draft -> Sent, mints a single-use signing invitation
 * (the token the owner signs through — PropertyOwner is a contact, not a login),
 * and emails the owner the link. Fail-closed: a non-draft agreement, or an owner
 * missing the email + phone the e-sign flow needs, is refused with no mutation.
 */
class AgreementSender
{
    public function send(ManagementAgreement $agreement): AgreementSignature
    {
        if (! $agreement->status->canTransitionTo(AgreementStatus::Sent)) {
            throw new DataIntegrityException(
                "A {$agreement->status->value} agreement cannot be sent for signature.",
                'agreement.not_sendable',
            );
        }

        $owner = $agreement->propertyOwner;
        if ($owner === null || blank($owner->email) || blank($owner->phone)) {
            throw new DataIntegrityException(
                'The owner needs both an email address and a phone number before the agreement can be sent for signature.',
                'agreement.owner_contact_missing',
            );
        }

        if (blank($agreement->rendered_body)) {
            $agreement->recomputeRenderedBody();
        }

        return DB::transaction(function () use ($agreement, $owner): AgreementSignature {
            $signature = AgreementSignature::create([
                'management_agreement_id' => $agreement->id,
                'landlord_id' => $agreement->landlord_id,
                'token' => AgreementSignature::newToken(),
                'status' => AgreementSignatureStatus::Pending,
                'signer_name' => $owner->name,
                'signer_email' => $owner->email,
                'signer_phone' => $owner->phone,
            ]);

            $agreement->forceFill([
                'status' => AgreementStatus::Sent,
                'sent_at' => now(),
            ])->save();

            Mail::to($owner->email)->queue(new OwnerSignatureRequest($signature));

            return $signature;
        });
    }
}
