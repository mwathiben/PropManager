<?php

declare(strict_types=1);

namespace App\Observers;

use App\Mail\VendorCreatedMailable;
use App\Models\Vendor;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

/**
 * Phase-54 VENDOR-ONBOARDING-1: send the vendor a welcome email with a
 * 7-day signed URL to /v/profile/{vendor} so they can fill in their
 * specialties + service-area without needing a user account.
 *
 * Vendor is standalone (no User row, no auth). The signed URL IS the
 * auth — Laravel's 'signed' middleware verifies on each request.
 *
 * Skipped silently when vendor.email is null (phone-only vendors are
 * legacy data; landlord can fill in those by editing the vendor row
 * themselves).
 */
class VendorObserver
{
    public function created(Vendor $vendor): void
    {
        if (! $vendor->email) {
            return;
        }

        try {
            $url = URL::signedRoute(
                'vendor.profile.edit',
                ['vendor' => $vendor->id],
                now()->addDays(7),
            );

            Mail::to($vendor->email)->queue(new VendorCreatedMailable($vendor, $url));
        } catch (\Throwable $e) {
            // Vendor creation must not break if mail wiring is misconfigured.
            Log::warning('VendorObserver welcome dispatch failed', [
                'vendor_id' => $vendor->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
