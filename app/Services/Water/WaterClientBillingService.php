<?php

declare(strict_types=1);

namespace App\Services\Water;

use App\Models\PaymentConfiguration;
use App\Models\WaterClientCharge;
use App\Models\WaterConnection;
use App\Models\WaterReading;
use Carbon\CarbonImmutable;

/**
 * Phase-97 WATER-CLIENT-BILLING: turns a water client's consumption (or flat rate)
 * into a WaterClientCharge for a period, at the connection's effective rate
 * (client_rate ?? landlord water_client_rate) via the Phase-87 tariff engine.
 *
 * THE TWO DEFERRED GUARDS (Phase 94/95): the biller must REFUSE — never coerce to
 * 0 — a connection with no effective rate, or a metered connection with no readable
 * meter. Such connections are returned as "skipped" with a reason so the landlord
 * can fix the config, not silently billed at zero.
 */
class WaterClientBillingService
{
    public function __construct(private WaterTariffService $tariffService) {}

    /**
     * Bill every active connection for a landlord for the given period.
     * Per-connection failures are isolated (one bad row never aborts the run).
     *
     * @return array{billed: list<WaterClientCharge>, skipped: list<array{connection_id:int, identifier:string, reason:string}>}
     */
    public function billForPeriod(int $landlordId, CarbonImmutable $period): array
    {
        $period = $period->startOfMonth();

        $connections = WaterConnection::query()
            ->withoutGlobalScope('landlord') // cron has no auth; keep SoftDeletes active
            ->where('landlord_id', $landlordId)
            ->where('status', 'active')
            ->get();

        $billed = [];
        $skipped = [];

        foreach ($connections as $connection) {
            try {
                $result = $this->billConnection($connection, $period);
            } catch (\Throwable $e) {
                report($e);
                $skipped[] = ['connection_id' => $connection->id, 'identifier' => $connection->identifier, 'reason' => 'error'];

                continue;
            }

            if ($result['status'] === 'billed') {
                $billed[] = $result['charge'];
            } elseif ($result['status'] === 'skipped') {
                $skipped[] = ['connection_id' => $connection->id, 'identifier' => $connection->identifier, 'reason' => $result['reason']];
            }
        }

        return ['billed' => $billed, 'skipped' => $skipped];
    }

    /**
     * Bill one connection for one period. Idempotent: an already-billed period
     * returns the existing charge. Returns a skip reason rather than a 0 charge
     * when the connection is misconfigured (no rate / metered-without-meter).
     *
     * @return array{status:string, reason?:string, charge?:WaterClientCharge}
     */
    public function billConnection(WaterConnection $connection, CarbonImmutable $period): array
    {
        $period = $period->startOfMonth();

        $existing = WaterClientCharge::withoutGlobalScope('landlord') // keep SoftDeletes
            ->where('water_connection_id', $connection->id)
            ->where('billing_period_start', $period->toDateString())
            ->first();
        if ($existing !== null) {
            return ['status' => 'already_billed', 'charge' => $existing];
        }

        // GUARD A: no effective rate -> refuse (never coerce to 0).
        $rate = $this->effectiveRate($connection);
        if ($rate === null) {
            return ['status' => 'skipped', 'reason' => 'no_rate'];
        }

        if ($connection->billing_mode === 'metered') {
            // GUARD B: metered requires a readable meter (scoped, soft-delete-aware).
            $meter = $connection->meter;
            if ($meter === null) {
                return ['status' => 'skipped', 'reason' => 'metered_no_meter'];
            }

            $consumption = $this->periodConsumption($connection, $meter->id, $period);
            if ($consumption <= 0) {
                // Nothing read this period — no charge (no minimum floor, like tenants).
                return ['status' => 'skipped', 'reason' => 'no_consumption'];
            }

            $waterDue = $this->tariffService->computeConsumptionCharge($consumption, ['unit_rate' => $rate]);
        } else {
            // Flat-rate line: the agreed rate is the period charge; no meter needed.
            $consumption = null;
            $waterDue = round($rate, 2);
        }

        if ($waterDue <= 0) {
            return ['status' => 'skipped', 'reason' => 'nothing_to_bill'];
        }

        $charge = WaterClientCharge::create([
            'landlord_id' => $connection->landlord_id,
            'water_connection_id' => $connection->id,
            'billing_period_start' => $period->toDateString(),
            'consumption' => $consumption,
            'water_due' => $waterDue,
            'amount_paid' => 0,
            'status' => 'due',
            'due_date' => $period->addMonthNoOverflow()->addDays(13)->toDateString(),
        ]);

        return ['status' => 'billed', 'charge' => $charge];
    }

    /**
     * Record a payment a water client made (the landlord logs cash/M-Pesa received),
     * applied across the connection's unpaid charges oldest-first. Returns the amount
     * applied (a leftover is an overpayment the caller can surface). Transactional.
     */
    public function applyPayment(WaterConnection $connection, float $amount): float
    {
        if ($amount <= 0) {
            return 0.0;
        }

        return (float) \Illuminate\Support\Facades\DB::transaction(function () use ($connection, $amount) {
            $charges = WaterClientCharge::withoutGlobalScope('landlord') // keep SoftDeletes
                ->where('water_connection_id', $connection->id)
                ->where('status', '!=', 'voided')
                ->whereColumn('amount_paid', '<', 'water_due')
                ->orderBy('billing_period_start')
                ->lockForUpdate()
                ->get();

            $remaining = round($amount, 2);
            $applied = 0.0;

            foreach ($charges as $charge) {
                if ($remaining <= 0) {
                    break;
                }
                $balance = $charge->balance();
                $pay = min($remaining, $balance);
                $charge->amount_paid = round((float) $charge->amount_paid + $pay, 2);
                $charge->status = $charge->deriveStatus();
                $charge->save();
                $remaining = round($remaining - $pay, 2);
                $applied = round($applied + $pay, 2);
            }

            return $applied;
        });
    }

    /**
     * The connection's effective per-unit (metered) or per-period (flat) rate:
     * the connection's own client_rate, else the landlord's water_client_rate.
     * Null when neither is configured — the caller MUST refuse to bill.
     */
    public function effectiveRate(WaterConnection $connection): ?float
    {
        // A non-positive rate is functionally "unset" — you cannot bill a water client
        // at 0/unit. Treat it as no-rate so the guard refuses rather than silently
        // billing 0 (and hiding it under the 'nothing_to_bill' skip).
        if ($connection->client_rate !== null && (float) $connection->client_rate > 0) {
            return (float) $connection->client_rate;
        }

        $rate = PaymentConfiguration::where('landlord_id', $connection->landlord_id)->value('water_client_rate');

        return ($rate !== null && (float) $rate > 0) ? (float) $rate : null;
    }

    /**
     * Approved consumption on the connection's meter within the period, bounded by
     * connected_at + landlord_id so a re-used meter never leaks a prior occupant's
     * usage (the Phase-93 cross-account lesson).
     */
    private function periodConsumption(WaterConnection $connection, int $meterId, CarbonImmutable $period): float
    {
        $floor = $connection->connected_at?->toDateString();

        return (float) WaterReading::query()
            ->withoutGlobalScope('landlord')
            ->where('meter_id', $meterId)
            ->where('landlord_id', $connection->landlord_id)
            ->where('status', 'approved')
            ->when($floor !== null, fn ($q) => $q->where('reading_date', '>=', $floor))
            ->whereBetween('reading_date', [$period->toDateString(), $period->endOfMonth()->toDateString()])
            ->sum('consumption');
    }
}
