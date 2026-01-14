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

class StreamingPaymentsExport implements FromQuery, ShouldAutoSize, ShouldQueue, WithHeadings, WithMapping, WithStyles
{
    use Exportable;

    public function __construct(
        protected Builder $query
    ) {}

    public function query(): Builder
    {
        return $this->query;
    }

    public function map($payment): array
    {
        return [
            $payment->payment_date?->format('Y-m-d'),
            $payment->reference ?? '',
            $payment->lease->tenant->name ?? 'N/A',
            $payment->lease->unit->unit_number ?? 'N/A',
            $payment->lease->unit->building->name ?? 'N/A',
            $payment->amount,
            ucfirst(str_replace('_', ' ', $payment->payment_method)),
            $payment->invoice->invoice_number ?? 'Unallocated',
            $payment->status ?? 'completed',
        ];
    }

    public function headings(): array
    {
        return [
            'Date',
            'Reference',
            'Tenant',
            'Unit',
            'Building',
            'Amount (KES)',
            'Method',
            'Invoice',
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
