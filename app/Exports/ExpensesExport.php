<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ExpensesExport implements FromCollection, ShouldAutoSize, WithHeadings, WithStyles
{
    public function __construct(
        protected Collection $expenses,
        protected array $dateRange,
        protected string $currencyCode = 'KES'
    ) {}

    public function collection(): Collection
    {
        return $this->expenses->map(fn ($e) => [
            'Date' => $e->expense_date->format('Y-m-d'),
            'Description' => $e->description,
            'Category' => $e->category?->name ?? 'Uncategorized',
            'Vendor' => $e->vendor?->name ?? 'N/A',
            'Location' => $e->getLocationLabel(),
            'Amount' => $e->amount,
            'Payment Method' => $e->payment_method ? ucfirst(str_replace('_', ' ', $e->payment_method)) : 'N/A',
            'Reference' => $e->reference ?? '',
            'Recurring' => $e->is_recurring ? 'Yes' : 'No',
            'Notes' => $e->notes ?? '',
        ]);
    }

    public function headings(): array
    {
        return [
            'Date',
            'Description',
            'Category',
            'Vendor',
            'Location',
            "Amount ({$this->currencyCode})",
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
