<?php

declare(strict_types=1);

namespace App\Traits;

use App\Exceptions\AccountingPeriodLockedException;
use App\Models\AccountingPeriod;

/**
 * Phase-30 INT-PERIOD-LOCK-2: write-guard trait for finance models.
 * Hooks the Eloquent `saving` and `deleting` events; if the row's
 * accounting-effective date falls inside a CLOSED AccountingPeriod
 * for that landlord, throw AccountingPeriodLockedException.
 *
 * The host model declares accountingPeriodDateColumn() (e.g. Invoice
 * returns 'created_at', Payment returns 'payment_date', Expense
 * returns 'expense_date'). It also declares the landlord_id column
 * — defaults to 'landlord_id'.
 *
 * Status-only updates that don't move money (e.g. flipping
 * status='sent' to 'viewed') still happen on the same row, so any
 * change to a locked row is rejected. If callers need to project
 * post-close adjustments, the right answer is a CREDIT NOTE in the
 * current open period, not an in-place edit.
 */
trait EnforcesAccountingPeriodLock
{
    public static function bootEnforcesAccountingPeriodLock(): void
    {
        static::saving(function ($model): void {
            $model->guardAccountingPeriodLock();
        });
        static::deleting(function ($model): void {
            $model->guardAccountingPeriodLock();
        });
    }

    protected function guardAccountingPeriodLock(): void
    {
        $landlordId = (int) ($this->{$this->accountingPeriodLandlordColumn()} ?? 0);
        if ($landlordId <= 0) {
            return;
        }
        $column = $this->accountingPeriodDateColumn();
        $value = $this->getAttribute($column);
        if ($value === null) {
            return;
        }
        $effectiveDate = $value instanceof \DateTimeInterface
            ? $value->format('Y-m-d')
            : (string) $value;

        if (AccountingPeriod::isDateLocked($landlordId, $effectiveDate)) {
            throw AccountingPeriodLockedException::forModel(static::class, $effectiveDate);
        }
    }

    protected function accountingPeriodLandlordColumn(): string
    {
        return 'landlord_id';
    }

    abstract protected function accountingPeriodDateColumn(): string;
}
