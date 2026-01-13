<?php

namespace App\Exports;

use App\Models\Invoice;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class FinancialReportExport implements FromCollection, ShouldAutoSize, WithHeadings, WithStyles, WithTitle
{
    public function __construct(
        protected int $landlordId,
        protected array $dateRange
    ) {}

    public function collection(): Collection
    {
        $invoices = Invoice::where('landlord_id', $this->landlordId)
            ->whereBetween('due_date', [$this->dateRange['start'], $this->dateRange['end']])
            ->with(['lease.tenant', 'lease.unit.building'])
            ->get();

        return $invoices->map(fn ($invoice) => [
            'Invoice #' => $invoice->invoice_number,
            'Due Date' => $invoice->due_date->format('Y-m-d'),
            'Tenant' => $invoice->lease->tenant->name ?? 'N/A',
            'Unit' => $invoice->lease->unit->unit_number ?? 'N/A',
            'Building' => $invoice->lease->unit->building->name ?? 'N/A',
            'Rent Due' => $invoice->rent_due,
            'Water Due' => $invoice->water_due,
            'Arrears' => $invoice->arrears,
            'Wallet Applied' => $invoice->wallet_applied ?? 0,
            'Total Due' => $invoice->total_due,
            'Amount Paid' => $invoice->amount_paid,
            'Balance' => $invoice->total_due - $invoice->amount_paid,
            'Status' => ucfirst($invoice->status),
        ]);
    }

    public function headings(): array
    {
        return [
            'Invoice #',
            'Due Date',
            'Tenant',
            'Unit',
            'Building',
            'Rent Due (KES)',
            'Water Due (KES)',
            'Arrears (KES)',
            'Wallet Applied (KES)',
            'Total Due (KES)',
            'Amount Paid (KES)',
            'Balance (KES)',
            'Status',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }

    public function title(): string
    {
        return 'Financial Report';
    }
}
