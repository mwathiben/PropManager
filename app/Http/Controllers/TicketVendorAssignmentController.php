<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\Vendor;
use App\Services\Maintenance\VendorAssignmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * Phase-49 VENDOR-MARKETPLACE-3: landlord assigns an external Vendor
 * to a Ticket. The Vendor must belong to the landlord (rule:exists with
 * landlord_id scope) — the service then DB::transaction-wraps the write
 * + TicketActivity log + TicketAssignedToVendor event dispatch.
 */
class TicketVendorAssignmentController extends Controller
{
    public function __construct(
        protected VendorAssignmentService $service,
    ) {
    }

    public function store(Request $request, Ticket $ticket): RedirectResponse
    {
        $landlordId = Auth::user()->isLandlord() ? Auth::id() : Auth::user()->landlord_id;

        $validated = $request->validate([
            'vendor_id' => [
                'required',
                Rule::exists('vendors', 'id')->where('landlord_id', $landlordId),
            ],
            'note' => 'nullable|string|max:500',
        ]);

        if ($ticket->landlord_id !== $landlordId) {
            abort(403);
        }

        $vendor = Vendor::findOrFail($validated['vendor_id']);
        $this->service->assign($ticket, $vendor, $validated['note'] ?? null);

        return back()->with('success', 'Vendor assigned.');
    }
}
