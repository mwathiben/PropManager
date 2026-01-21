<?php

namespace App\Observers;

use App\Models\Lease;
use App\Services\FinanceCacheService;

class LeaseObserver
{
    private const DEPOSIT_FIELDS = [
        'deposit_amount',
        'deposit_status',
        'deposit_refund_amount',
    ];

    public function created(Lease $lease): void
    {
        $this->invalidateCache($lease);
    }

    public function updated(Lease $lease): void
    {
        if ($this->hasDepositFieldChanges($lease)) {
            $this->invalidateCache($lease);
        }
    }

    public function deleted(Lease $lease): void
    {
        $this->invalidateCache($lease);
    }

    private function invalidateCache(Lease $lease): void
    {
        if ($lease->landlord_id) {
            FinanceCacheService::invalidateForLandlord($lease->landlord_id);
        }
    }

    private function hasDepositFieldChanges(Lease $lease): bool
    {
        foreach (self::DEPOSIT_FIELDS as $field) {
            if ($lease->wasChanged($field)) {
                return true;
            }
        }

        return false;
    }
}
