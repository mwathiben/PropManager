<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class InvoicesExport implements FromCollection, ShouldAutoSize, WithHeadings, WithStyles
{
    public function __construct(
        protected Collection $invoices,
        protected array $dateRange = [],
        protected string $currencyCode = 'KES'
    ) {}

    public function collection(): Collection
    {
        return $this->invoices->map(fn ($inv) => [
            'Invoice Number' => $inv->invoice_number,
            'Date' => $inv->created_at?->format('Y-m-d'),
            'Due Date' => $inv->due_date?->format('Y-m-d'),
            'Tenant' => $inv->lease->tenant->name ?? 'N/A',
            'Unit' => $inv->lease->unit->unit_number ?? 'N/A',
            'Building' => $inv->lease->unit->building->name ?? 'N/A',
            'Rent' => $inv->rent_amount,
            'Water' => $inv->water_charges,
            'Arrears' => $inv->arrears_amount,
            'Total Due' => $inv->total_due,
            'Amount Paid' => $inv->amount_paid,
            'Balance' => $inv->total_due - $inv->amount_paid,
            'Status' => ucfirst($inv->status),
        ]);
    }

    public function headings(): array
    {
        return [
            'Invoice Number',
            'Date',
            'Due Date',
            'Tenant',
            'Unit',
            'Building',
            "Rent ({$this->currencyCode})",
            "Water ({$this->currencyCode})",
            "Arrears ({$this->currencyCode})",
            "Total Due ({$this->currencyCode})",
            "Amount Paid ({$this->currencyCode})",
            "Balance ({$this->currencyCode})",
            'Status',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
