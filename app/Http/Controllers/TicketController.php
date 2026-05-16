<?php

namespace App\Http\Controllers;

use App\Enums\TicketStatus;
use App\Events\TicketStatusChanged;
use App\Http\Requests\Ticket\AddTicketCommentRequest;
use App\Http\Requests\Ticket\AssignTicketRequest;
use App\Http\Requests\Ticket\ResolveTicketRequest;
use App\Http\Requests\Ticket\StoreTicketRequest;
use App\Http\Requests\Ticket\SubmitTicketFeedbackRequest;
use App\Http\Requests\Ticket\UpdateTicketRequest;
use App\Jobs\SendNotificationJob;
use App\Models\Building;
use App\Models\Ticket;
use App\Models\TicketActivity;
use App\Models\TicketFeedback;
use App\Models\Unit;
use App\Models\User;
use App\Traits\HasBuildingFilter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class TicketController extends Controller
{
    use HasBuildingFilter;

    public function index(Request $request): Response
    {
        $user = Auth::user();

        $query = Ticket::query()
            ->with(['building', 'unit', 'reporter', 'assignee', 'feedback']);

        // Role-based filtering
        if ($user->isTenant()) {
            // Tenants only see their own tickets
            $query->where('reporter_id', $user->id);
        } elseif ($user->isCaretaker()) {
            // Caretakers see tickets assigned to them
            $query->where('assigned_to', $user->id);
        }
        // Landlords see all tickets (TenantScope handles filtering)

        // Apply filters
        if ($request->filled('status')) {
            if ($request->status === 'active') {
                $query->open();
            } else {
                $query->where('status', $request->status);
            }
        }

        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        if ($request->filled('priority')) {
            $query->where('priority', $request->priority);
        }

        // Building/Wing filter
        $buildingId = $request->filled('building_id') ? (int) $request->building_id : null;
        $wingId = $request->filled('wing_id') ? (int) $request->wing_id : null;

        if ($buildingId || $wingId) {
            $buildingIds = $this->getBuildingIds($buildingId, $wingId);
            $query->whereIn('building_id', $buildingIds);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Order by priority and date
        $query->orderByRaw("CASE priority
            WHEN 'urgent' THEN 1
            WHEN 'high' THEN 2
            WHEN 'medium' THEN 3
            WHEN 'low' THEN 4
            ELSE 5 END")
            ->orderBy('created_at', 'desc');

        $tickets = $query->paginate(20)->withQueryString();

        // Get buildings for filter dropdown (landlords and caretakers) - only main buildings with wings
        $buildings = [];
        if ($user->isLandlord() || $user->isCaretaker()) {
            $buildings = $this->getBuildingsForFilter();
        }

        // Get stats for dashboard
        $stats = $this->getTicketStats($user);

        return Inertia::render('Tickets/Index', [
            'tickets' => $tickets,
            'buildings' => $buildings,
            'stats' => $stats,
            'filters' => $request->only(['status', 'category', 'priority', 'building_id', 'wing_id', 'search']),
            'statuses' => Ticket::statuses(),
            'priorities' => Ticket::priorities(),
            'categories' => ['issue' => 'Issue', 'complaint' => 'Complaint'],
        ]);
    }

    public function create(Request $request): Response
    {
        $user = Auth::user();

        // Get buildings - for tenants, get their lease's building
        $buildings = [];
        $defaultBuildingId = null;
        $defaultUnitId = null;

        if ($user->isTenant() && $user->lease) {
            $unit = $user->lease->unit;
            if ($unit) {
                $buildings = Building::where('id', $unit->building_id)
                    ->select('id', 'name')
                    ->get();
                $defaultBuildingId = $unit->building_id;
                $defaultUnitId = $unit->id;
            }
        } else {
            $buildings = Building::select('id', 'name')->get();
        }

        // Get units for the first building (or all units for landlords/caretakers)
        $units = Unit::when($defaultBuildingId, function ($q) use ($defaultBuildingId) {
            return $q->where('building_id', $defaultBuildingId);
        })->select('id', 'building_id', 'unit_number')->get();

        return Inertia::render('Tickets/Create', [
            'buildings' => $buildings,
            'units' => $units,
            'defaultBuildingId' => $defaultBuildingId,
            'defaultUnitId' => $defaultUnitId,
            'subcategories' => Ticket::allSubcategories(),
            'priorities' => Ticket::priorities(),
        ]);
    }

    public function store(StoreTicketRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $photos = $request->file('photos', []);
        unset($validated['photos']);

        DB::beginTransaction();

        try {
            $ticket = Ticket::create($validated);

            // Phase-28 TENANT-MAINT-2: persist multi-photo upload (up
            // to 5 × 5MB) as polymorphic Document attachments.
            foreach ($photos as $photo) {
                $path = $photo->store('tickets/'.$ticket->id, 'local');
                $ticket->attachments()->create([
                    'landlord_id' => $ticket->landlord_id,
                    'title' => $photo->getClientOriginalName(),
                    'file_name' => $photo->getClientOriginalName(),
                    'file_path' => $path,
                    'mime_type' => $photo->getMimeType(),
                    'file_size' => $photo->getSize(),
                    'document_type' => 'other',
                    'uploaded_by' => auth()->id(),
                ]);
            }

            DB::commit();

            return redirect()->route('tickets.show', $ticket)
                ->with('success', 'Ticket submitted successfully. You will be notified of updates.');
        } catch (\Exception $e) {
            DB::rollBack();

            return redirect()->back()
                ->with('error', 'Failed to submit ticket. Please try again.')
                ->withInput();
        }
    }

    public function show(Ticket $ticket): Response
    {
        $user = Auth::user();

        // Authorization check - ensure user can access this ticket
        if ($user->isTenant() && $ticket->reporter_id !== $user->id) {
            abort(403, 'You are not authorized to view this ticket.');
        }

        // Additional security: ensure ticket belongs to user's landlord context
        $landlordId = $user->isLandlord() ? $user->id : $user->landlord_id;
        if ($ticket->landlord_id !== $landlordId) {
            abort(404);
        }

        $ticket->load([
            'building',
            'unit',
            'reporter',
            'assignee',
            'activities.user',
            'feedback.user',
            'attachments',
        ]);

        // Load comments based on user role
        if ($user->isTenant()) {
            $ticket->load(['comments' => function ($query) {
                $query->where('is_internal', false)->with('author');
            }]);
        } else {
            $ticket->load('comments.author');
        }

        // Get caretakers for assignment dropdown (landlords only)
        $caretakers = [];
        if ($user->isLandlord()) {
            $caretakers = User::where('landlord_id', $user->id)
                ->where('role', 'caretaker')
                ->select('id', 'name')
                ->get();
        }

        return Inertia::render('Tickets/Show', [
            'ticket' => $ticket,
            'caretakers' => $caretakers,
            'canAssign' => $user->isLandlord(),
            'canChangeStatus' => ! $user->isTenant(),
            'canAddInternalComment' => ! $user->isTenant(),
            'canSubmitFeedback' => $user->isTenant() && $ticket->status === \App\Enums\TicketStatus::Closed && ! $ticket->hasFeedback(),
            'statuses' => Ticket::statuses(),
        ]);
    }

    public function update(UpdateTicketRequest $request, Ticket $ticket): RedirectResponse
    {
        $validated = $request->validated();

        $oldStatus = $ticket->status;
        $ticket->update($validated);

        $newStatus = isset($validated['status']) ? TicketStatus::from($validated['status']) : null;
        if ($newStatus && $newStatus !== $oldStatus) {
            event(new TicketStatusChanged($ticket->fresh(), $oldStatus, $newStatus));

            if ($ticket->reporter_id) {
                dispatch(SendNotificationJob::forNew(
                    $ticket->reporter_id,
                    'maintenance_notice',
                    'Ticket Status Update',
                    "Your ticket \"{$ticket->title}\" has been updated to: {$newStatus->value}.",
                    ['ticket_id' => $ticket->id],
                    $ticket->landlord_id
                ));
            }
        }

        return redirect()->back()->with('success', 'Ticket updated successfully.');
    }

    public function assign(AssignTicketRequest $request, Ticket $ticket): RedirectResponse
    {
        $user = Auth::user();
        $validated = $request->validated();

        // Verify the assignee is a caretaker for this landlord
        $assignee = User::where('id', $validated['assigned_to'])
            ->where('landlord_id', $user->id)
            ->where('role', 'caretaker')
            ->first();

        if (! $assignee) {
            return redirect()->back()->with('error', 'Invalid caretaker selected.');
        }

        $ticket->update(['assigned_to' => $assignee->id]);

        return redirect()->back()->with('success', "Ticket assigned to {$assignee->name}.");
    }

    public function addComment(AddTicketCommentRequest $request, Ticket $ticket): RedirectResponse
    {
        $user = Auth::user();
        $validated = $request->validated();

        $comment = $ticket->comments()->create([
            'user_id' => $user->id,
            'comment' => $validated['comment'],
            'is_internal' => $validated['is_internal'] ?? false,
        ]);

        // Log activity
        $ticket->logActivity(
            TicketActivity::ACTION_COMMENTED,
            null,
            null,
            $validated['is_internal'] ? 'Internal note added' : 'Comment added'
        );

        // Notify relevant parties (if not internal)
        if (! $comment->is_internal) {
            // Notify reporter if comment is from staff
            if (! $user->isTenant() && $ticket->reporter_id) {
                dispatch(SendNotificationJob::forNew(
                    $ticket->reporter_id,
                    'maintenance_notice',
                    'New comment on your ticket',
                    "A new comment has been added to your ticket: {$ticket->title}\n\nComment: ".substr($validated['comment'], 0, 200),
                    ['ticket_id' => $ticket->id],
                    $ticket->landlord_id
                ));
            }

            // Notify assignee if comment is from someone else
            if ($ticket->assigned_to && $ticket->assigned_to !== $user->id) {
                dispatch(SendNotificationJob::forNew(
                    $ticket->assigned_to,
                    'maintenance_notice',
                    'New comment on assigned ticket',
                    "A new comment has been added to ticket: {$ticket->title}\n\nComment: ".substr($validated['comment'], 0, 200),
                    ['ticket_id' => $ticket->id],
                    $ticket->landlord_id
                ));
            }
        }

        return redirect()->back()->with('success', 'Comment added successfully.');
    }

    public function resolve(ResolveTicketRequest $request, Ticket $ticket): RedirectResponse
    {
        $validated = $request->validated();

        $oldStatus = $ticket->status;
        $ticket->resolve($validated['resolution_notes'] ?? null);

        event(new TicketStatusChanged($ticket->fresh(), $oldStatus, TicketStatus::Resolved));

        if ($ticket->reporter_id) {
            dispatch(SendNotificationJob::forNew(
                $ticket->reporter_id,
                'maintenance_notice',
                'Ticket Resolved',
                "Your ticket \"{$ticket->title}\" has been resolved.".
                    ($validated['resolution_notes'] ? "\n\nResolution: {$validated['resolution_notes']}" : ''),
                ['ticket_id' => $ticket->id],
                $ticket->landlord_id
            ));
        }

        return redirect()->back()->with('success', 'Ticket marked as resolved.');
    }

    public function close(Ticket $ticket): RedirectResponse
    {
        $user = Auth::user();

        if ($user->isTenant()) {
            abort(403, 'Tenants cannot close tickets.');
        }

        $oldStatus = $ticket->status;
        $ticket->close();

        event(new TicketStatusChanged($ticket->fresh(), $oldStatus, TicketStatus::Closed));

        if ($ticket->reporter_id) {
            dispatch(SendNotificationJob::forNew(
                $ticket->reporter_id,
                'maintenance_notice',
                'Ticket Closed',
                "Your ticket \"{$ticket->title}\" has been closed. You can now leave feedback.",
                ['ticket_id' => $ticket->id],
                $ticket->landlord_id
            ));
        }

        return redirect()->back()->with('success', 'Ticket closed successfully.');
    }

    public function submitFeedback(SubmitTicketFeedbackRequest $request, Ticket $ticket): RedirectResponse
    {
        $user = Auth::user();

        // Ticket must be closed and not have existing feedback
        if ($ticket->status !== \App\Enums\TicketStatus::Closed) {
            return redirect()->back()->with('error', 'Feedback can only be submitted for closed tickets.');
        }

        if ($ticket->hasFeedback()) {
            return redirect()->back()->with('error', 'Feedback has already been submitted for this ticket.');
        }

        $validated = $request->validated();

        $feedback = TicketFeedback::create([
            'ticket_id' => $ticket->id,
            'user_id' => $user->id,
            'rating' => $validated['rating'],
            'comments' => $validated['comments'] ?? null,
        ]);

        // Log activity
        $ticket->logActivity(
            TicketActivity::ACTION_FEEDBACK_SUBMITTED,
            null,
            $validated['rating'].' stars',
            "Feedback submitted: {$validated['rating']}/5 stars"
        );

        // Notify landlord about feedback
        dispatch(SendNotificationJob::forNew(
            $ticket->landlord_id,
            'maintenance_notice',
            'Tenant feedback received',
            "Feedback received for ticket: {$ticket->title}\n\nRating: {$validated['rating']}/5 stars".
                ($validated['comments'] ? "\n\nComments: {$validated['comments']}" : ''),
            ['ticket_id' => $ticket->id, 'rating' => $validated['rating']],
            $ticket->landlord_id
        ));

        return redirect()->back()->with('success', 'Thank you for your feedback!');
    }

    public function destroy(Ticket $ticket): RedirectResponse
    {
        $user = Auth::user();

        // Only the reporter can cancel their own open tickets
        if ($ticket->reporter_id !== $user->id) {
            abort(403, 'You are not authorized to cancel this ticket.');
        }

        if (! $ticket->canBeEdited()) {
            return redirect()->back()->with('error', 'This ticket cannot be cancelled anymore.');
        }

        $oldStatus = $ticket->status;
        $ticket->cancel();

        event(new TicketStatusChanged($ticket->fresh(), $oldStatus, TicketStatus::Cancelled));

        return redirect()->route('tickets.index')->with('success', 'Ticket cancelled successfully.');
    }

    public function getUnits(Building $building): JsonResponse
    {
        $units = $building->units()
            ->select('id', 'unit_number', 'status')
            ->get();

        return response()->json($units);
    }

    /**
     * Get ticket statistics for the current user.
     */
    protected function getTicketStats(User $user): array
    {
        $baseQuery = Ticket::query();

        if ($user->isTenant()) {
            $baseQuery->where('reporter_id', $user->id);
        } elseif ($user->isCaretaker()) {
            $baseQuery->where('assigned_to', $user->id);
        }

        return [
            'total' => (clone $baseQuery)->count(),
            'open' => (clone $baseQuery)->open()->count(),
            'resolved' => (clone $baseQuery)->where('status', 'resolved')->count(),
            'closed' => (clone $baseQuery)->where('status', 'closed')->count(),
            'urgent' => (clone $baseQuery)->open()->where('priority', 'urgent')->count(),
            'issues' => (clone $baseQuery)->where('category', 'issue')->count(),
            'complaints' => (clone $baseQuery)->where('category', 'complaint')->count(),
        ];
    }
}
