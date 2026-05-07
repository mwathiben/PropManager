<?php

namespace App\Services\BulkOperations;

use App\Jobs\SendNotificationJob;
use App\Models\Lease;
use App\Models\RentHistory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BulkRentAdjuster
{
    private array $leaseIds;

    private int $landlordId;

    private string $adjustmentType = 'percentage';

    private float $adjustmentValue = 0;

    private string $reason = 'Bulk rent adjustment';

    private string $effectiveDate;

    private bool $notifyTenants = false;

    private array $results = [
        'total' => 0,
        'success' => 0,
        'failed' => 0,
        'errors' => [],
        'adjustments' => [],
    ];

    public static function forLeases(array $leaseIds, int $landlordId): self
    {
        $adjuster = new self;
        $adjuster->leaseIds = $leaseIds;
        $adjuster->landlordId = $landlordId;
        $adjuster->results['total'] = count($leaseIds);
        $adjuster->effectiveDate = now()->toDateString();

        return $adjuster;
    }

    private const ALLOWED_ADJUSTMENT_TYPES = ['percentage', 'fixed', 'absolute'];

    public function withAdjustmentType(string $type): self
    {
        if (! in_array($type, self::ALLOWED_ADJUSTMENT_TYPES, true)) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Invalid adjustment type "%s". Allowed types are: %s',
                    $type,
                    implode(', ', self::ALLOWED_ADJUSTMENT_TYPES)
                )
            );
        }

        $this->adjustmentType = $type;

        return $this;
    }

    public function withValue(float $value): self
    {
        $this->adjustmentValue = $value;

        return $this;
    }

    public function withReason(?string $reason): self
    {
        if ($reason) {
            $this->reason = $reason;
        }

        return $this;
    }

    public function withEffectiveDate(string $date): self
    {
        $this->effectiveDate = $date;

        return $this;
    }

    public function shouldNotifyTenants(bool $notify): self
    {
        $this->notifyTenants = $notify;

        return $this;
    }

    public function execute(): array
    {
        DB::beginTransaction();

        try {
            foreach ($this->leaseIds as $leaseId) {
                $this->processLease($leaseId);
            }

            DB::commit();

            return $this->results;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Bulk rent adjustment failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    private function processLease(int $leaseId): void
    {
        $lease = Lease::where('id', $leaseId)
            ->where('landlord_id', $this->landlordId)
            ->where('is_active', true)
            ->with('tenant:id,name')
            ->firstOrFail();

        $oldRent = $lease->rent_amount;
        $newRent = $this->calculateNewRent($oldRent);

        $lease->update(['rent_amount' => $newRent]);

        $this->recordRentHistory($lease, $oldRent, $newRent);

        if ($this->notifyTenants) {
            $this->notifyTenant($lease, $oldRent, $newRent);
        }

        $this->results['success']++;
        $this->results['adjustments'][] = [
            'lease_id' => $lease->id,
            'tenant' => $lease->tenant->name ?? 'Unknown',
            'old_rent' => $oldRent,
            'new_rent' => $newRent,
        ];
    }

    private function calculateNewRent(float $currentRent): float
    {
        $newRent = match ($this->adjustmentType) {
            self::ALLOWED_ADJUSTMENT_TYPES[0] => $currentRent * (1 + ($this->adjustmentValue / 100)), // percentage
            self::ALLOWED_ADJUSTMENT_TYPES[1] => $currentRent + $this->adjustmentValue, // fixed
            self::ALLOWED_ADJUSTMENT_TYPES[2] => $this->adjustmentValue, // absolute
            default => throw new \InvalidArgumentException(
                sprintf(
                    'Invalid adjustment type "%s". Allowed types are: %s',
                    $this->adjustmentType,
                    implode(', ', self::ALLOWED_ADJUSTMENT_TYPES)
                )
            ),
        };

        return max(0, round($newRent, 2));
    }

    private function recordRentHistory(Lease $lease, float $oldRent, float $newRent): void
    {
        RentHistory::create([
            'lease_id' => $lease->id,
            'old_amount' => $oldRent,
            'new_amount' => $newRent,
            'reason' => $this->reason,
            'effective_date' => $this->effectiveDate,
        ]);
    }

    private function notifyTenant(Lease $lease, float $oldRent, float $newRent): void
    {
        $tenantName = $lease->tenant->name ?? 'Tenant';
        $currency = config('app.currency', 'KES');

        dispatch(SendNotificationJob::forNew(
            $lease->tenant_id,
            'rent_hike',
            'Rent Adjustment Notice',
            sprintf(
                "Hello %s,\n\nThis is to inform you that your rent will be adjusted from %s %s to %s %s effective %s.\n\nReason: %s\n\nThank you for your understanding.",
                $tenantName,
                $currency,
                number_format($oldRent, 2),
                $currency,
                number_format($newRent, 2),
                $this->effectiveDate,
                $this->reason
            ),
            [
                'old_rent' => $oldRent,
                'new_rent' => $newRent,
                'effective_date' => $this->effectiveDate,
                'currency' => $currency,
            ],
            $this->landlordId
        ))->afterCommit();
    }
}
