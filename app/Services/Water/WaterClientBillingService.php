<?php

declare(strict_types=1);

namespace App\Services\Water;

use App\Models\Invoice;
use App\Models\PaymentConfiguration;
use App\Models\WaterConnection;
use App\Models\WaterReading;
use App\Services\InvoiceService;
use Carbon\Carbon;
use Carbon\CarbonImmutable;

/**
 * Phase-97 WATER-CLIENT-BILLING / Phase-98 WATER-CLIENT-INVOICING-UNIFY: turns a
 * water client's consumption (or flat rate) into a real INVOICE for a period, at the
 * connection's effective rate (client_rate ?? landlord water_client_rate) via the
 * Phase-87 tariff engine — the same one invoicing system as tenants, not a parallel
 * track.
 *
 * THE TWO DEFERRED GUARDS (Phase 94/95): the biller must REFUSE — never coerce to
 * 0 — a connection with no effective rate, or a metered connection with no readable
 * meter. Such connections are returned as "skipped" with a reason so the landlord
 * can fix the config, not silently billed at zero.
 */
class WaterClientBillingService
{
    public function __construct(
        private WaterTariffService $tariffService,
        private InvoiceService $invoiceService,
    ) {}

    /**
     * Bill every active connection for a landlord for the given period.
     * Per-connection failures are isolated (one bad row never aborts the run).
     *
     * @return array{billed: list<Invoice>, skipped: list<array{connection_id:int, identifier:string, reason:string}>}
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
                $billed[] = $result['invoice'];
            } elseif ($result['status'] === 'skipped') {
                $skipped[] = ['connection_id' => $connection->id, 'identifier' => $connection->identifier, 'reason' => $result['reason']];
            }
        }

        return ['billed' => $billed, 'skipped' => $skipped];
    }

    /**
     * Bill one connection for one period. Idempotent: an already-billed period
     * returns the existing invoice. Returns a skip reason rather than a 0 charge
     * when the connection is misconfigured (no rate / metered-without-meter).
     *
     * @return array{status:string, reason?:string, invoice?:Invoice}
     */
    public function billConnection(WaterConnection $connection, CarbonImmutable $period): array
    {
        $period = $period->startOfMonth();

        $existing = Invoice::withoutGlobalScope('landlord')
            ->where('water_connection_id', $connection->id)
            ->whereYear('billing_period_start', $period->year)
            ->whereMonth('billing_period_start', $period->month)
            ->first();
        if ($existing !== null) {
            return ['status' => 'already_billed', 'invoice' => $existing];
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

        $invoice = $this->invoiceService->generateInvoiceForWaterConnection(
            $connection,
            Carbon::parse($period->toDateString()),
            (float) $waterDue,
            $consumption,
        );

        return ['status' => 'billed', 'invoice' => $invoice];
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
