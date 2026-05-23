<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Phase-28 TENANT-STATEMENT-3: self-email-me statement mailable.
 *
 * Recipient is constrained to the authenticated tenant by the caller
 * (TenantStatementController::email) — Phase-13 PERSONAL-DATA-1
 * compliance. The subject + body are translated against the tenant's
 * own preferred locale via the HasLocalePreference interface so a
 * Swahili-preferring tenant gets a Swahili email even if the worker
 * processing the queued job is on a different locale.
 */
class TenantStatementMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly User $tenant,
        public readonly array $rows,
        public readonly CarbonImmutable $periodFrom,
        public readonly CarbonImmutable $periodTo,
    ) {}

    public function envelope(): Envelope
    {
        $recipientLocale = $this->tenant instanceof HasLocalePreference
            ? $this->tenant->preferredLocale()
            : app()->getLocale();

        $subject = trans('tenant.statement.email_subject', [
            'from' => $this->periodFrom->toDateString(),
            'to' => $this->periodTo->toDateString(),
        ], $recipientLocale);

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(
            // markdown (not view): the template uses <x-mail::message>/<x-mail::table>,
            // whose `mail` namespace only exists in the markdown render pipeline — a plain
            // view send 500s with "No hint path defined for [mail]".
            markdown: 'emails.tenant.statement',
            with: [
                'tenant' => $this->tenant,
                'rows' => $this->rows,
                'from' => $this->periodFrom,
                'to' => $this->periodTo,
            ],
        );
    }
}
