<?php

namespace App\Exports;

use App\Models\Unit;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class OccupancyReportExport implements FromCollection, ShouldAutoSize, WithHeadings, WithStyles, WithTitle
{
    public function __construct(
        protected int $landlordId,
        protected string $currencyCode = 'KES'
    ) {}

    public function collection(): Collection
    {
        $units = Unit::whereHas('building', function ($q) {
            $q->where('landlord_id', $this->landlordId);
        })
            ->with(['building', 'activeLease.tenant'])
            ->orderBy('building_id')
            ->get();

        return $units->map(fn ($unit) => [
            'Building' => $unit->building->name ?? 'N/A',
            'Unit' => $unit->unit_number,
            'Status' => ucfirst($unit->status),
            'Target Rent' => $unit->target_rent ?? 0,
            'Current Rent' => $unit->activeLease?->rent_amount ?? 0,
            'Tenant' => $unit->activeLease?->tenant->name ?? '-',
            'Lease Start' => $unit->activeLease?->start_date?->format('Y-m-d') ?? '-',
            'Lease End' => $unit->activeLease?->end_date?->format('Y-m-d') ?? '-',
            'Arrears' => $unit->activeLease?->arrears ?? 0,
        ]);
    }

    public function headings(): array
    {
        return [
            'Building',
            'Unit',
            'Status',
            "Target Rent ({$this->currencyCode})",
            "Current Rent ({$this->currencyCode})",
            'Tenant',
            'Lease Start',
            'Lease End',
            "Arrears ({$this->currencyCode})",
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
        return 'Occupancy Report';
    }
}
