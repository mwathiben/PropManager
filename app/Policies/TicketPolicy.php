<?php

namespace App\Policies;

use App\Models\Ticket;
use App\Models\User;

class TicketPolicy
{
    /**
     * Perform pre-authorization checks.
     */
    public function before(User $user, string $ability): ?bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return null;
    }

    /**
     * Determine whether the user can view any tickets.
     */
    public function viewAny(User $user): bool
    {
        return true; // All authenticated users can view tickets (scoped by TenantScope)
    }

    /**
     * Determine whether the user can view the ticket.
     */
    public function view(User $user, Ticket $ticket): bool
    {
        if ($user->isLandlord()) {
            return $ticket->landlord_id === $user->id;
        }

        if ($user->isCaretaker()) {
            return $ticket->landlord_id === $user->landlord_id
                || $ticket->assigned_to === $user->id;
        }

        if ($user->isTenant()) {
            return $ticket->reporter_id === $user->id;
        }

        return false;
    }

    /**
     * Determine whether the user can create tickets.
     */
    public function create(User $user): bool
    {
        return true; // All authenticated users can create tickets
    }

    /**
     * Determine whether the user can update the ticket.
     */
    public function update(User $user, Ticket $ticket): bool
    {
        if ($user->isLandlord()) {
            return $ticket->landlord_id === $user->id;
        }

        if ($user->isCaretaker()) {
            return $ticket->assigned_to === $user->id
                || $ticket->landlord_id === $user->landlord_id;
        }

        // Tenants can only update their own open tickets
        if ($user->isTenant()) {
            return $ticket->reporter_id === $user->id
                && in_array($ticket->status, ['open', 'in_progress']);
        }

        return false;
    }

    /**
     * Determine whether the user can delete the ticket.
     */
    public function delete(User $user, Ticket $ticket): bool
    {
        return $user->isLandlord() && $ticket->landlord_id === $user->id;
    }

    /**
     * Determine whether the user can assign the ticket.
     */
    public function assign(User $user, Ticket $ticket): bool
    {
        if ($user->isLandlord()) {
            return $ticket->landlord_id === $user->id;
        }

        return false;
    }

    /**
     * Determine whether the user can resolve the ticket.
     */
    public function resolve(User $user, Ticket $ticket): bool
    {
        if ($user->isLandlord()) {
            return $ticket->landlord_id === $user->id;
        }

        if ($user->isCaretaker()) {
            return $ticket->assigned_to === $user->id;
        }

        return false;
    }

    /**
     * Determine whether the user can close the ticket.
     */
    public function close(User $user, Ticket $ticket): bool
    {
        if ($user->isLandlord()) {
            return $ticket->landlord_id === $user->id;
        }

        // Reporter can close their own resolved tickets
        if ($ticket->reporter_id === $user->id && $ticket->status === 'resolved') {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can comment on the ticket.
     */
    public function comment(User $user, Ticket $ticket): bool
    {
        return $this->view($user, $ticket);
    }
}
