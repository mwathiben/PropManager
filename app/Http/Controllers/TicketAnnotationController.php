<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\Ticket;
use App\Services\Tickets\TicketAnnotationService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;

/**
 * Phase-45 TICKET-PHOTOS-1/2: persist annotated copies of
 * maintenance-ticket photo attachments. Tenants own their own
 * tickets; landlords own everything under their landlord_id;
 * super_admin sees all. Caretakers fall under landlord-side access.
 */
class TicketAnnotationController extends Controller
{
    public function __construct(private readonly TicketAnnotationService $annotations)
    {
    }

    public function store(Request $request, Ticket $ticket, Document $document): RedirectResponse
    {
        $this->authorizeForTicket($request, $ticket);

        // Sibling document must belong to the same ticket and must
        // BE an image — annotating a PDF is nonsensical.
        if (
            $document->documentable_id !== $ticket->id
            || $document->documentable_type !== Ticket::class
        ) {
            throw new AuthorizationException('Document does not belong to this ticket.');
        }
        if (! $document->isImage()) {
            throw new AuthorizationException('Only image attachments can be annotated.');
        }
        if ($document->isAnnotation()) {
            throw new AuthorizationException('Cannot annotate an annotation.');
        }

        $validated = $request->validate([
            'image' => ['required', 'string', 'max:8000000'], // ~6MB base64 = ~4.5MB binary
            'annotation_data' => ['required', 'array'],
        ]);

        $this->annotations->storeAnnotation(
            $ticket,
            $document,
            $validated['image'],
            $validated['annotation_data'],
            $request->user(),
        );

        return Redirect::route('tickets.show', $ticket)
            ->with('success', __('tenant.tickets.annotation_saved'));
    }

    private function authorizeForTicket(Request $request, Ticket $ticket): void
    {
        $user = $request->user();

        if ($user->isSuperAdmin()) {
            return;
        }
        // Landlord/caretaker access: same landlord_id as the ticket.
        if ($ticket->landlord_id === $user->landlord_id) {
            return;
        }
        // Tenant access: the ticket's tenant_id matches the user id.
        if ($ticket->tenant_id === $user->id) {
            return;
        }

        throw new AuthorizationException('You may not annotate this ticket.');
    }
}
