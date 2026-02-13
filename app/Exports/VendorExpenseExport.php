<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class VendorExpenseExport implements FromCollection, ShouldAutoSize, WithHeadings, WithStyles
{
    public function __construct(
        protected Collection $vendors,
        protected array $dateRange,
        protected string $currencyCode = 'KES'
    ) {}

    public function collection(): Collection
    {
        return $this->vendors->map(fn ($v) => [
            'Vendor' => $v->name,
            'Contact Person' => $v->contact_person ?? 'N/A',
            'Email' => $v->email ?? 'N/A',
            'Phone' => $v->phone ?? 'N/A',
            'Total Expenses' => $v->expenses_sum_amount ?? 0,
            'Expense Count' => $v->expenses_count ?? 0,
            'Status' => $v->is_active ? 'Active' : 'Inactive',
        ]);
    }

    public function headings(): array
    {
        return [
            'Vendor',
            'Contact Person',
            'Email',
            'Phone',
            "Total Expenses ({$this->currencyCode})",
            'Expense Count',
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
