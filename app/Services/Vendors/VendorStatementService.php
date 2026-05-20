<?php

declare(strict_types=1);

namespace App\Services\Vendors;

use App\Models\Expense;
use App\Models\TicketCost;
use App\Models\Vendor;
use Illuminate\Support\Carbon;

/**
 * Phase-70 PAYOUT-STATEMENT-1: a read-only statement of what has been
 * recorded against a vendor over a period — vendor-category ticket costs
 * on tickets assigned to them, plus expenses linked to them. This is a
 * record, NOT a payout (Stripe Connect is landlord settlement, not vendor
 * disbursement). All amounts normalised to integer cents.
 */
class VendorStatementService
{
    /**
     * @return array{
     *   ticket_costs: array<int, array{ticket_id:int, title:string, amount_cents:int, recorded_at:?string}>,
     *   expenses: array<int, array{id:int, description:?string, amount_cents:int, expense_date:?string}>,
     *   ticket_costs_total_cents:int, expenses_total_cents:int, total_cents:int
     * }
     */
    public function forVendor(Vendor $vendor, Carbon $from, Carbon $to): array
    {
        $ticketCosts = TicketCost::query()
            ->where('ticket_costs.category', 'vendor')
            ->whereBetween('ticket_costs.recorded_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->whereHas('ticket', fn ($q) => $q->withoutGlobalScopes()->where('vendor_id', $vendor->id))
            ->with('ticket:id,title')
            ->orderBy('ticket_costs.recorded_at')
            ->get()
            ->map(fn (TicketCost $c) => [
                'ticket_id' => (int) $c->ticket_id,
                'title' => (string) ($c->ticket?->title ?? ''),
                'amount_cents' => (int) $c->amount_cents,
                'recorded_at' => $c->recorded_at?->toIso8601String(),
            ]);

        $expenses = Expense::query()
            ->where('vendor_id', $vendor->id)
            ->whereBetween('expense_date', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->orderBy('expense_date')
            ->get()
            ->map(fn (Expense $e) => [
                'id' => (int) $e->id,
                'description' => $e->description,
                'amount_cents' => (int) round(((float) $e->amount) * 100),
                'expense_date' => optional($e->expense_date)->toDateString(),
            ]);

        $ticketCostsTotal = (int) $ticketCosts->sum('amount_cents');
        $expensesTotal = (int) $expenses->sum('amount_cents');

        return [
            'ticket_costs' => $ticketCosts->values()->all(),
            'expenses' => $expenses->values()->all(),
            'ticket_costs_total_cents' => $ticketCostsTotal,
            'expenses_total_cents' => $expensesTotal,
            'total_cents' => $ticketCostsTotal + $expensesTotal,
        ];
    }

    /**
     * @param  array<string, mixed>  $statement  output of forVendor()
     */
    public function toCsv(array $statement): string
    {
        $buffer = "\xEF\xBB\xBF";
        $buffer .= "type,reference,description,amount_kes,date\n";

        foreach ($statement['ticket_costs'] as $row) {
            $buffer .= $this->line([
                'ticket_cost',
                'Ticket #'.$row['ticket_id'],
                $row['title'],
                number_format($row['amount_cents'] / 100, 2, '.', ''),
                $row['recorded_at'] ?? '',
            ]);
        }

        foreach ($statement['expenses'] as $row) {
            $buffer .= $this->line([
                'expense',
                'Expense #'.$row['id'],
                (string) ($row['description'] ?? ''),
                number_format($row['amount_cents'] / 100, 2, '.', ''),
                $row['expense_date'] ?? '',
            ]);
        }

        return $buffer;
    }

    /**
     * @param  array<int, string>  $fields
     */
    private function line(array $fields): string
    {
        return implode(',', array_map(fn (string $f) => $this->escape($f), $fields))."\n";
    }

    private function escape(string $value): string
    {
        if ($value === '') {
            return '';
        }

        // CSV-injection guard (Phase-65 convention): neutralise formula-leading
        // characters so a malicious title/description can't execute in Excel.
        if (in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            $value = "'".$value;
        }

        if (str_contains($value, ',') || str_contains($value, '"') || str_contains($value, "\n") || str_contains($value, "\r")) {
            return '"'.str_replace('"', '""', $value).'"';
        }

        return $value;
    }
}
