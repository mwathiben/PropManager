<?php

namespace App\Http\Controllers;

use App\Http\Traits\WithLandlordScope;
use App\Models\Building;
use App\Models\Import;
use App\Models\Invitation;
use App\Models\Notification;
use App\Models\NotificationSchedule;
use App\Models\NotificationTemplate;
use App\Models\TenantMessage;
use App\Models\User;
use App\Repositories\Contracts\NotificationConfigRepositoryInterface;
use App\Services\PushNotificationService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OperationsHubController extends Controller
{
    use WithLandlordScope;

    public function __construct(
        private readonly NotificationConfigRepositoryInterface $notificationConfig
    ) {}

    public function index(Request $request): Response
    {
        $landlordId = $this->getLandlordId();
        $tab = $request->query('tab', 'overview');

        $baseProps = [
            'activeTab' => $tab,
            'filters' => $request->only(['search', 'status', 'type']),
            'buildings' => $this->getBuildings($landlordId),
        ];

        $tabData = match ($tab) {
            'overview' => $this->getOverviewData($landlordId),
            'notifications' => $this->getNotificationsData($request, $landlordId),
            'inbox' => $this->getInboxData($request, $landlordId),
            'bulk' => $this->getBulkData($landlordId),
            'team' => $this->getTeamData($request, $landlordId),
            'imports' => $this->getImportsData($request, $landlordId),
            default => $this->getOverviewData($landlordId),
        };

        return Inertia::render('Operations/Hub', array_merge($baseProps, $tabData));
    }

    private function getOverviewData(int $landlordId): array
    {
        $unreadInbox = TenantMessage::where('landlord_id', $landlordId)
            ->where('status', TenantMessage::STATUS_RECEIVED)->count();
        $pending = Notification::where('landlord_id', $landlordId)->where('status', 'pending')->count();
        $failed = Notification::where('landlord_id', $landlordId)->where('status', 'failed')->count();
        $team = User::where('landlord_id', $landlordId)->where('role', 'caretaker')->count();

        return [
            'overviewStats' => [
                ['label' => 'Unread inbox', 'value' => $unreadInbox, 'tone' => $unreadInbox > 0 ? 'amber' : 'emerald'],
                ['label' => 'Pending notifications', 'value' => $pending, 'tone' => 'default'],
                ['label' => 'Failed notifications', 'value' => $failed, 'tone' => $failed > 0 ? 'red' : 'default'],
                ['label' => 'Team members', 'value' => $team, 'tone' => 'default'],
            ],
        ];
    }

    private function getNotificationsData(Request $request, int $landlordId): array
    {
        $stats = [
            'total_sent' => Notification::where('landlord_id', $landlordId)
                ->whereIn('status', ['sent', 'delivered', 'read'])
                ->count(),
            'pending' => Notification::where('landlord_id', $landlordId)
                ->where('status', 'pending')
                ->count(),
            'failed' => Notification::where('landlord_id', $landlordId)
                ->where('status', 'failed')
                ->count(),
            'this_month' => Notification::where('landlord_id', $landlordId)
                ->whereMonth('created_at', now()->month)
                ->count(),
        ];

        $recentNotifications = Notification::where('landlord_id', $landlordId)
            ->with('recipient:id,name')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        $channelStats = Notification::where('landlord_id', $landlordId)
            ->selectRaw('channel, count(*) as count')
            ->groupBy('channel')
            ->pluck('count', 'channel')
            ->toArray();

        $tenants = User::where('role', 'tenant')
            ->where('landlord_id', $landlordId)
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        $templates = NotificationTemplate::where('landlord_id', $landlordId)
            ->orderBy('name')
            ->get();

        $scheduled = NotificationSchedule::where('landlord_id', $landlordId)
            ->where('is_active', true)
            ->orderBy('last_run_at', 'desc')
            ->limit(5)
            ->get();

        return [
            'stats' => $stats,
            'recentNotifications' => $recentNotifications,
            'channelStats' => $channelStats,
            'tenants' => $tenants,
            'templates' => $templates,
            'scheduled' => $scheduled,
            'setupComplete' => $this->isNotificationSetupComplete($landlordId),
        ];
    }

    private function isNotificationSetupComplete(int $landlordId): bool
    {
        if ($this->notificationConfig->isSetupComplete($landlordId)) {
            return true;
        }

        $smsConfigured = $this->notificationConfig->getSmsProvider($landlordId) !== 'none';
        $pushConfigured = app(PushNotificationService::class)->isConfigured($landlordId);

        return $smsConfigured || $pushConfigured;
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
}
