<?php

namespace App\Observers;

use App\Models\Expense;
use App\Services\FinanceCacheService;

class ExpenseObserver
{
    public function created(Expense $expense): void
    {
        if ($expense->landlord_id) {
            FinanceCacheService::invalidateAndWarm($expense->landlord_id);
        }
    }

    public function updated(Expense $expense): void
    {
        if ($expense->landlord_id) {
            FinanceCacheService::invalidateForLandlord($expense->landlord_id);
        }
    }

    public function deleted(Expense $expense): void
    {
        if ($expense->landlord_id) {
            FinanceCacheService::invalidateForLandlord($expense->landlord_id);
        }
    }
}
