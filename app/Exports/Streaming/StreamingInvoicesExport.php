<?php

namespace App\Exports\Streaming;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class StreamingInvoicesExport implements FromQuery, ShouldAutoSize, ShouldQueue, WithHeadings, WithMapping, WithStyles
{
    use Exportable;

    public function __construct(
        protected Builder $query,
        protected string $currencyCode = 'KES'
    ) {}

    public function query(): Builder
    {
        return $this->query;
    }

    public function map($invoice): array
    {
        return [
            $invoice->invoice_number,
            $invoice->created_at?->format('Y-m-d'),
            $invoice->due_date?->format('Y-m-d'),
            $invoice->lease->tenant->name ?? 'N/A',
            $invoice->lease->unit->unit_number ?? 'N/A',
            $invoice->lease->unit->building->name ?? 'N/A',
            $invoice->rent_amount,
            $invoice->water_charges,
            $invoice->arrears_amount,
            $invoice->total_due,
            $invoice->amount_paid,
            $invoice->total_due - $invoice->amount_paid,
            ucfirst($invoice->status),
        ];
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
