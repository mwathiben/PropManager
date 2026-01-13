<?php

namespace App\Http\Controllers;

use App\Models\TenantActivity;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ActivityLogController extends Controller
{
    /**
     * Display a listing of all activity logs.
     */
    public function index(Request $request)
    {
        $user = auth()->user();

        if (! $user->isLandlord() && ! $user->isCaretaker()) {
            abort(403, 'Access denied.');
        }

        $landlordId = $user->isCaretaker() ? $user->landlord_id : $user->id;

        $query = TenantActivity::where('landlord_id', $landlordId)
            ->with([
                'tenant:id,name,email',
                'performer:id,name,email',
            ]);

        // Type filter
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        // Date range filter
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        // Search filter
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                    ->orWhereHas('tenant', function ($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%");
                    })
                    ->orWhereHas('performer', function ($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%");
                    });
            });
        }

        // Order by most recent first
        $query->orderBy('created_at', 'desc');

        $activities = $query->paginate(50)->withQueryString();

        // Transform activities for display
        $activities->getCollection()->transform(function ($activity) {
            return [
                'id' => $activity->id,
                'type' => $activity->type,
                'type_label' => $this->getTypeLabel($activity->type),
                'type_color' => $this->getTypeColor($activity->type),
                'description' => $activity->description,
                'tenant' => $activity->tenant ? [
                    'id' => $activity->tenant->id,
                    'name' => $activity->tenant->name,
                ] : null,
                'performer' => $activity->performer ? [
                    'id' => $activity->performer->id,
                    'name' => $activity->performer->name,
                ] : null,
                'metadata' => $activity->metadata,
                'created_at' => $activity->created_at,
                'created_at_human' => $activity->created_at->diffForHumans(),
            ];
        });

        // Get available activity types for filter
        $activityTypes = TenantActivity::where('landlord_id', $landlordId)
            ->distinct()
            ->pluck('type')
            ->map(function ($type) {
                return [
                    'value' => $type,
                    'label' => $this->getTypeLabel($type),
                ];
            });

        // Get stats
        $stats = [
            'total_activities' => TenantActivity::where('landlord_id', $landlordId)->count(),
            'today' => TenantActivity::where('landlord_id', $landlordId)
                ->whereDate('created_at', Carbon::today())
                ->count(),
            'this_week' => TenantActivity::where('landlord_id', $landlordId)
                ->where('created_at', '>=', Carbon::now()->startOfWeek())
                ->count(),
            'this_month' => TenantActivity::where('landlord_id', $landlordId)
                ->where('created_at', '>=', Carbon::now()->startOfMonth())
                ->count(),
        ];

        return Inertia::render('ActivityLogs/Index', [
            'activities' => $activities,
            'activityTypes' => $activityTypes,
            'stats' => $stats,
            'filters' => $request->only(['type', 'date_from', 'date_to', 'search']),
        ]);
    }

    /**
     * Get human-readable label for activity type.
     */
    private function getTypeLabel(string $type): string
    {
        return match ($type) {
            'lease_created' => 'Lease Created',
            'lease_renewed' => 'Lease Renewed',
            'lease_terminated' => 'Lease Terminated',
            'rent_adjusted' => 'Rent Adjusted',
            'payment_received' => 'Payment Received',
            'invoice_generated' => 'Invoice Generated',
            'document_uploaded' => 'Document Uploaded',
            'verification_submitted' => 'Verification Submitted',
            'verification_approved' => 'Verification Approved',
            'verification_rejected' => 'Verification Rejected',
            'move_out_initiated' => 'Move-out Initiated',
            'move_out_completed' => 'Move-out Completed',
            'note_added' => 'Note Added',
            'profile_updated' => 'Profile Updated',
            'emergency_contact_added' => 'Emergency Contact Added',
            default => ucwords(str_replace('_', ' ', $type)),
        };
    }

    /**
     * Get color class for activity type.
     */
    private function getTypeColor(string $type): string
    {
        return match ($type) {
            'lease_created', 'lease_renewed' => 'bg-green-100 text-green-800',
            'lease_terminated', 'move_out_completed' => 'bg-red-100 text-red-800',
            'payment_received' => 'bg-emerald-100 text-emerald-800',
            'invoice_generated' => 'bg-blue-100 text-blue-800',
            'rent_adjusted' => 'bg-yellow-100 text-yellow-800',
            'verification_approved' => 'bg-green-100 text-green-800',
            'verification_rejected' => 'bg-red-100 text-red-800',
            'verification_submitted' => 'bg-purple-100 text-purple-800',
            'document_uploaded' => 'bg-indigo-100 text-indigo-800',
            'move_out_initiated' => 'bg-orange-100 text-orange-800',
            'note_added' => 'bg-gray-100 text-gray-800',
            'profile_updated', 'emergency_contact_added' => 'bg-cyan-100 text-cyan-800',
            default => 'bg-gray-100 text-gray-800',
        };
    }
}
