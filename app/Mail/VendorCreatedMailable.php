<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Vendor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Phase-54 VENDOR-ONBOARDING-1: welcome a newly-added vendor with the
 * signed-URL profile-completion link. Locale falls back to the
 * landlord's preferredLocale, then config('app.fallback_locale').
 */
class VendorCreatedMailable extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Vendor $vendor,
        public string $profileUrl,
    ) {
        $this->afterCommit = true;
        $this->locale($this->resolveLocale());
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('maintenance.vendor_onboarding.subject', [
                'landlord' => $this->vendor->landlord?->name ?? config('app.name'),
            ]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.maintenance.vendor-created',
            text: 'emails.maintenance.vendor-created-text',
            with: [
                'vendor' => $this->vendor,
                'profileUrl' => $this->profileUrl,
            ],
        );
    }

    private function resolveLocale(): string
    {
        $landlord = $this->vendor->landlord;
        if ($landlord && method_exists($landlord, 'preferredLocale')) {
            $locale = $landlord->preferredLocale();
            if (is_string($locale) && $locale !== '') {
                return $locale;
            }
        }

        return (string) config('app.fallback_locale', 'en');
    }
}
