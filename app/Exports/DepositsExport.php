<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class DepositsExport implements FromCollection, ShouldAutoSize, WithHeadings, WithStyles
{
    public function __construct(
        protected Collection $deposits,
        protected string $currencyCode = 'KES'
    ) {}

    public function collection(): Collection
    {
        return $this->deposits->map(fn ($lease) => [
            'Tenant' => $lease->tenant?->name ?? 'N/A',
            'Unit' => $lease->unit?->unit_number ?? 'N/A',
            'Building' => $lease->unit?->building?->name ?? 'N/A',
            'Deposit Amount' => $lease->deposit_amount,
            'Status' => ucfirst(str_replace('_', ' ', $lease->deposit_status)),
            'Refund Amount' => $lease->deposit_refund_amount ?? 0,
            'Deductions' => $lease->deposit_deductions ?? 0,
            'Deduction Reason' => $lease->deposit_deduction_reason ?? '',
            'Processed Date' => $lease->deposit_processed_at?->format('Y-m-d') ?? '',
            'Lease Start' => $lease->start_date?->format('Y-m-d') ?? '',
            'Lease End' => $lease->end_date?->format('Y-m-d') ?? '',
            'Active' => $lease->is_active ? 'Yes' : 'No',
        ]);
    }

    public function headings(): array
    {
        return [
            'Tenant',
            'Unit',
            'Building',
            "Deposit Amount ({$this->currencyCode})",
            'Status',
            "Refund Amount ({$this->currencyCode})",
            "Deductions ({$this->currencyCode})",
            'Deduction Reason',
            'Processed Date',
            'Lease Start',
            'Lease End',
            'Active',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
