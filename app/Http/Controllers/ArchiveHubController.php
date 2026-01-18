<?php

namespace App\Http\Controllers;

use App\Http\Traits\WithLandlordScope;
use App\Models\Document;
use App\Models\Lease;
use App\Models\TenantActivity;
use Carbon\Carbon;
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
        $query = Document::where('landlord_id', $landlordId)
            ->with(['documentable']);

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%'.$request->search.'%')
                    ->orWhere('original_name', 'like', '%'.$request->search.'%');
            });
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
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
            ->withQueryString();

        $documentTypes = [
            ['value' => 'lease_agreement', 'label' => 'Lease Agreement'],
            ['value' => 'tenant_id', 'label' => 'Tenant ID'],
            ['value' => 'tenant_passport', 'label' => 'Passport'],
            ['value' => 'bank_statement', 'label' => 'Bank Statement'],
            ['value' => 'payslip', 'label' => 'Payslip'],
            ['value' => 'reference_letter', 'label' => 'Reference Letter'],
            ['value' => 'utility_bill', 'label' => 'Utility Bill'],
            ['value' => 'other', 'label' => 'Other'],
        ];

        return [
            'documents' => $documents,
            'documentTypes' => $documentTypes,
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
