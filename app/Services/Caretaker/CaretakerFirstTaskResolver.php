<?php

declare(strict_types=1);

namespace App\Services\Caretaker;

use App\Models\CaretakerAssignment;
use App\Models\Ticket;
use App\Models\User;

/**
 * Phase-77 CARETAKER-CONTEXT-3: the caretaker's next actionable item after
 * onboarding — the oldest open ticket on a building they have ACCEPTED, else the
 * maintenance hub. Strictly scoped to the caretaker's accepted buildings (a
 * ticket on another landlord's building can never be returned). Shared by the
 * orientation step + the invitation deep-link.
 */
class CaretakerFirstTaskResolver
{
    public function resolve(User $caretaker): string
    {
        $buildingIds = CaretakerAssignment::query()
            ->where('caretaker_id', $caretaker->id)
            ->where('status', CaretakerAssignment::STATUS_ACCEPTED)
            ->pluck('building_id');

        if ($buildingIds->isNotEmpty()) {
            $ticket = Ticket::query()
                ->whereIn('building_id', $buildingIds)
                ->where('landlord_id', $caretaker->landlord_id)
                ->open()
                ->orderBy('created_at')
                ->orderBy('id')
                ->first(['id']);

            if ($ticket !== null) {
                return route('tickets.show', $ticket->id);
            }
        }

        return route('maintenance.hub');
    }
}
