<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Phase-28 TENANT-STATEMENT-1: chronological charges + payments + running
 * balance for a tenant's statement view.
 *
 * Charges = sum of Invoice rows attached to any of the tenant's leases
 * (excluding voided invoices) keyed on billing_period_start. Payments =
 * Payment rows on those same leases (excluding voided) keyed on
 * payment_date. Opening balance for the window = sum(charges before
 * $from) - sum(payments before $from); running balance walks each row
 * accumulating in PHP because MySQL 5.7 (deployment env) lacks window
 * functions, and the row count is bounded by the tenant's per-period
 * activity so the cost is negligible.
 */
class StatementService
{
    /**
     * Phase-45 STATEMENT-DEPTH-2: optional row-level filters supported
     * via the $filters parameter. {types: ['charge','payment']} restricts
     * the chronological event stream; {min_amount, max_amount} clamp on
     * the absolute amount (charge OR payment whichever is non-zero).
     * Opening/closing rows always render so the running balance stays
     * intact even under filtering.
     *
     * @param  array{types?: list<string>, min_amount?: float, max_amount?: float}  $filters
     * @return Collection<int, array{
     *     date: string,
     *     description: string,
     *     reference: string|null,
     *     charge: float,
     *     payment: float,
     *     running_balance: float,
     *     kind: 'opening'|'invoice'|'payment'|'closing',
     * }>
     */
    public function forTenant(User $tenant, CarbonImmutable $from, CarbonImmutable $to, array $filters = []): Collection
    {
        $leaseIds = $tenant->leases()->pluck('id');

        if ($leaseIds->isEmpty()) {
            return collect([
                $this->openingRow($from, 0.0),
                $this->closingRow($to, 0.0),
            ]);
        }

        $openingBalance = $this->chargeTotalBefore($leaseIds, $from)
            - $this->paymentTotalBefore($leaseIds, $from);

        $invoices = Invoice::whereIn('lease_id', $leaseIds)
            ->whereNull('voided_at')
            ->whereBetween('billing_period_start', [$from->toDateString(), $to->toDateString()])
            ->orderBy('billing_period_start')
            ->orderBy('id')
            ->get(['id', 'invoice_number', 'billing_period_start', 'total_due']);

        $payments = Payment::whereIn('lease_id', $leaseIds)
            ->where('is_voided', false)
            ->whereBetween('payment_date', [$from->toDateString(), $to->toDateString()])
            ->orderBy('payment_date')
            ->orderBy('id')
            ->get(['id', 'reference', 'payment_date', 'amount']);

        $events = $invoices
            ->map(fn (Invoice $inv) => [
                'sort' => $inv->billing_period_start->toDateString().sprintf('-1-%010d', $inv->id),
                'date' => $inv->billing_period_start->toDateString(),
                'description' => __('tenant.statement.invoice_description', ['number' => $inv->invoice_number]),
                'reference' => $inv->invoice_number,
                'charge' => (float) $inv->total_due,
                'payment' => 0.0,
                'kind' => 'invoice',
            ])
            ->concat($payments->map(fn (Payment $p) => [
                'sort' => $p->payment_date->toDateString().sprintf('-2-%010d', $p->id),
                'date' => $p->payment_date->toDateString(),
                'description' => __('tenant.statement.payment_description'),
                'reference' => $p->reference,
                'charge' => 0.0,
                'payment' => (float) $p->amount,
                'kind' => 'payment',
            ]))
            ->sortBy('sort')
            ->values();

        $balance = $openingBalance;
        $rows = collect([$this->openingRow($from, $openingBalance)]);

        $allowedKinds = ! empty($filters['types']) ? array_intersect(
            ['invoice', 'payment'],
            array_map(static fn (string $t): string => match ($t) {
                'charge' => 'invoice',
                default => $t,
            }, $filters['types']),
        ) : null;
        $minAmount = isset($filters['min_amount']) ? (float) $filters['min_amount'] : null;
        $maxAmount = isset($filters['max_amount']) ? (float) $filters['max_amount'] : null;

        foreach ($events as $event) {
            $balance += $event['charge'] - $event['payment'];

            // Running balance must walk EVERY event to stay correct,
            // but only events matching the filter are emitted.
            if ($allowedKinds !== null && ! in_array($event['kind'], $allowedKinds, true)) {
                continue;
            }
            $amount = $event['charge'] !== 0.0 ? $event['charge'] : $event['payment'];
            if ($minAmount !== null && $amount < $minAmount) {
                continue;
            }
            if ($maxAmount !== null && $amount > $maxAmount) {
                continue;
            }

            $rows->push([
                'date' => $event['date'],
                'description' => $event['description'],
                'reference' => $event['reference'],
                'charge' => $event['charge'],
                'payment' => $event['payment'],
                'running_balance' => round($balance, 2),
                'kind' => $event['kind'],
            ]);
        }

        $rows->push($this->closingRow($to, $balance));

        return $rows;
    }

    /**
     * Phase-45 STATEMENT-DEPTH-1: monthly subtotals for a multi-period
     * window. Used by the xlsx export "Monthly Summary" sheet. Returns
     * one row per calendar month in [$from, $to] with charges, payments,
     * net (charges - payments), and the running closing balance at month-end.
     *
     * @return Collection<int, array{
     *     month: string,
     *     charges: float,
     *     payments: float,
     *     net: float,
     *     closing_balance: float,
     * }>
     */
    public function monthlySubtotals(User $tenant, CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        $leaseIds = $tenant->leases()->pluck('id');
        if ($leaseIds->isEmpty()) {
            return collect();
        }

        $openingBalance = $this->chargeTotalBefore($leaseIds, $from)
            - $this->paymentTotalBefore($leaseIds, $from);

        $balance = $openingBalance;
        $months = collect();
        $cursor = $from->startOfMonth();
        $end = $to->startOfMonth();

        while ($cursor->lessThanOrEqualTo($end)) {
            $monthStart = $cursor->startOfMonth();
            $monthEnd = $cursor->endOfMonth();
            $clampedTo = $monthEnd->greaterThan($to) ? $to : $monthEnd;

            $charges = (float) Invoice::whereIn('lease_id', $leaseIds)
                ->whereNull('voided_at')
                ->whereBetween('billing_period_start', [$monthStart->toDateString(), $clampedTo->toDateString()])
                ->sum('total_due');

            $payments = (float) Payment::whereIn('lease_id', $leaseIds)
                ->where('is_voided', false)
                ->whereBetween('payment_date', [$monthStart->toDateString(), $clampedTo->toDateString()])
                ->sum('amount');

            $balance += $charges - $payments;

            $months->push([
                'month' => $monthStart->format('Y-m'),
                'charges' => round($charges, 2),
                'payments' => round($payments, 2),
                'net' => round($charges - $payments, 2),
                'closing_balance' => round($balance, 2),
            ]);

            $cursor = $cursor->addMonth();
        }

        return $months;
    }

    private function chargeTotalBefore(Collection $leaseIds, CarbonImmutable $from): float
    {
        return (float) Invoice::whereIn('lease_id', $leaseIds)
            ->whereNull('voided_at')
            ->where('billing_period_start', '<', $from->toDateString())
            ->sum('total_due');
    }

    private function paymentTotalBefore(Collection $leaseIds, CarbonImmutable $from): float
    {
        return (float) Payment::whereIn('lease_id', $leaseIds)
            ->where('is_voided', false)
            ->where('payment_date', '<', $from->toDateString())
            ->sum('amount');
    }

    /**
     * @return array{date: string, description: string, reference: null, charge: float, payment: float, running_balance: float, kind: 'opening'}
     */
    private function openingRow(CarbonImmutable $from, float $balance): array
    {
        return [
            'date' => $from->toDateString(),
            'description' => __('tenant.statement.opening_balance'),
            'reference' => null,
            'charge' => 0.0,
            'payment' => 0.0,
            'running_balance' => round($balance, 2),
            'kind' => 'opening',
        ];
    }

    /**
     * @return array{date: string, description: string, reference: null, charge: float, payment: float, running_balance: float, kind: 'closing'}
     */
    private function closingRow(CarbonImmutable $to, float $balance): array
    {
        return [
            'date' => $to->toDateString(),
            'description' => __('tenant.statement.closing_balance'),
            'reference' => null,
            'charge' => 0.0,
            'payment' => 0.0,
            'running_balance' => round($balance, 2),
            'kind' => 'closing',
        ];
    }
}
