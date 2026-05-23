<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\ScheduledReport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Phase-27 BI-DELIVERY-2: scheduled report email.
 *
 * Recipient localisation rides Phase-24 HasLocalePreference if a
 * matching User record exists for the recipient_email; otherwise
 * defaults to config('app.fallback_locale').
 */
class ScheduledReportDelivery extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public ScheduledReport $schedule,
        public string $xlsxPath,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('emails.subjects.scheduled_report', [
                'name' => $this->schedule->savedReport->name,
            ]),
        );
    }

    public function content(): Content
    {
        return new Content(
            // markdown (not view): the template uses @component('mail::message'/'mail::button'),
            // whose `mail` namespace only exists in the markdown render pipeline — a plain
            // view send 500s with "No hint path defined for [mail]".
            markdown: 'emails.scheduled-report',
            with: [
                'schedule' => $this->schedule,
                'reportName' => $this->schedule->savedReport->name,
                'cadence' => $this->schedule->cadence,
            ],
        );
    }

    /**
     * @return list<\Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [
            Attachment::fromPath($this->xlsxPath)
                ->as(\Illuminate\Support\Str::slug($this->schedule->savedReport->name).'.xlsx')
                ->withMime('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'),
        ];
    }
}
