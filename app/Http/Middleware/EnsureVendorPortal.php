<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Vendor;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Phase-70 VENDOR-AUTH-1: guards the vendor portal. A Vendor is a
 * standalone model (no User row), so the portal identity lives in the
 * session, seeded by a signed magic-link (VendorPortalController::enter).
 *
 * The vendor is re-resolved and re-checked active on EVERY request, so a
 * landlord who deactivates a vendor revokes portal access immediately.
 * The resolved vendor is stashed on the request as `portal_vendor` —
 * controllers read it from there and NEVER from a route/client id, which
 * is the portal's isolation boundary against IDOR.
 */
class EnsureVendorPortal
{
    public function handle(Request $request, Closure $next): Response
    {
        $vendorId = $request->session()->get('vendor_portal_id');

        if ($vendorId === null) {
            abort(403, __('vendor_portal.link_required'));
        }

        $vendor = Vendor::withoutGlobalScopes()
            ->where('id', $vendorId)
            ->where('is_active', true)
            ->first();

        if ($vendor === null) {
            $request->session()->forget('vendor_portal_id');
            abort(403, __('vendor_portal.link_required'));
        }

        $request->attributes->set('portal_vendor', $vendor);

        return $next($request);
    }
}
