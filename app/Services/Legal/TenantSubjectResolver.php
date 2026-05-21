<?php

declare(strict_types=1);

namespace App\Services\Legal;

use App\Models\Document;
use App\Models\Invoice;
use App\Models\Lease;
use App\Models\LegalHold;
use App\Models\MessageThread;
use App\Models\Ticket;
use App\Models\User;

/**
 * Phase-72 SUBJECT-PICKER: resolves every holdable record tied to a tenant
 * (invoices + tickets + documents + message threads), the single source of
 * truth shared by the tenant-litigation preset (Phase-65) and the wizard's
 * interactive subject picker. All lookups are landlord-scoped (landlord_id
 * match) with global scopes bypassed so the caller's ownership is explicit.
 */
class TenantSubjectResolver
{
    /**
     * Flat per-class id map (used by the all-or-nothing preset).
     *
     * @return array<class-string, array<int, int>>
     */
    public function idsForTenant(User $tenant, int $landlordId): array
    {
        return [
            Invoice::class => $this->invoiceIds($tenant, $landlordId),
            Ticket::class => $this->ticketIds($tenant, $landlordId),
            Document::class => $this->documentIds($tenant, $landlordId),
            MessageThread::class => $this->threadIds($tenant, $landlordId),
        ];
    }

    /**
     * Rich per-type suggestion for the picker: each record + whether it is
     * already under an active hold, plus per-type total/held counts. Items are
     * capped at the bulk ceiling so "select all" can never exceed what the
     * wizard's holdAll will accept; `truncated` tells the UI more exist.
     *
     * @return list<array{type:string, short:string, count:int, held:int, truncated:bool, items:list<array{id:int, already_held:bool}>}>
     */
    public function suggest(User $tenant, int $landlordId): array
    {
        $cap = min((int) config('legal_hold.bulk_max', 100), BulkHoldService::HARDCODED_BULK_CEILING);
        $groups = [];

        foreach ($this->idsForTenant($tenant, $landlordId) as $class => $ids) {
            $held = $ids === [] ? [] : LegalHold::query()
                ->where('holdable_type', $class)
                ->whereIn('holdable_id', $ids)
                ->whereNull('released_at')
                ->pluck('holdable_id')
                ->map(fn ($id) => (int) $id)
                ->all();
            $heldSet = array_flip($held);
            $shown = array_slice($ids, 0, $cap);

            $groups[] = [
                'type' => $class,
                'short' => class_basename($class),
                'count' => count($ids),
                'held' => count($held),
                'truncated' => count($ids) > count($shown),
                'items' => array_map(
                    fn (int $id) => ['id' => $id, 'already_held' => isset($heldSet[$id])],
                    $shown,
                ),
            ];
        }

        return $groups;
    }

    /**
     * @return array<int, int>
     */
    private function invoiceIds(User $tenant, int $landlordId): array
    {
        return Invoice::query()
            ->withoutGlobalScopes()
            ->where('landlord_id', $landlordId)
            ->whereIn('lease_id', $tenant->leases()->withoutGlobalScopes()->pluck('id'))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * @return array<int, int>
     */
    private function ticketIds(User $tenant, int $landlordId): array
    {
        return Ticket::query()
            ->withoutGlobalScopes()
            ->where('landlord_id', $landlordId)
            ->where('reporter_id', $tenant->id)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * @return array<int, int>
     */
    private function documentIds(User $tenant, int $landlordId): array
    {
        return Document::query()
            ->withoutGlobalScopes()
            ->where('landlord_id', $landlordId)
            ->where(function ($q) use ($tenant) {
                $q->where(function ($qq) use ($tenant) {
                    $qq->where('documentable_type', User::class)
                        ->where('documentable_id', $tenant->id);
                })
                    ->orWhere(function ($qq) use ($tenant) {
                        $qq->where('documentable_type', Lease::class)
                            ->whereIn('documentable_id', $tenant->leases()->withoutGlobalScopes()->pluck('id'));
                    });
            })
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * @return array<int, int>
     */
    private function threadIds(User $tenant, int $landlordId): array
    {
        return MessageThread::query()
            ->withoutGlobalScopes()
            ->where('landlord_id', $landlordId)
            ->whereHas('participants', fn ($q) => $q->where('users.id', $tenant->id))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }
}
