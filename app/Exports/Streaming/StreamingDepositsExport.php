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

class StreamingDepositsExport implements FromQuery, ShouldAutoSize, ShouldQueue, WithHeadings, WithMapping, WithStyles
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

    public function map($lease): array
    {
        return [
            $lease->tenant?->name ?? 'N/A',
            $lease->unit?->unit_number ?? 'N/A',
            $lease->unit?->building?->name ?? 'N/A',
            $lease->deposit_amount,
            ucfirst(str_replace('_', ' ', $lease->deposit_status)),
            $lease->deposit_refund_amount ?? 0,
            $lease->deposit_deductions ?? 0,
            $lease->deposit_deduction_reason ?? '',
            $lease->deposit_processed_at?->format('Y-m-d') ?? '',
            $lease->start_date?->format('Y-m-d') ?? '',
            $lease->end_date?->format('Y-m-d') ?? '',
            $lease->is_active ? 'Yes' : 'No',
        ];
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
