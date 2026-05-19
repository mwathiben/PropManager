<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\Invoice;
use App\Models\LegalHold;
use App\Models\Lease;
use App\Models\MessageThread;
use App\Models\Ticket;
use App\Models\User;
use App\Services\Legal\BulkHoldService;
use App\Support\LegalHoldRegistry;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Phase-65 BULK-HOLD-3: tenant-litigation preset. One-click hold on
 * every record tied to a specific tenant — invoices + tickets +
 * documents + message threads — inside ONE outer DB::transaction so
 * a mid-flight failure rolls back ALL subject_types (partial hold
 * state never observable). Emits per-subject counter for ops.
 */
class TenantLegalHoldController extends Controller
{
    public function __construct(private readonly BulkHoldService $service)
    {
    }

    public function __invoke(Request $request, User $tenant): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:500'],
        ]);

        $user = $request->user();

        if (! $user->can('create', LegalHold::class)) {
            throw new AuthorizationException;
        }

        if ((int) $tenant->landlord_id !== (int) $user->id) {
            throw new AuthorizationException;
        }

        $subjectMap = [
            Invoice::class => $this->invoiceIdsForTenant($tenant, (int) $user->id),
            Ticket::class => $this->ticketIdsForTenant($tenant, (int) $user->id),
            Document::class => $this->documentIdsForTenant($tenant, (int) $user->id),
            MessageThread::class => $this->threadIdsForTenant($tenant, (int) $user->id),
        ];

        try {
            DB::transaction(function () use ($subjectMap, $user, $validated) {
                foreach ($subjectMap as $subjectClass => $ids) {
                    if ($ids === []) {
                        continue;
                    }

                    $this->service->holdAll($subjectClass, $ids, $user, $validated['reason']);

                    app(\App\Services\MetricsService::class)->increment(
                        'tenant_litigation_hold_subjects_count',
                        count($ids),
                        ['subject_type' => class_basename($subjectClass)],
                    );
                }
            });
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['reason' => __($e->getMessage())]);
        }

        Cache::forget('legal_holds:active:'.$user->id);

        return redirect()->route('tenants.show', $tenant)
            ->with('success', __('legal_holds.create_modal_title'));
    }

    /**
     * @return array<int, int>
     */
    private function invoiceIdsForTenant(User $tenant, int $landlordId): array
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
    private function ticketIdsForTenant(User $tenant, int $landlordId): array
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
    private function documentIdsForTenant(User $tenant, int $landlordId): array
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
    private function threadIdsForTenant(User $tenant, int $landlordId): array
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
