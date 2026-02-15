<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Lease;
use App\Models\Ticket;
use App\Services\DashboardService;
use App\Services\FinanceCacheService;
use Illuminate\Http\JsonResponse;

class DashboardStatsController extends Controller
{
    private const CACHE_TYPE = 'dashboard_quick';

    public function __construct(
        private readonly DashboardService $dashboardService,
    ) {}

    public function __invoke(): JsonResponse
    {
        $user = auth()->user();

        if ($user->role === 'super_admin') {
            abort(403, 'Super admins use the admin dashboard.');
        }

        $landlordId = match ($user->role) {
            'landlord' => $user->id,
            'caretaker' => $user->landlord_id,
            default => abort(403, 'You do not have permission to access this resource.'),
        };

        $stats = FinanceCacheService::rememberStats(
            self::CACHE_TYPE,
            $landlordId,
            fn () => $this->buildStats($landlordId),
        );

        return response()->json($stats);
    }

    private function buildStats(int $landlordId): array
    {
        $metrics = $this->dashboardService->calculateQuickMetrics($landlordId);

        $activeLeaseIds = Lease::where('landlord_id', $landlordId)
            ->where('is_active', true)
            ->pluck('id');

        $overdueStats = Invoice::whereIn('lease_id', $activeLeaseIds)
            ->where('status', 'overdue')
            ->selectRaw('COUNT(*) as count, COALESCE(SUM(total_due - amount_paid), 0) as amount')
            ->first();

        $openTickets = Ticket::where('landlord_id', $landlordId)
            ->open()
            ->count();

        return [
            'financial' => $metrics['financial'],
            'arrears_aging' => $metrics['arrears_aging'],
            'action_items' => [
                'overdue_invoices' => (int) ($overdueStats->count ?? 0),
                'overdue_amount' => (float) ($overdueStats->amount ?? 0),
                'open_tickets' => $openTickets,
            ],
        ];
    }
}
