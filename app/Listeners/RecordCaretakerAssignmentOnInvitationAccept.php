<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\InvitationAccepted;
use App\Models\Building;
use App\Services\Caretaker\CaretakerAssignmentService;

/**
 * Phase-48 CARETAKER-ASSIGNMENT-UX-2: when a caretaker accepts a landlord's
 * Invitation tied to a property, mint pending CaretakerAssignment rows for
 * every building under that property. The wizard's step 2 then surfaces
 * those rows for accept/decline.
 *
 * No-op when the invitation isn't for a caretaker, or has no property.
 */
class RecordCaretakerAssignmentOnInvitationAccept
{
    public function __construct(
        protected CaretakerAssignmentService $service,
    ) {
    }

    public function handle(InvitationAccepted $event): void
    {
        $invitation = $event->invitation;
        $user = $event->user;

        if (($invitation->role ?? null) !== 'caretaker') {
            return;
        }

        if ($invitation->property_id === null) {
            return;
        }

        $buildings = Building::where('property_id', $invitation->property_id)->get();

        foreach ($buildings as $building) {
            $this->service->recordAssignment($user, $building);
        }
    }
}
