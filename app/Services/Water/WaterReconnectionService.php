<?php

declare(strict_types=1);

namespace App\Services\Water;

use App\Models\Meter;
use App\Models\PaymentConfiguration;
use App\Models\WaterPendingCharge;

/**
 * Phase-90 RECONNECT-FEE: on reconnect, record the configured reconnection fee as
 * a pending charge on the unit's active lease so the next invoice bills it.
 * Returns the fee charged (0 if none configured or no active lease).
 */
class WaterReconnectionService
{
    public function effectiveFee(Meter $meter): float
    {
        $building = $meter->building;
        $config = PaymentConfiguration::withoutGlobalScope('landlord')
            ->where('landlord_id', $meter->landlord_id)
            ->first();

        return (float) ($building?->water_reconnection_fee
            ?? $config?->water_reconnection_fee
            ?? 0);
    }

    public function chargeFee(Meter $meter): float
    {
        $fee = $this->effectiveFee($meter);
        if ($fee <= 0) {
            return 0.0;
        }

        $lease = $meter->unit?->activeLease;
        if (! $lease) {
            return 0.0; // nobody to charge (vacant unit)
        }

        WaterPendingCharge::create([
            'landlord_id' => $meter->landlord_id,
            'lease_id' => $lease->id,
            'amount' => $fee,
            'type' => 'reconnection_fee',
            'description' => 'Water reconnection fee',
        ]);

        return $fee;
    }
}
