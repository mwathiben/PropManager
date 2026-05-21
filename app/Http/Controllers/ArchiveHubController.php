<?php

namespace App\Http\Controllers;

use App\Http\Traits\WithLandlordScope;
use App\Models\Document;
use App\Models\Lease;
use App\Models\TenantActivity;
use App\Support\TenantClock;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ArchiveHubController extends Controller
{
    use WithLandlordScope;

    public function index(Request $request): Response
    {
        $landlordId = $this->getLandlordId();
        $tab = $request->query('tab', 'documents');

        $baseProps = [
            'activeTab' => $tab,
            'filters' => $request->only(['search', 'type', 'building_id', 'wing_id', 'model_type', 'date_from', 'date_to']),
            'buildings' => $this->getBuildings($landlordId),
            'buildingsWithWings' => $this->getBuildingsWithWings($landlordId),
        ];

        $tabData = match ($tab) {
            'documents' => $this->getDocumentsData($request, $landlordId),
            'leases' => $this->getLeasesData($request, $landlordId),
            'activity' => $this->getActivityData($request, $landlordId),
            default => $this->getDocumentsData($request, $landlordId),
        };

        return Inertia::render('Archive/Hub', array_merge($baseProps, $tabData));
    }

    private function getDocumentsData(Request $request, int $landlordId): array
    {
        // Phase-82 DOC-RENEWAL-2: the archive shows CURRENT documents (the
        // renewal chain's superseded versions drop out by default).
        $query = Document::where('landlord_id', $landlordId)
            ->current()
            ->with(['documentable']);

        if ($request->filled('search')) {
            // Phase-82 fix: real columns are title/file_name (not name/original_name).
            $query->where(function ($q) use ($request) {
                $q->where('title', 'like', '%'.$request->search.'%')
                    ->orWhere('file_name', 'like', '%'.$request->search.'%');
            });
        }

        if ($request->filled('type')) {
            // Phase-82 fix: the column is document_type (not type).
            $query->where('document_type', $request->type);
        }

        // Phase-82 DOC-EXPIRY-1: landlord expiry filter.
        if ($request->filled('expiry')) {
            if ($request->expiry === 'expired') {
                $query->whereNotNull('expires_at')->whereDate('expires_at', '<', now());
            } elseif ($request->expiry === 'expiring') {
                $query->whereNotNull('expires_at')
                    ->whereDate('expires_at', '>=', now())
                    ->whereDate('expires_at', '<=', now()->addDays(30));
            }
        }

        if ($request->filled('model_type')) {
            $modelType = $request->model_type === 'Lease'
                ? 'App\\Models\\Lease'
                : 'App\\Models\\User';
            $query->where('documentable_type', $modelType);
        }

        if ($request->filled('building_id')) {
            $buildingId = $request->building_id;
            $wingId = $request->wing_id;

            $query->where(function ($q) use ($buildingId, $wingId) {
                $q->whereHasMorph('documentable', ['App\\Models\\Lease'], function ($leaseQuery) use ($buildingId, $wingId) {
                    $leaseQuery->whereHas('unit', function ($unitQuery) use ($buildingId, $wingId) {
                        $unitQuery->where('building_id', $buildingId);
                        if ($wingId) {
                            $unitQuery->where('wing_id', $wingId);
                        }
                    });
                });
            });
        }

        $documents = $query->orderBy('created_at', 'desc')
            ->paginate(20)
            ->withQueryString()
            // Phase-82 DOC-EXPIRY-1: surface expiry + renewal state per row.
            ->through(fn (Document $d) => [
                'id' => $d->id,
                'title' => $d->title,
                'file_name' => $d->file_name,
                'document_type' => $d->document_type,
                'document_type_label' => __('document.types.'.$d->document_type),
                'documentable_type' => class_basename((string) $d->documentable_type),
                'expires_at' => $d->expires_at?->toDateString(),
                'expiry_status' => $d->expiryStatus(),
                'is_renewable' => (bool) $d->is_renewable,
                'uploaded_at' => $d->created_at?->toDateString(),
            ]);

        $documentTypes = collect(Document::DOCUMENT_TYPES)
            ->map(fn ($label, $value) => ['value' => $value, 'label' => __('document.types.'.$value)])
            ->values()
            ->all();

        return [
            'documents' => $documents,
            'documentTypes' => $documentTypes,
            'expiryFilters' => [
                ['value' => '', 'label' => __('document.expiry.filter_all')],
                ['value' => 'expiring', 'label' => __('document.expiry.filter_expiring')],
                ['value' => 'expired', 'label' => __('document.expiry.filter_expired')],
            ],
        ];
    }

    private function getLeasesData(Request $request, int $landlordId): array
    {
        $query = Lease::where('landlord_id', $landlordId)
            ->with(['tenant', 'unit.building']);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->whereHas('tenant', fn ($tq) => $tq->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('unit', fn ($uq) => $uq->where('unit_number', 'like', "%{$search}%"));
            });
        }

        if ($request->filled('building_id')) {
            $query->whereHas('unit', fn ($q) => $q->where('building_id', $request->building_id));
        }

        if ($request->filled('status')) {
            if ($request->status === 'active') {
                $query->where('is_active', true);
            } else {
                $query->where('is_active', false);
            }
        }

        $leases = $query->orderBy('created_at', 'desc')
            ->paginate(20)
            ->withQueryString();

        $stats = [
            'active' => Lease::where('landlord_id', $landlordId)->where('is_active', true)->count(),
            'inactive' => Lease::where('landlord_id', $landlordId)->where('is_active', false)->count(),
        ];

        return [
            'leases' => $leases,
            'stats' => $stats,
        ];
    }

    private function getActivityData(Request $request, int $landlordId): array
    {
        $query = TenantActivity::where('landlord_id', $landlordId)
            ->with([
                'tenant:id,name,email',
                'performer:id,name,email',
            ]);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                    ->orWhereHas('tenant', fn ($tq) => $tq->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('performer', fn ($pq) => $pq->where('name', 'like', "%{$search}%"));
            });
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $activities = $query->orderBy('created_at', 'desc')
            ->paginate(50)
            ->withQueryString();

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

        $activityTypes = TenantActivity::where('landlord_id', $landlordId)
            ->distinct()
            ->pluck('type')
            ->map(fn ($type) => [
                'value' => $type,
                'label' => $this->getTypeLabel($type),
            ]);

        // Phase-21 DEFER-PERF-2: TenantClock-anchored date stats so the
        // ArchiveHub "today" / "this week" badges match the viewing user's
        // local day boundary (Phase-17 pattern).
        $userNow = TenantClock::nowFor(auth()->user());
        $stats = [
            'total_activities' => TenantActivity::where('landlord_id', $landlordId)->count(),
            'today' => TenantActivity::where('landlord_id', $landlordId)
                ->whereDate('created_at', $userNow->toDateString())
                ->count(),
            'this_week' => TenantActivity::where('landlord_id', $landlordId)
                ->where('created_at', '>=', $userNow->startOfWeek())
                ->count(),
            'this_month' => TenantActivity::where('landlord_id', $landlordId)
                ->where('created_at', '>=', $userNow->startOfMonth())
                ->count(),
        ];

        return [
            'activities' => $activities,
            'activityTypes' => $activityTypes,
            'stats' => $stats,
        ];
    }

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
