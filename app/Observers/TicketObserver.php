<?php

namespace App\Observers;

use App\Jobs\SendNotificationJob;
use App\Models\Ticket;
use App\Models\TicketActivity;
use Illuminate\Support\Facades\Auth;

class TicketObserver
{
    /**
     * Handle the Ticket "creating" event.
     * Auto-assign to building's caretaker and set landlord_id.
     */
    public function creating(Ticket $ticket): void
    {
        $user = Auth::user();

        // Set landlord_id based on user role
        if ($user) {
            if ($user->isLandlord()) {
                $ticket->landlord_id = $user->id;
            } elseif ($user->landlord_id) {
                $ticket->landlord_id = $user->landlord_id;
            }

            // Set reporter if not already set
            if (! $ticket->reporter_id) {
                $ticket->reporter_id = $user->id;
            }
        }

        // Auto-assign to building's caretaker
        if (! $ticket->assigned_to && $ticket->building_id) {
            $building = $ticket->building;
            if ($building && $building->caretaker_id) {
                $ticket->assigned_to = $building->caretaker_id;
            }
        }
    }

    /**
     * Handle the Ticket "created" event.
     * Log activity and send notifications.
     */
    public function created(Ticket $ticket): void
    {
        // Log the creation activity
        $ticket->logActivity(
            TicketActivity::ACTION_CREATED,
            null,
            null,
            "Ticket created: {$ticket->title}",
            $ticket->reporter_id
        );

        // If auto-assigned, log the assignment
        if ($ticket->assigned_to) {
            $assignee = $ticket->assignee;
            $ticket->logActivity(
                TicketActivity::ACTION_ASSIGNED,
                null,
                $assignee?->name,
                "Auto-assigned to building caretaker: {$assignee?->name}",
                null // System action
            );
        }

        // Notify landlord about new ticket
        if ($ticket->landlord_id) {
            $this->notifyNewTicket($ticket, $ticket->landlord_id);
        }

        // Notify assigned caretaker
        if ($ticket->assigned_to) {
            $this->notifyAssignment($ticket, $ticket->assigned_to);
        }
    }

    /**
     * Handle the Ticket "updated" event.
     * Log status changes and assignments, send notifications.
     */
    public function updated(Ticket $ticket): void
    {
        // Check for status change
        if ($ticket->wasChanged('status')) {
            $oldStatus = $ticket->getOriginal('status');
            $this->handleStatusChange($ticket, $oldStatus, $ticket->status);
        }

        // Check for assignment change
        if ($ticket->wasChanged('assigned_to')) {
            $oldAssignedTo = $ticket->getOriginal('assigned_to');
            $this->handleAssignmentChange($ticket, $oldAssignedTo, $ticket->assigned_to);
        }
    }

    /**
     * Handle status change events.
     */
    protected function handleStatusChange(Ticket $ticket, string $oldStatus, string $newStatus): void
    {
        $action = match ($newStatus) {
            'resolved' => TicketActivity::ACTION_RESOLVED,
            'closed' => TicketActivity::ACTION_CLOSED,
            default => TicketActivity::ACTION_STATUS_CHANGED,
        };

        $statusLabels = Ticket::statuses();
        $oldLabel = $statusLabels[$oldStatus] ?? $oldStatus;
        $newLabel = $statusLabels[$newStatus] ?? $newStatus;

        $ticket->logActivity(
            $action,
            $oldLabel,
            $newLabel,
            "Status changed from {$oldLabel} to {$newLabel}"
        );

        // Notify reporter about status change
        if ($ticket->reporter_id) {
            $this->notifyStatusChange($ticket, $ticket->reporter_id, $oldStatus, $newStatus);
        }
    }

    /**
     * Handle assignment change events.
     */
    protected function handleAssignmentChange(Ticket $ticket, ?int $oldAssigneeId, ?int $newAssigneeId): void
    {
        $oldAssignee = $oldAssigneeId ? \App\Models\User::find($oldAssigneeId) : null;
        $newAssignee = $newAssigneeId ? \App\Models\User::find($newAssigneeId) : null;

        $ticket->logActivity(
            TicketActivity::ACTION_ASSIGNED,
            $oldAssignee?->name ?? 'Unassigned',
            $newAssignee?->name ?? 'Unassigned',
            $newAssignee
                ? "Assigned to {$newAssignee->name}"
                : 'Unassigned'
        );

        // Notify new assignee
        if ($newAssigneeId) {
            $this->notifyAssignment($ticket, $newAssigneeId);
        }
    }

    /**
     * Send notification for new ticket.
     */
    protected function notifyNewTicket(Ticket $ticket, int $recipientId): void
    {
        $categoryLabel = ucfirst($ticket->category);
        $unitInfo = $ticket->unit ? " - Unit {$ticket->unit->unit_number}" : '';

        SendNotificationJob::dispatch(
            $recipientId,
            'maintenance_notice',
            "New {$categoryLabel}: {$ticket->title}",
            "A new {$ticket->category} has been reported{$unitInfo}.\n\nPriority: ".ucfirst($ticket->priority)."\n\nDescription: ".substr($ticket->description, 0, 200),
            [
                'ticket_id' => $ticket->id,
                'category' => $ticket->category,
                'priority' => $ticket->priority,
            ],
            $ticket->landlord_id
        );
    }

    /**
     * Send notification for ticket assignment.
     */
    protected function notifyAssignment(Ticket $ticket, int $assigneeId): void
    {
        $unitInfo = $ticket->unit ? " - Unit {$ticket->unit->unit_number}" : '';

        SendNotificationJob::dispatch(
            $assigneeId,
            'maintenance_notice',
            "Ticket Assigned: {$ticket->title}",
            "You have been assigned a new ticket{$unitInfo}.\n\nPriority: ".ucfirst($ticket->priority)."\n\nDescription: ".substr($ticket->description, 0, 200),
            [
                'ticket_id' => $ticket->id,
                'category' => $ticket->category,
                'priority' => $ticket->priority,
            ],
            $ticket->landlord_id
        );
    }

    /**
     * Send notification for status change.
     */
    protected function notifyStatusChange(Ticket $ticket, int $reporterId, string $oldStatus, string $newStatus): void
    {
        $statusLabels = Ticket::statuses();
        $newLabel = $statusLabels[$newStatus] ?? $newStatus;

        $subject = match ($newStatus) {
            'acknowledged' => 'Your ticket has been acknowledged',
            'in_progress' => 'Work has started on your ticket',
            'resolved' => 'Your ticket has been resolved',
            'closed' => 'Your ticket has been closed',
            'cancelled' => 'Your ticket has been cancelled',
            default => 'Your ticket status has been updated',
        };

        $message = "Your ticket \"{$ticket->title}\" has been updated.\n\nNew Status: {$newLabel}";

        if ($newStatus === 'resolved' && $ticket->resolution_notes) {
            $message .= "\n\nResolution Notes: {$ticket->resolution_notes}";
        }

        SendNotificationJob::dispatch(
            $reporterId,
            'maintenance_notice',
            $subject,
            $message,
            [
                'ticket_id' => $ticket->id,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
            ],
            $ticket->landlord_id
        );
    }
}
