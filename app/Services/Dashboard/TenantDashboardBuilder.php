<?php

declare(strict_types=1);

namespace App\Services\Dashboard;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\TenantInvitation;
use App\Models\Ticket;
use App\Models\User;

/**
 * Tenant dashboard payload, extracted from DashboardService (M2
 * decomposition step 6). Builds the lease-holder's home (balance, next
 * payment, recent activity, caretaker) — or the no-lease state with any
 * pending invitations. Behaviour is locked by the dashboard feature suite
 * (DashboardControllerTest "tenant sees tenant dashboard" + the water
 * dashboard tests) — a verbatim move.
 */
class TenantDashboardBuilder
{
    public function forTenant(User $tenant): array
    {
        $lease = $tenant->lease()->with(['unit.building', 'rentHistory'])->first();

        if (! $lease) {
            return $this->noLeaseData($tenant);
        }

        $unit = $lease->unit;
        $building = $unit->building;

        $totalInvoiced = Invoice::where('lease_id', $lease->id)->sum('total_due');
        $totalPaid = Payment::withArchived()->where('lease_id', $lease->id)->sum('amount');
        $balance = $totalPaid - $totalInvoiced;

        $actionItems = $this->actionItems($tenant, $lease);

        $nextPayment = Invoice::where('lease_id', $lease->id)
            ->whereIn('status', ['sent', 'partial', 'overdue'])
            ->orderBy('due_date', 'asc')
            ->first();

        $recentPayments = Payment::where('lease_id', $lease->id)
            ->where('is_voided', false)
            ->select(['id', 'amount', 'payment_method', 'payment_date'])
            ->orderBy('payment_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        $recentTickets = Ticket::where('reporter_id', $tenant->id)
            ->select(['id', 'title', 'status', 'priority', 'building_id', 'created_at'])
            ->with('building:id,name')
            ->orderBy('created_at', 'desc')
            ->limit(3)
            ->get();

        $pendingInvoices = Invoice::where('lease_id', $lease->id)
            ->whereIn('status', ['sent', 'partial', 'overdue'])
            ->select(['id', 'invoice_number', 'total_due', 'amount_paid', 'due_date', 'status'])
            ->orderBy('due_date', 'asc')
            ->get();

        $caretaker = $building->caretaker;

        return [
            'hasLease' => true,
            'unit' => [
                'id' => $unit->id,
                'unit_number' => $unit->unit_number,
                'floor_number' => $unit->floor_number,
                'status' => $unit->status,
            ],
            'building' => [
                'id' => $building->id,
                'name' => $building->name,
            ],
            'lease' => [
                'id' => $lease->id,
                'rent_amount' => $lease->rent_amount,
                'deposit_amount' => $lease->deposit_amount,
                'start_date' => $lease->start_date,
                'end_date' => $lease->end_date,
            ],
            'balance' => $balance,
            'actionItems' => $actionItems,
            'nextPayment' => $nextPayment,
            'recentPayments' => $recentPayments,
            'recentTickets' => $recentTickets,
            'pendingInvoices' => $pendingInvoices,
            'caretaker' => $caretaker ? [
                'name' => $caretaker->name,
                'mobile_number' => $caretaker->mobile_number,
            ] : null,
        ];
    }

    protected function noLeaseData(User $tenant): array
    {
        $pendingInvitations = TenantInvitation::valid()
            ->where('existing_user_id', $tenant->id)
            ->select(['id', 'unit_id', 'landlord_id', 'rent_amount', 'service_charge', 'deposit_amount', 'start_date', 'end_date', 'expires_at'])
            ->with([
                'unit:id,unit_number,floor_number,building_id',
                'unit.building:id,name,property_id',
                'unit.building.property:id,name',
                'landlord:id,name',
            ])
            ->get()
            ->map(function ($invitation) {
                return [
                    'id' => $invitation->id,
                    'property_name' => $invitation->unit->building->property->name ?? 'Unknown Property',
                    'building_name' => $invitation->unit->building->name ?? 'Unknown Building',
                    'unit_number' => $invitation->unit->unit_number,
                    'floor_number' => $invitation->unit->floor_number,
                    'rent_amount' => $invitation->rent_amount,
                    'service_charge' => $invitation->service_charge,
                    'deposit_amount' => $invitation->deposit_amount,
                    'total_move_in' => $invitation->total_move_in_cost,
                    'start_date' => $invitation->start_date->format('M d, Y'),
                    'end_date' => $invitation->end_date?->format('M d, Y'),
                    'expires_at' => $invitation->expires_at->format('M d, Y'),
                    'landlord_name' => $invitation->landlord->name ?? 'Unknown',
                ];
            });

        return [
            'hasLease' => false,
            'message' => $pendingInvitations->isEmpty()
                ? 'You do not have an active lease. Please contact your landlord.'
                : null,
            'pendingInvitations' => $pendingInvitations,
        ];
    }

    protected function actionItems(User $tenant, $lease): array
    {
        $overdueDate = Invoice::where('lease_id', $lease->id)
            ->where('status', 'overdue')
            ->orderBy('due_date', 'asc')
            ->value('due_date');

        return [
            'pending_invoices' => Invoice::where('lease_id', $lease->id)
                ->whereIn('status', ['sent', 'partial'])
                ->count(),
            'overdue_invoices' => Invoice::where('lease_id', $lease->id)
                ->where('status', 'overdue')
                ->count(),
            'overdue_days' => $overdueDate ? now()->diffInDays($overdueDate) : 0,
            'open_tickets' => Ticket::where('reporter_id', $tenant->id)
                ->whereNotIn('status', ['resolved', 'closed'])
                ->count(),
        ];
    }
}
