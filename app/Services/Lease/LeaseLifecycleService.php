<?php

declare(strict_types=1);

namespace App\Services\Lease;

use App\Models\Lease;
use App\Models\LeasePause;
use App\Models\LeaseRenewal;
use App\Models\LeaseTermination;
use App\Models\LeaseTransfer;
use App\Models\RentEscalation;
use App\Models\RentHistory;
use Illuminate\Support\Carbon;

/**
 * Phase-83 LIFECYCLE-VIEW-2: merge a lease's renewals, terminations, transfers,
 * pauses, rent history, and scheduled escalations into one date-sorted, typed
 * timeline for the lease lifecycle view. Lease-scoped (the lease is the
 * authorization boundary), so global scopes are bypassed and filtered by lease_id.
 */
class LeaseLifecycleService
{
    /**
     * @return list<array{type:string, date:string, title:string, detail:string}>
     */
    public function timeline(Lease $lease): array
    {
        $events = array_merge(
            $this->rentChangeEvents($lease->id),
            $this->escalationEvents($lease->id),
            $this->renewalEvents($lease->id),
            $this->terminationEvents($lease->id),
            $this->transferEvents($lease->id),
            $this->pauseEvents($lease->id),
        );

        usort($events, fn ($a, $b) => strcmp($b['date'], $a['date']));

        return $events;
    }

    /** @return list<array{type:string, date:string, title:string, detail:string}> */
    private function rentChangeEvents(int $leaseId): array
    {
        $events = [];

        foreach ($this->rows(RentHistory::class, $leaseId) as $row) {
            $events[] = [
                'type' => 'rent_change',
                'date' => $this->date($row->effective_date),
                'title' => __('lease.lifecycle.event_rent_change'),
                'detail' => 'KES '.number_format((float) $row->old_amount, 0).' → KES '.number_format((float) $row->new_amount, 0),
            ];
        }

        return $events;
    }

    /** @return list<array{type:string, date:string, title:string, detail:string}> */
    private function escalationEvents(int $leaseId): array
    {
        $events = [];

        foreach ($this->rows(RentEscalation::class, $leaseId) as $row) {
            $events[] = [
                'type' => 'escalation',
                'date' => $this->date($row->effective_date),
                'title' => __('lease.lifecycle.event_escalation'),
                'detail' => __('lease.escalation.status_'.$row->status),
            ];
        }

        return $events;
    }

    /** @return list<array{type:string, date:string, title:string, detail:string}> */
    private function renewalEvents(int $leaseId): array
    {
        $events = [];

        foreach ($this->rows(LeaseRenewal::class, $leaseId) as $row) {
            $events[] = [
                'type' => 'renewal',
                'date' => $this->date($row->proposed_at ?? $row->created_at),
                'title' => __('lease.lifecycle.event_renewal'),
                'detail' => (string) $row->status,
            ];
        }

        return $events;
    }

    /** @return list<array{type:string, date:string, title:string, detail:string}> */
    private function terminationEvents(int $leaseId): array
    {
        $events = [];

        foreach ($this->rows(LeaseTermination::class, $leaseId) as $row) {
            $events[] = [
                'type' => 'termination',
                'date' => $this->date($row->notice_given_at ?? $row->created_at),
                'title' => __('lease.lifecycle.event_termination'),
                'detail' => (string) $row->status,
            ];
        }

        return $events;
    }

    /** @return list<array{type:string, date:string, title:string, detail:string}> */
    private function transferEvents(int $leaseId): array
    {
        $events = [];

        foreach ($this->rows(LeaseTransfer::class, $leaseId) as $row) {
            $events[] = [
                'type' => 'transfer',
                'date' => $this->date($row->transfer_date ?? $row->created_at),
                'title' => __('lease.lifecycle.event_transfer'),
                'detail' => (string) $row->status,
            ];
        }

        return $events;
    }

    /** @return list<array{type:string, date:string, title:string, detail:string}> */
    private function pauseEvents(int $leaseId): array
    {
        $events = [];

        foreach ($this->rows(LeasePause::class, $leaseId) as $row) {
            $events[] = [
                'type' => 'pause',
                'date' => $this->date($row->pause_start ?? $row->created_at),
                'title' => __('lease.lifecycle.event_pause'),
                'detail' => (string) $row->status,
            ];
        }

        return $events;
    }

    /**
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $model
     * @return \Illuminate\Support\Collection<int, \Illuminate\Database\Eloquent\Model>
     */
    private function rows(string $model, int $leaseId)
    {
        return $model::query()->withoutGlobalScopes()->where('lease_id', $leaseId)->get();
    }

    private function date(mixed $value): string
    {
        if ($value instanceof Carbon) {
            return $value->toDateString();
        }

        return $value ? Carbon::parse($value)->toDateString() : now()->toDateString();
    }
}
