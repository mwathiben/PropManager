<?php

declare(strict_types=1);

namespace App\Services\Notification;

use App\Enums\InvoiceStatus;
use App\Jobs\SendNotificationJob;
use App\Models\Lease;

/**
 * Landlord-wide bulk reminder dispatch, extracted from NotificationsController
 * (M2 decomposition). Queues a per-tenant rent reminder or arrears notice for
 * every qualifying active lease. Behaviour is locked by NotificationsTest
 * (the rent-reminders / arrears-notices route cases) — a verbatim move.
 */
class BulkReminderDispatcher
{
    /**
     * Queue a rent reminder for every active lease of the landlord.
     *
     * @return int number of reminders queued
     */
    public function dispatchRentReminders(int $landlordId): int
    {
        // PERF-Q2: thin Lease projection + chunked iteration. The reminder is
        // per-tenant personalized (name + rent_amount), so we keep the per-
        // lease dispatch but stop hydrating full Lease rows and bound memory
        // for landlords with thousands of leases.
        $sent = 0;

        Lease::where('landlord_id', $landlordId)
            ->where('is_active', true)
            ->select(['id', 'tenant_id', 'rent_amount', 'landlord_id'])
            ->with('tenant:id,name')
            ->chunkById(250, function ($leases) use ($landlordId, &$sent) {
                foreach ($leases as $lease) {
                    if (! $lease->tenant) {
                        continue;
                    }

                    dispatch(SendNotificationJob::forNew(
                        $lease->tenant_id,
                        'rent_reminder',
                        'Rent Reminder',
                        sprintf(
                            "Hello %s,\n\nYour rent of KES %s is due soon.\n\nThank you.",
                            $lease->tenant->name,
                            // (float) cast: rent_amount is a MySQL decimal STRING; under
                            // strict_types number_format() rejects a string argument.
                            number_format((float) $lease->rent_amount, 2)
                        ),
                        [
                            'lease_id' => $lease->id,
                            'amount' => $lease->rent_amount,
                            'due_date' => now()->format('Y-m-d'),
                        ],
                        $landlordId
                    ));

                    $sent++;
                }
            });

        return $sent;
    }

    /**
     * Queue an arrears notice for every active lease of the landlord that has
     * an outstanding (overdue / partial / sent) balance.
     *
     * @return int number of notices queued
     */
    public function dispatchArrearsNotices(int $landlordId): int
    {
        // Get all active leases that have overdue invoices
        $leases = Lease::where('landlord_id', $landlordId)
            ->where('is_active', true)
            ->whereHas('invoices', function ($query) {
                $query->whereIn('status', [InvoiceStatus::Overdue, InvoiceStatus::Partial, InvoiceStatus::Sent])
                    ->whereColumn('amount_paid', '<', 'total_due');
            })
            ->with(['tenant:id,name', 'invoices' => function ($query) {
                $query->whereIn('status', [InvoiceStatus::Overdue, InvoiceStatus::Partial, InvoiceStatus::Sent])
                    ->whereColumn('amount_paid', '<', 'total_due');
            }])
            ->get();

        $sent = 0;

        foreach ($leases as $lease) {
            if ($lease->tenant) {
                $arrearsAmount = $lease->invoices->sum(fn ($inv) => $inv->total_due - $inv->amount_paid);

                if ($arrearsAmount > 0) {
                    dispatch(SendNotificationJob::forNew(
                        $lease->tenant_id,
                        'arrears_notice',
                        'Payment Overdue - Arrears Notice',
                        sprintf(
                            "Hello %s,\n\nYou have an outstanding balance of KES %s. Please clear your arrears as soon as possible.\n\nThank you.",
                            $lease->tenant->name,
                            number_format((float) $arrearsAmount, 2)
                        ),
                        [
                            'lease_id' => $lease->id,
                            'arrears_amount' => $arrearsAmount,
                        ],
                        $landlordId
                    ));

                    $sent++;
                }
            }
        }

        return $sent;
    }
}
