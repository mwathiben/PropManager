<?php

namespace App\Observers;

use App\Models\Expense;
use App\Services\FinanceCacheService;

class ExpenseObserver
{
    public function created(Expense $expense): void
    {
        $this->invalidateCache($expense);
    }

    public function updated(Expense $expense): void
    {
        $this->invalidateCache($expense);
    }

    public function deleted(Expense $expense): void
    {
        $this->invalidateCache($expense);
    }

    private function invalidateCache(Expense $expense): void
    {
        if ($expense->landlord_id) {
            FinanceCacheService::invalidateForLandlord($expense->landlord_id);
        }
    }
}
