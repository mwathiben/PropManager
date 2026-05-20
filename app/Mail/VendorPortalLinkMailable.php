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
 * Phase-70 VENDOR-AUTH-3: emails a vendor a signed link into their
 * portal. ShouldQueue + afterCommit + locale-aware, mirroring the
 * Phase-54 VendorAssignmentMailable (vendors are not Users, so locale
 * falls back to the owning landlord's preference).
 */
class VendorPortalLinkMailable extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Vendor $vendor,
        public string $url,
    ) {
        $this->afterCommit = true;
        $this->locale($this->resolveLocale());
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('vendor_portal.email.subject'));
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.vendor.portal-link',
            with: ['vendor' => $this->vendor, 'url' => $this->url],
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
