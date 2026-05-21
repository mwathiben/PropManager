<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\LeaseTerminationInitiated;
use App\Models\LegalMatter;
use App\Models\User;
use App\Services\Legal\BulkHoldService;
use App\Services\Legal\HoldSettingsResolver;
use App\Services\Legal\TenantSubjectResolver;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Phase-72 HOLD-SETTINGS (auto-hold rule): when a landlord has opted in
 * (auto_hold_on_eviction), initiating a lease termination auto-preserves that
 * tenant's records under a new "tenant_dispute" matter — a safety net so the
 * landlord doesn't forget. Strictly opt-in (default off) and idempotent per
 * termination (matter_reference AUTO-TERM-{id}).
 */
class HoldOnLeaseTermination implements ShouldQueue
{
    public function __construct(
        private readonly HoldSettingsResolver $settings,
        private readonly TenantSubjectResolver $subjects,
        private readonly BulkHoldService $bulk,
    ) {}

    public function handle(LeaseTerminationInitiated $event): void
    {
        $termination = $event->termination;
        $landlordId = (int) $termination->landlord_id;

        if (! $this->settings->effective($landlordId)['auto_hold_on_eviction']) {
            return;
        }

        $reference = 'AUTO-TERM-'.$termination->id;

        // Atomic per-termination guard so a queue redelivery or concurrent fire
        // can't mint a duplicate matter (a unique index on matter_reference is
        // unsuitable — the wizard allows free-form, possibly repeated refs).
        Cache::lock('auto-hold-term-'.$termination->id, 10)->block(5, function () use ($termination, $landlordId, $reference) {
            $alreadyDone = LegalMatter::withoutGlobalScopes()
                ->where('landlord_id', $landlordId)
                ->where('matter_reference', $reference)
                ->exists();
            if ($alreadyDone) {
                return;
            }

            $lease = $termination->lease()->withoutGlobalScopes()->first();
            $tenant = $lease?->tenant_id !== null ? User::find($lease->tenant_id) : null;
            $landlord = User::find($landlordId);
            if ($tenant === null || $landlord === null) {
                return;
            }

            $idsMap = $this->subjects->idsForTenant($tenant, $landlordId);
            if (array_sum(array_map('count', $idsMap)) === 0) {
                return;
            }

            DB::transaction(function () use ($landlordId, $landlord, $tenant, $reference, $idsMap) {
                $matter = LegalMatter::create([
                    'landlord_id' => $landlordId,
                    'title' => __('legal_holds.auto_hold.title', ['tenant' => $tenant->name]),
                    'matter_reference' => $reference,
                    'situation_type' => 'tenant_dispute',
                    'description' => __('legal_holds.auto_hold.reason'),
                ]);

                foreach ($idsMap as $subjectClass => $ids) {
                    if ($ids === []) {
                        continue;
                    }

                    $this->bulk->holdAll($subjectClass, $ids, $landlord, __('legal_holds.auto_hold.reason'), (int) $matter->id);
                }
            });
        });
    }
}
