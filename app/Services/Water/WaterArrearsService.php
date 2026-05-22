<?php

declare(strict_types=1);

namespace App\Services\Water;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use Illuminate\Support\Collection;

/**
 * Phase-90: water arrears = Overdue/Partial invoices that carry a water charge and
 * still have an outstanding balance. Water isn't separable from rent at payment
 * time (payments hit the invoice total), so "in water arrears" means the unpaid
 * invoice included water. Scope-free for cron use (filtered by landlord_id).
 */
class WaterArrearsService
{
    /**
     * @return Collection<int, Invoice>
     */
    public function overdueWaterInvoices(?int $landlordId = null): Collection
    {
        return Invoice::withoutGlobalScope('landlord')
            ->when($landlordId, fn ($q) => $q->where('landlord_id', $landlordId))
            ->whereIn('status', [InvoiceStatus::Overdue, InvoiceStatus::Partial])
            ->where('water_due', '>', 0)
            ->whereRaw('(total_due - amount_paid) > 0')
            ->with(['lease.tenant:id,name', 'lease.unit:id,unit_number,building_id', 'lease.unit.building:id,name'])
            ->orderBy('due_date')
            ->get();
    }
}
