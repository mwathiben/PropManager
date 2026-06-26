<?php

namespace App\Http\Controllers;

use App\Http\Traits\WithLandlordScope;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MaintenanceHubController extends Controller
{
    use WithLandlordScope;

    public function index(Request $request): Response
    {
        $landlordId = $this->getLandlordId();
        $tab = $request->query('tab', 'overview');

        $baseProps = [
            'activeTab' => $tab,
            'filters' => $request->only(['search', 'status', 'priority', 'building_id']),
            'buildings' => $this->getBuildings($landlordId),
            'caretakers' => $this->getCaretakers($landlordId),
            'counts' => $this->getCounts($landlordId),
        ];

        $user = $request->user();

        $tabData = match ($tab) {
            'overview' => $this->getOverviewData($landlordId),
            'tickets' => $this->getTicketsData($request, $user, 'issue'),
            'complaints' => $this->getTicketsData($request, $user, 'complaint'),
            default => $this->getOverviewData($landlordId),
        };

        return Inertia::render('Maintenance/Hub', array_merge($baseProps, $tabData));
    }

    private function getOverviewData(int $landlordId): array
    {
        $openIssues = Ticket::where('landlord_id', $landlordId)->where('category', 'issue')->open()->count();
        $openComplaints = Ticket::where('landlord_id', $landlordId)->where('category', 'complaint')->open()->count();
        $urgent = Ticket::where('landlord_id', $landlordId)->open()->where('priority', 'urgent')->count();
        $escalated = Ticket::where('landlord_id', $landlordId)->escalated()->count();

        return [
            'overviewStats' => [
                ['label' => __('maintenance.overview.open_issues'), 'value' => $openIssues, 'tone' => $openIssues > 0 ? 'amber' : 'emerald'],
                ['label' => __('maintenance.overview.open_complaints'), 'value' => $openComplaints, 'tone' => $openComplaints > 0 ? 'amber' : 'emerald'],
                ['label' => __('maintenance.overview.urgent'), 'value' => $urgent, 'tone' => $urgent > 0 ? 'red' : 'default'],
                ['label' => __('maintenance.overview.escalated'), 'value' => $escalated, 'tone' => $escalated > 0 ? 'red' : 'default'],
            ],
        ];
    }

    private function getTicketsData(Request $request, User $user, string $category): array
    {
        $query = Ticket::query()
            ->where('category', $category)
            ->with(['building', 'unit', 'reporter', 'assignee', 'feedback']);

        if ($user->isCaretaker()) {
            $query->where('assigned_to', $user->id);
        }

        $this->applyTicketFilters($query, $request);

        $query->orderByRaw("CASE priority
            WHEN 'urgent' THEN 1
            WHEN 'high' THEN 2
            WHEN 'medium' THEN 3
            WHEN 'low' THEN 4
            ELSE 5
        END")
            ->orderBy('created_at', 'desc');

        $tickets = $query->paginate(20)->withQueryString();

        $landlordId = $user->isCaretaker() ? $user->landlord_id : $user->id;

        return [
            'tickets' => $tickets,
            'stats' => $this->getTicketStats($landlordId, $category),
        ];
    }

    private function applyTicketFilters(\Illuminate\Database\Eloquent\Builder $query, Request $request): void
    {
        if ($request->filled('status')) {
            if ($request->status === 'active') {
                $query->open();
            } else {
                $query->where('status', $request->status);
            }
        }

        if ($request->filled('priority')) {
            $query->where('priority', $request->priority);
        }

        if ($request->filled('building_id')) {
            $query->where('building_id', $request->building_id);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }
    }

    private function getTicketStats(int $landlordId, string $category): array
    {
        return [
            'open' => Ticket::where('landlord_id', $landlordId)
                ->where('category', $category)
                ->open()
                ->count(),
            'in_progress' => Ticket::where('landlord_id', $landlordId)
                ->where('category', $category)
                ->where('status', 'in_progress')
                ->count(),
            'resolved_this_week' => Ticket::where('landlord_id', $landlordId)
                ->where('category', $category)
                ->where('status', 'resolved')
                ->where('resolved_at', '>=', now()->startOfWeek())
                ->count(),
        ];
    }

    private function getCounts(int $landlordId): array
    {
        return [
            'tickets' => Ticket::where('landlord_id', $landlordId)
                ->where('category', 'issue')
                ->open()
                ->count(),
            'complaints' => Ticket::where('landlord_id', $landlordId)
                ->where('category', 'complaint')
                ->open()
                ->count(),
        ];
    }

    private function getCaretakers(int $landlordId): array
    {
        return User::where('landlord_id', $landlordId)
            ->where('role', 'caretaker')
            ->select('id', 'name')
            ->orderBy('name')
            ->get()
            ->toArray();
    }
}
