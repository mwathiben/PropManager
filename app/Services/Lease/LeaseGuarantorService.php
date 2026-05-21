<?php

declare(strict_types=1);

namespace App\Services\Lease;

use App\Models\Lease;
use App\Models\LeaseGuarantor;

/**
 * Phase-83 GUARANTOR-1/2: add / release guarantors, and release all active
 * guarantors when a lease ends (move-out completion).
 */
class LeaseGuarantorService
{
    /**
     * @param  array{name:string, email?:string|null, phone?:string|null, national_id?:string|null, relationship?:string|null, guaranteed_amount?:float|string|null}  $data
     */
    public function add(Lease $lease, array $data): LeaseGuarantor
    {
        return LeaseGuarantor::create([
            'lease_id' => $lease->id,
            'landlord_id' => $lease->landlord_id,
            'name' => $data['name'],
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'national_id' => $data['national_id'] ?? null,
            'relationship' => $data['relationship'] ?? null,
            'guaranteed_amount' => $data['guaranteed_amount'] ?? null,
            'status' => LeaseGuarantor::STATUS_ACTIVE,
        ]);
    }

    public function release(LeaseGuarantor $guarantor, string $reason): bool
    {
        if ($guarantor->status !== LeaseGuarantor::STATUS_ACTIVE) {
            return false;
        }

        return $guarantor->update([
            'status' => LeaseGuarantor::STATUS_RELEASED,
            'released_at' => now(),
            'released_reason' => $reason,
        ]);
    }

    /**
     * Release every still-active guarantor on a lease (e.g. at move-out).
     *
     * @return int how many were released
     */
    public function releaseAllForLease(Lease $lease, string $reason): int
    {
        $released = 0;

        LeaseGuarantor::query()
            ->withoutGlobalScopes()
            ->where('lease_id', $lease->id)
            ->active()
            ->get()
            ->each(function (LeaseGuarantor $guarantor) use ($reason, &$released) {
                if ($this->release($guarantor, $reason)) {
                    $released++;
                }
            });

        return $released;
    }
}
