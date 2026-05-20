<?php

declare(strict_types=1);

namespace App\Services\Vendors;

use App\Models\Vendor;
use Illuminate\Support\Facades\URL;

/**
 * Phase-70 VENDOR-AUTH-3: single authority for minting a signed vendor
 * portal magic-link. Used by the landlord re-issue action and the
 * `vendor:portal-link` command so the route + TTL are defined once.
 */
class VendorPortalLinkService
{
    public const TTL_DAYS = 7;

    public function issue(Vendor $vendor): string
    {
        return URL::signedRoute(
            'vendor.portal.enter',
            ['vendor' => $vendor->id],
            now()->addDays(self::TTL_DAYS),
        );
    }
}
