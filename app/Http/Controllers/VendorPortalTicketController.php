<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\Vendor;
use App\Services\Maintenance\VendorAssignmentResponseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Phase-70 TICKET-INBOX-2: the vendor's job inbox. Every query scopes to
 * the SESSION vendor (request->attributes('portal_vendor')); accept/
 * decline re-verify the ticket belongs to that vendor BEFORE acting, so a
 * vendor cannot touch another vendor's ticket even with a guessed id.
 */
class VendorPortalTicketController extends Controller
{
    public function __construct(private readonly VendorAssignmentResponseService $responses) {}

    public function index(Request $request): Response
    {
        $vendor = $this->vendor($request);

        $tickets = Ticket::query()
            ->where('vendor_id', $vendor->id)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Ticket $t) => [
                'id' => $t->id,
                'title' => $t->title,
                'status' => $t->status->value,
                'priority' => $t->priority,
                'location' => $t->location,
                'vendor_status' => $t->vendor_status,
                'resolution_due_at' => $t->resolution_due_at?->toIso8601String(),
                'created_at' => $t->created_at?->toIso8601String(),
            ]);

        return Inertia::render('VendorPortal/Inbox', [
            'vendor' => ['id' => $vendor->id, 'name' => $vendor->name],
            'tickets' => $tickets,
        ]);
    }

    public function accept(Request $request, Ticket $ticket): RedirectResponse
    {
        $this->assertOwnedAndPending($request, $ticket);
        $this->responses->accept($ticket);

        return back()->with('success', __('vendor_portal.inbox.accepted'));
    }

    public function decline(Request $request, Ticket $ticket): RedirectResponse
    {
        $this->assertOwnedAndPending($request, $ticket);
        $reason = $request->validate(['reason' => ['nullable', 'string', 'max:500']])['reason'] ?? null;
        $this->responses->decline($ticket, $reason);

        return back()->with('success', __('vendor_portal.inbox.declined'));
    }

    private function vendor(Request $request): Vendor
    {
        /** @var Vendor $vendor */
        $vendor = $request->attributes->get('portal_vendor');

        return $vendor;
    }

    private function assertOwnedAndPending(Request $request, Ticket $ticket): void
    {
        abort_unless($ticket->vendor_id === $this->vendor($request)->id, 403);
        abort_unless($ticket->vendor_status === 'pending', 422, __('vendor_portal.inbox.already_responded'));
        // Can't accept/decline a ticket the landlord already resolved/closed/
        // cancelled (vendor_status is independent of lifecycle status).
        abort_unless($ticket->isOpen(), 422, __('vendor_portal.inbox.already_responded'));
    }
}
