<?php

namespace App\Observers;

use App\Enums\TicketStatus;
use App\Jobs\SendNotificationJob;
use App\Models\Ticket;
use App\Models\TicketActivity;
use App\Models\User;
use App\Services\Maintenance\SlaDefinitionService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TicketObserver
{
    /**
     * Handle the Ticket "creating" event.
     * Auto-assign to building's caretaker and set landlord_id.
     */
    public function creating(Ticket $ticket): void
    {
        $this->stampOwnershipFields($ticket);
        $this->autoAssignToCaretaker($ticket);
        $this->stampSlaDueDates($ticket);
    }

    /**
     * Handle the Ticket "created" event.
     * Log activity and send notifications.
     */
    public function created(Ticket $ticket): void
    {
        DB::transaction(function () use ($ticket) {
            $this->logCreatedActivities($ticket);
        });

        DB::afterCommit(function () use ($ticket) {
            $this->dispatchCreatedNotifications($ticket);
        });
    }

    /**
     * Stamp landlord_id and reporter_id on the ticket being created.
     */
    private function stampOwnershipFields(Ticket $ticket): void
    {
        $user = Auth::user();

        if (! $user) {
            return;
        }

        if ($user->isScopeOwner()) {
            $ticket->landlord_id = $user->id;
        } elseif ($user->landlord_id) {
            $ticket->landlord_id = $user->landlord_id;
        }

        if (! $ticket->reporter_id) {
            $ticket->reporter_id = $user->id;
        }
    }

    /**
     * Auto-assign the ticket to the building's caretaker if unassigned.
     */
    private function autoAssignToCaretaker(Ticket $ticket): void
    {
        if ($ticket->assigned_to || ! $ticket->building_id) {
            return;
        }

        $building = $ticket->building;

        if ($building && $building->caretaker_id) {
            $ticket->assigned_to = $building->caretaker_id;
        }
    }

    /**
     * Stamp sla_due_at and resolution_due_at using the per-category cascade.
     *
     * Phase-49 SLA-PER-CATEGORY-2: uses the per-(landlord, category,
     * subcategory, priority) cascade. Service has Ticket::SLA_SECONDS /
     * RESOLUTION_SLA_SECONDS as fallback.
     */
    private function stampSlaDueDates(Ticket $ticket): void
    {
        if ($ticket->sla_due_at !== null && $ticket->resolution_due_at !== null) {
            return;
        }

        $seconds = app(SlaDefinitionService::class)->resolveFor(
            (string) $ticket->category,
            $ticket->subcategory,
            (string) $ticket->priority,
            $ticket->landlord_id,
        );
        $base = $ticket->created_at ?? now();

        if ($ticket->sla_due_at === null) {
            $ticket->sla_due_at = $base->copy()->addSeconds($seconds['response_seconds']);
        }

        if ($ticket->resolution_due_at === null) {
            $ticket->resolution_due_at = $base->copy()->addSeconds($seconds['resolution_seconds']);
        }
    }

    /**
     * Log activity entries inside the creation transaction.
     */
    private function logCreatedActivities(Ticket $ticket): void
    {
        $ticket->logActivity(
            TicketActivity::ACTION_CREATED,
            null,
            null,
            "Ticket created: {$ticket->title}",
            $ticket->reporter_id
        );

        if ($ticket->assigned_to) {
            $assignee = $ticket->assignee;
            $ticket->logActivity(
                TicketActivity::ACTION_ASSIGNED,
                null,
                $assignee?->name,
                "Auto-assigned to building caretaker: {$assignee?->name}",
                null
            );
        }
    }

    /**
     * Dispatch notifications after the creation transaction commits.
     */
    private function dispatchCreatedNotifications(Ticket $ticket): void
    {
        if ($ticket->landlord_id) {
            $this->notifyNewTicket($ticket, $ticket->landlord_id);
        }

        if ($ticket->assigned_to) {
            $this->notifyAssignment($ticket, $ticket->assigned_to);
        }

        $this->tryAutoRouteVendor($ticket);
    }

    /**
     * Phase-75 VENDOR-ROUTING-3: opt-in auto-route to the best vendor.
     * No-op unless maintenance.auto_route_vendors is enabled. Best-effort.
     */
    private function tryAutoRouteVendor(Ticket $ticket): void
    {
        if (! $ticket->landlord_id || ! config('maintenance.auto_route_vendors', false)) {
            return;
        }

        try {
            app(\App\Services\Maintenance\VendorAssignmentService::class)->autoAssign($ticket);
        } catch (\Throwable) {
            // routing is non-critical — never break ticket creation
        }
    }

    /**
     * Handle the Ticket "updated" event.
     * Log status changes and assignments, send notifications.
     */
    public function updated(Ticket $ticket): void
    {
        // CONC-4: when a controller calls $ticket->update() inside its own
        // DB::transaction, this observer fires before COMMIT. Notification
        // dispatches must defer with DB::afterCommit so workers don't read
        // pre-commit state and so notifications for rolled-back transitions
        // never go out. The activity-log writes inline because they belong
        // to the same transaction.

        if ($ticket->wasChanged('status')) {
            $oldStatus = $ticket->getOriginal('status');
            $this->handleStatusChange($ticket, $oldStatus, $ticket->status);
        }

        if ($ticket->wasChanged('assigned_to')) {
            $oldAssignedTo = $ticket->getOriginal('assigned_to');
            $this->handleAssignmentChange($ticket, $oldAssignedTo, $ticket->assigned_to);
        }
    }

    /**
     * Handle status change events.
     */
    protected function handleStatusChange(Ticket $ticket, TicketStatus $oldStatus, TicketStatus $newStatus): void
    {
        $action = match ($newStatus) {
            TicketStatus::Resolved => TicketActivity::ACTION_RESOLVED,
            TicketStatus::Closed => TicketActivity::ACTION_CLOSED,
            default => TicketActivity::ACTION_STATUS_CHANGED,
        };

        $oldLabel = $oldStatus->label();
        $newLabel = $newStatus->label();

        $ticket->logActivity(
            $action,
            $oldLabel,
            $newLabel,
            "Status changed from {$oldLabel} to {$newLabel}"
        );

        if ($ticket->reporter_id) {
            DB::afterCommit(fn () => $this->notifyStatusChange($ticket, $ticket->reporter_id, $oldStatus, $newStatus));
        }
    }

    /**
     * Handle assignment change events.
     */
    protected function handleAssignmentChange(Ticket $ticket, ?int $oldAssigneeId, ?int $newAssigneeId): void
    {
        $oldAssignee = $oldAssigneeId ? User::find($oldAssigneeId) : null;
        $newAssignee = $newAssigneeId ? User::find($newAssigneeId) : null;

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
            DB::afterCommit(fn () => $this->notifyAssignment($ticket, $newAssigneeId));
        }
    }

    /**
     * Send notification for new ticket.
     */
    protected function notifyNewTicket(Ticket $ticket, int $recipientId): void
    {
        $categoryLabel = ucfirst($ticket->category);
        $unitInfo = $ticket->unit ? " - Unit {$ticket->unit->unit_number}" : '';

        dispatch(SendNotificationJob::forNew(
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
        ));
    }

    /**
     * Send notification for ticket assignment.
     */
    protected function notifyAssignment(Ticket $ticket, int $assigneeId): void
    {
        $unitInfo = $ticket->unit ? " - Unit {$ticket->unit->unit_number}" : '';

        dispatch(SendNotificationJob::forNew(
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
        ));
    }

    /**
     * Send notification for status change.
     */
    protected function notifyStatusChange(Ticket $ticket, int $reporterId, TicketStatus $oldStatus, TicketStatus $newStatus): void
    {
        $newLabel = $newStatus->label();

        $subject = match ($newStatus) {
            TicketStatus::Acknowledged => 'Your ticket has been acknowledged',
            TicketStatus::InProgress => 'Work has started on your ticket',
            TicketStatus::Resolved => 'Your ticket has been resolved',
            TicketStatus::Closed => 'Your ticket has been closed',
            TicketStatus::Cancelled => 'Your ticket has been cancelled',
            default => 'Your ticket status has been updated',
        };

        $message = "Your ticket \"{$ticket->title}\" has been updated.\n\nNew Status: {$newLabel}";

        if ($newStatus === TicketStatus::Resolved && $ticket->resolution_notes) {
            $message .= "\n\nResolution Notes: {$ticket->resolution_notes}";
        }

        dispatch(SendNotificationJob::forNew(
            $reporterId,
            'maintenance_notice',
            $subject,
            $message,
            [
                'ticket_id' => $ticket->id,
                'old_status' => $oldStatus->value,
                'new_status' => $newStatus->value,
            ],
            $ticket->landlord_id
        ));
    }
}
