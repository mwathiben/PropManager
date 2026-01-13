<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogController extends Controller
{
    /**
     * Display a listing of audit logs.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('view-audit-logs');

        $query = AuditLog::with('user:id,name,email')
            ->latest();

        // Apply landlord scope for non-super-admins
        $user = $request->user();
        if (! $user->isSuperAdmin()) {
            if ($user->isLandlord()) {
                $query->where('landlord_id', $user->id);
            } elseif ($user->isCaretaker()) {
                $query->where('landlord_id', $user->landlord_id);
            }
        }

        // Filter by event type
        if ($request->filled('event_type')) {
            $query->where('event_type', $request->event_type);
        }

        // Filter by model type
        if ($request->filled('model_type')) {
            $query->where('auditable_type', 'like', '%'.$request->model_type.'%');
        }

        // Filter by user
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        // Filter by date range
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        // Search in metadata or auditable type
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('auditable_type', 'like', "%{$search}%")
                    ->orWhere('auditable_id', $search)
                    ->orWhereHas('user', function ($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        $logs = $query->paginate(25)->through(fn ($log) => [
            'id' => $log->id,
            'event_type' => $log->event_type,
            'description' => $log->description,
            'event_icon' => $log->event_icon,
            'event_color' => $log->event_color,
            'auditable_type' => class_basename($log->auditable_type),
            'auditable_id' => $log->auditable_id,
            'changed_fields' => $log->changed_fields,
            'user' => $log->user ? [
                'id' => $log->user->id,
                'name' => $log->user->name,
                'email' => $log->user->email,
            ] : null,
            'ip_address' => $log->ip_address,
            'created_at' => $log->created_at->format('Y-m-d H:i:s'),
            'created_at_human' => $log->created_at->diffForHumans(),
        ]);

        // Get filter options
        $eventTypes = AuditLog::query()
            ->when(! $user->isSuperAdmin(), function ($q) use ($user) {
                $q->where('landlord_id', $user->isLandlord() ? $user->id : $user->landlord_id);
            })
            ->distinct()
            ->pluck('event_type')
            ->toArray();

        $modelTypes = AuditLog::query()
            ->when(! $user->isSuperAdmin(), function ($q) use ($user) {
                $q->where('landlord_id', $user->isLandlord() ? $user->id : $user->landlord_id);
            })
            ->distinct()
            ->pluck('auditable_type')
            ->map(fn ($type) => class_basename($type))
            ->unique()
            ->values()
            ->toArray();

        return Inertia::render('Admin/AuditLogs', [
            'logs' => $logs,
            'filters' => [
                'event_type' => $request->event_type,
                'model_type' => $request->model_type,
                'user_id' => $request->user_id,
                'date_from' => $request->date_from,
                'date_to' => $request->date_to,
                'search' => $request->search,
            ],
            'eventTypes' => $eventTypes,
            'modelTypes' => $modelTypes,
        ]);
    }

    /**
     * Display details of a specific audit log.
     */
    public function show(Request $request, AuditLog $auditLog): Response
    {
        Gate::authorize('view-audit-logs');

        $user = $request->user();

        // Check access for non-super-admins
        if (! $user->isSuperAdmin()) {
            $landlordId = $user->isLandlord() ? $user->id : $user->landlord_id;
            if ($auditLog->landlord_id !== $landlordId) {
                abort(403);
            }
        }

        $auditLog->load('user:id,name,email');

        return Inertia::render('Admin/AuditLogDetail', [
            'log' => [
                'id' => $auditLog->id,
                'event_type' => $auditLog->event_type,
                'description' => $auditLog->description,
                'event_icon' => $auditLog->event_icon,
                'event_color' => $auditLog->event_color,
                'auditable_type' => class_basename($auditLog->auditable_type),
                'auditable_id' => $auditLog->auditable_id,
                'old_values' => $auditLog->old_values,
                'new_values' => $auditLog->new_values,
                'changed_fields' => $auditLog->changed_fields,
                'user' => $auditLog->user ? [
                    'id' => $auditLog->user->id,
                    'name' => $auditLog->user->name,
                    'email' => $auditLog->user->email,
                ] : null,
                'ip_address' => $auditLog->ip_address,
                'user_agent' => $auditLog->user_agent,
                'url' => $auditLog->url,
                'metadata' => $auditLog->metadata,
                'created_at' => $auditLog->created_at->format('Y-m-d H:i:s'),
                'created_at_human' => $auditLog->created_at->diffForHumans(),
            ],
        ]);
    }

    /**
     * Get audit history for a specific model instance.
     */
    public function forModel(Request $request): \Illuminate\Http\JsonResponse
    {
        Gate::authorize('view-audit-logs');

        $request->validate([
            'model_type' => 'required|string',
            'model_id' => 'required|integer',
        ]);

        $user = $request->user();
        $modelType = $request->model_type;
        $modelId = $request->model_id;

        $query = AuditLog::where('auditable_type', 'like', "%{$modelType}%")
            ->where('auditable_id', $modelId)
            ->with('user:id,name')
            ->latest();

        // Apply landlord scope for non-super-admins
        if (! $user->isSuperAdmin()) {
            $landlordId = $user->isLandlord() ? $user->id : $user->landlord_id;
            $query->where('landlord_id', $landlordId);
        }

        $logs = $query->limit(50)->get()->map(fn ($log) => [
            'id' => $log->id,
            'event_type' => $log->event_type,
            'description' => $log->description,
            'event_color' => $log->event_color,
            'changed_fields' => $log->changed_fields,
            'user_name' => $log->user?->name ?? 'System',
            'created_at' => $log->created_at->format('Y-m-d H:i:s'),
            'created_at_human' => $log->created_at->diffForHumans(),
        ]);

        return response()->json(['logs' => $logs]);
    }

    /**
     * Export audit logs to CSV.
     */
    public function export(Request $request)
    {
        Gate::authorize('view-audit-logs');

        $user = $request->user();
        $query = AuditLog::with('user:id,name,email')->latest();

        // Apply landlord scope for non-super-admins
        if (! $user->isSuperAdmin()) {
            $landlordId = $user->isLandlord() ? $user->id : $user->landlord_id;
            $query->where('landlord_id', $landlordId);
        }

        // Apply same filters as index
        if ($request->filled('event_type')) {
            $query->where('event_type', $request->event_type);
        }
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $logs = $query->limit(10000)->get();

        $filename = 'audit_logs_'.now()->format('Y-m-d_His').'.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () use ($logs) {
            $handle = fopen('php://output', 'w');

            // CSV headers
            fputcsv($handle, [
                'ID',
                'Date/Time',
                'User',
                'Event Type',
                'Model',
                'Model ID',
                'Changed Fields',
                'IP Address',
            ]);

            foreach ($logs as $log) {
                fputcsv($handle, [
                    $log->id,
                    $log->created_at->format('Y-m-d H:i:s'),
                    $log->user?->name ?? 'System',
                    $log->event_type,
                    class_basename($log->auditable_type),
                    $log->auditable_id,
                    $log->changed_fields_list,
                    $log->ip_address,
                ]);
            }

            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }
}
