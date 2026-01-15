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

class StreamingExpensesExport implements FromQuery, ShouldAutoSize, ShouldQueue, WithHeadings, WithMapping, WithStyles
{
    use Exportable;

    public function __construct(
        protected Builder $query
    ) {}

    public function query(): Builder
    {
        return $this->query;
    }

    public function map($expense): array
    {
        return [
            $expense->expense_date->format('Y-m-d'),
            $expense->description,
            $expense->category?->name ?? 'Uncategorized',
            $expense->vendor?->name ?? 'N/A',
            $expense->getLocationLabel(),
            $expense->amount,
            $expense->payment_method ? ucfirst(str_replace('_', ' ', $expense->payment_method)) : 'N/A',
            $expense->reference ?? '',
            $expense->is_recurring ? 'Yes' : 'No',
            $expense->notes ?? '',
        ];
    }

    public function headings(): array
    {
        return [
            'Date',
            'Description',
            'Category',
            'Vendor',
            'Location',
            'Amount (KES)',
            'Payment Method',
            'Reference',
            'Recurring',
            'Notes',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
