<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\TicketStatus;
use App\Mail\VendorPortalLinkMailable;
use App\Models\Ticket;
use App\Models\Vendor;
use App\Services\Vendors\VendorPortalLinkService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Phase-70 VENDOR-AUTH: the vendor portal entry/dashboard/logout, plus
 * the landlord-side re-issue of a portal link. The portal vendor is read
 * from the request (set by EnsureVendorPortal), never a client id.
 */
class VendorPortalController extends Controller
{
    /**
     * Signed magic-link target: establishes the portal session. The
     * `signed` middleware has already verified the signature (which covers
     * the vendor id), so binding the Vendor here is safe. No TenantScope
     * applies (no authenticated user on this route).
     */
    public function enter(Request $request, Vendor $vendor): RedirectResponse
    {
        if (! $vendor->is_active) {
            abort(403, __('vendor_portal.link_required'));
        }

        // Regenerate on privilege grant (defends against session fixation —
        // the magic-link could land in a pre-seeded session).
        $request->session()->regenerate();
        $request->session()->put('vendor_portal_id', $vendor->id);

        return redirect()->route('vendor.portal.dashboard');
    }

    public function dashboard(Request $request): Response
    {
        /** @var Vendor $vendor */
        $vendor = $request->attributes->get('portal_vendor');

        $assigned = Ticket::query()->where('vendor_id', $vendor->id);

        return Inertia::render('VendorPortal/Dashboard', [
            'vendor' => ['id' => $vendor->id, 'name' => $vendor->name],
            'stats' => [
                'pending' => (clone $assigned)->where('vendor_status', 'pending')->count(),
                'open' => (clone $assigned)->whereIn('status', [
                    TicketStatus::Open, TicketStatus::Acknowledged, TicketStatus::InProgress,
                ])->count(),
                'overdue' => (clone $assigned)->breachedResolutionSla()->count(),
            ],
        ]);
    }

    public function logout(Request $request): RedirectResponse
    {
        $request->session()->forget('vendor_portal_id');

        return redirect('/')->with('status', __('vendor_portal.logged_out'));
    }

    /**
     * Landlord-side: re-send a fresh portal link to one of their vendors.
     */
    public function reissue(Request $request, Vendor $vendor, VendorPortalLinkService $links): RedirectResponse
    {
        $this->authorize('update', $vendor);

        if (! $vendor->email) {
            return back()->withErrors(['vendor' => __('vendor_portal.no_email')]);
        }

        Mail::to($vendor->email)->queue(new VendorPortalLinkMailable($vendor, $links->issue($vendor)));

        return back()->with('success', __('vendor_portal.link_sent'));
    }
}
