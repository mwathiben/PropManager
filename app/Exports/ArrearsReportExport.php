<?php

namespace App\Exports;

use App\Models\Lease;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ArrearsReportExport implements FromCollection, ShouldAutoSize, WithHeadings, WithStyles, WithTitle
{
    public function __construct(
        protected int $landlordId
    ) {}

    public function collection(): Collection
    {
        $leases = Lease::where('landlord_id', $this->landlordId)
            ->where('is_active', true)
            ->where('arrears', '>', 0)
            ->with(['tenant', 'unit.building'])
            ->orderBy('arrears', 'desc')
            ->get();

        return $leases->map(fn ($lease) => [
            'Tenant' => $lease->tenant->name ?? 'N/A',
            'Email' => $lease->tenant->email ?? 'N/A',
            'Phone' => $lease->tenant->mobile_number ?? 'N/A',
            'Unit' => $lease->unit->unit_number ?? 'N/A',
            'Building' => $lease->unit->building->name ?? 'N/A',
            'Monthly Rent' => $lease->rent_amount,
            'Arrears' => $lease->arrears,
            'Months Overdue' => $lease->rent_amount > 0
                ? round($lease->arrears / $lease->rent_amount, 1)
                : 0,
            'Lease Start' => $lease->start_date->format('Y-m-d'),
            'Wallet Balance' => $lease->wallet_balance,
        ]);
    }

    public function headings(): array
    {
        return [
            'Tenant',
            'Email',
            'Phone',
            'Unit',
            'Building',
            'Monthly Rent (KES)',
            'Arrears (KES)',
            'Months Overdue',
            'Lease Start',
            'Wallet Balance (KES)',
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
        return 'Arrears Report';
    }
}
