<?php

namespace App\Http\Controllers;

use App\Models\Building;
use App\Models\Import;
use App\Models\Invitation;
use App\Models\NotificationTemplate;
use App\Models\TenantMessage;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OperationsHubController extends Controller
{
    public function index(Request $request): Response
    {
        $user = auth()->user();

        if (! $user->isLandlord() && ! $user->isCaretaker()) {
            abort(403, 'Access denied.');
        }

        $landlordId = $user->isCaretaker() ? $user->landlord_id : $user->id;
        $tab = $request->query('tab', 'notifications');

        $baseProps = [
            'activeTab' => $tab,
            'filters' => $request->only(['search', 'status', 'type']),
            'buildings' => $this->getBuildings($landlordId),
        ];

        $tabData = match ($tab) {
            'notifications' => $this->getNotificationsData($request, $landlordId),
            'inbox' => $this->getInboxData($request, $landlordId),
            'bulk' => $this->getBulkData($landlordId),
            'team' => $this->getTeamData($request, $landlordId),
            'imports' => $this->getImportsData($request, $landlordId),
            default => $this->getNotificationsData($request, $landlordId),
        };

        return Inertia::render('Operations/Hub', array_merge($baseProps, $tabData));
    }

    private function getNotificationsData(Request $request, int $landlordId): array
    {
        $subTab = $request->query('sub_tab', 'reminders');

        $templates = NotificationTemplate::where('landlord_id', $landlordId)
            ->orderBy('name')
            ->get();

        return [
            'notificationSubTab' => $subTab,
            'templates' => $templates,
        ];
    }

    private function getInboxData(Request $request, int $landlordId): array
    {
        $query = TenantMessage::where('landlord_id', $landlordId)
            ->with(['user.lease.unit.building', 'notification', 'ticket'])
            ->latest();

        if ($request->filled('inbox_status')) {
            if ($request->inbox_status === 'unread') {
                $query->where('status', TenantMessage::STATUS_RECEIVED);
            } elseif ($request->inbox_status === 'processed') {
                $query->whereIn('status', [
                    TenantMessage::STATUS_PROCESSED,
                    TenantMessage::STATUS_ACTION_TAKEN,
                ]);
            }
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('body', 'like', "%{$search}%")
                    ->orWhere('from_number', 'like', "%{$search}%")
                    ->orWhereHas('user', fn ($q) => $q->where('name', 'like', "%{$search}%"));
            });
        }

        $messages = $query->paginate(10)->through(function ($message) {
            $unit = $message->user?->lease?->unit;

            return [
                'id' => $message->id,
                'tenant_name' => $message->user?->name ?? 'Unknown',
                'from_number' => $message->from_number,
                'body_preview' => strlen($message->body) > 80
                    ? substr($message->body, 0, 80).'...'
                    : $message->body,
                'source' => $message->source,
                'status' => $message->status,
                'is_reply' => $message->isReply(),
                'has_ticket' => $message->hasTicket(),
                'ticket_id' => $message->ticket_id,
                'unit_name' => $unit?->name,
                'created_at' => $message->created_at->diffForHumans(),
            ];
        })->withQueryString();

        $unreadCount = TenantMessage::where('landlord_id', $landlordId)
            ->where('status', TenantMessage::STATUS_RECEIVED)
            ->count();

        return [
            'inbox' => $messages,
            'inboxUnreadCount' => $unreadCount,
        ];
    }

    private function getBulkData(int $landlordId): array
    {
        $buildings = Building::where('landlord_id', $landlordId)
            ->withCount('units')
            ->get();

        $tenantCount = User::where('landlord_id', $landlordId)
            ->where('role', 'tenant')
            ->whereHas('leases', fn ($q) => $q->where('is_active', true))
            ->count();

        return [
            'buildingsWithCounts' => $buildings,
            'activeTenantCount' => $tenantCount,
            'bulkOperations' => [
                [
                    'id' => 'rent_adjustment',
                    'name' => 'Bulk Rent Adjustment',
                    'description' => 'Adjust rent for multiple units at once',
                    'route' => 'leases.batch-adjust',
                ],
                [
                    'id' => 'send_reminders',
                    'name' => 'Send Payment Reminders',
                    'description' => 'Send payment reminders to tenants with outstanding balances',
                    'route' => 'finances.send-reminders',
                ],
                [
                    'id' => 'generate_invoices',
                    'name' => 'Generate Invoices',
                    'description' => 'Generate monthly invoices for all active leases',
                    'route' => 'invoices.generate',
                ],
            ],
        ];
    }

    private function getTeamData(Request $request, int $landlordId): array
    {
        $caretakers = User::where('landlord_id', $landlordId)
            ->where('role', 'caretaker')
            ->withCount(['assignedBuildings as buildings_count'])
            ->orderBy('name')
            ->get();

        $invitations = Invitation::where('landlord_id', $landlordId)
            ->where('type', 'caretaker')
            ->orderBy('created_at', 'desc')
            ->paginate(10)
            ->withQueryString();

        return [
            'caretakers' => $caretakers,
            'invitations' => $invitations,
        ];
    }

    private function getImportsData(Request $request, int $landlordId): array
    {
        $imports = Import::where('landlord_id', $landlordId)
            ->orderBy('created_at', 'desc')
            ->paginate(20)
            ->withQueryString();

        $importTemplates = [
            [
                'type' => 'tenants',
                'name' => 'Tenants',
                'description' => 'Import tenant information from CSV',
                'fields' => ['name', 'email', 'phone', 'unit_id'],
            ],
            [
                'type' => 'units',
                'name' => 'Units',
                'description' => 'Import unit data from CSV',
                'fields' => ['unit_number', 'building_id', 'rent', 'status'],
            ],
            [
                'type' => 'payments',
                'name' => 'Payments',
                'description' => 'Import payment records from CSV',
                'fields' => ['tenant_id', 'amount', 'date', 'method'],
            ],
        ];

        return [
            'imports' => $imports,
            'importTemplates' => $importTemplates,
        ];
    }

    private function getBuildings(int $landlordId): array
    {
        return Building::where('landlord_id', $landlordId)
            ->select('id', 'name')
            ->orderBy('name')
            ->get()
            ->toArray();
    }
}
