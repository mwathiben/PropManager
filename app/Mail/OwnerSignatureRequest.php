<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\AgreementSignature;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Slice-2 PR-2.3c: emails a property owner the single-use link to review and
 * e-sign a management agreement (mirrors OwnerInvitation). $afterCommit so it
 * only sends once the Sent transition + invitation row have committed.
 */
class OwnerSignatureRequest extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public AgreementSignature $signature)
    {
        $this->afterCommit = true;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('emails.subjects.agreement_signature_request'),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.agreement-signature-request',
            with: [
                'signerName' => $this->signature->signer_name,
                'agreementTitle' => $this->signature->agreement?->title,
                'signUrl' => route('agreements.sign.show', $this->signature->token),
            ],
        );
    }
}
