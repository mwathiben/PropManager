<?php

declare(strict_types=1);

namespace App\Services\Sre;

use App\Models\AttributionTouchpoint;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\ProductEvent;
use App\Models\Ticket;
use App\Models\User;

/**
 * Phase-57 INDEX-AUDIT-2: curated list of hot-path queries the daily
 * IndexAuditScan cron runs EXPLAIN against. Each entry is a label →
 * callable that returns an Eloquent Builder. The Builder must NOT be
 * executed by the catalog — IndexAuditScan calls ->toSql() +
 * ->getBindings() and passes them to a raw EXPLAIN statement.
 *
 * Adding a new entry: append a single line. Removing one is fine too —
 * the gauge for an unmonitored query just stops updating.
 */
class IndexAuditCatalog
{
    /**
     * @return array<string, callable(): \Illuminate\Database\Eloquent\Builder>
     */
    public function queries(): array
    {
        return [
            'dashboard.recent_payments' => fn () => Payment::query()
                ->where('is_voided', false)
                ->orderBy('payment_date', 'desc')
                ->limit(5),

            'cohorts.by_source' => fn () => User::query()->withTrashed()
                ->where('created_at', '>=', now()->subYear()),

            'funnel.sankey_rollup' => fn () => ProductEvent::query()->withoutGlobalScopes()
                ->where('created_at', '>=', now()->subDays(90))
                ->whereIn('event_name', ['funnel.signup', 'funnel.first_payment']),

            'attribution.touchpoints_for_user' => fn () => AttributionTouchpoint::query()
                ->where('user_id', 1)
                ->orderBy('touched_at'),

            'tickets.recent_for_landlord' => fn () => Ticket::query()
                ->where('landlord_id', 1)
                ->orderByDesc('created_at')
                ->limit(20),

            'invoices.overdue_for_landlord' => fn () => Invoice::query()
                ->where('landlord_id', 1)
                ->where('status', 'overdue'),

            'payments.recent_for_lease' => fn () => Payment::query()
                ->where('lease_id', 1)
                ->where('is_voided', false)
                ->orderByDesc('payment_date'),

            'users.by_acquisition_source' => fn () => User::query()
                ->where('acquisition_source', 'organic'),
        ];
    }
}
