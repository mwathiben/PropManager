<?php

namespace App\Exports;

use App\Services\RentRollService;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Phase-100 REPORTS-DEPTH-3: rent-roll xlsx, sourced from RentRollService so the
 * spreadsheet, the PDF, and the on-screen report never drift.
 */
class RentRollExport implements FromCollection, ShouldAutoSize, WithHeadings, WithStyles, WithTitle
{
    public function __construct(
        protected int $landlordId,
        protected string $currencyCode = 'KES',
        protected ?int $buildingId = null,
        protected ?int $propertyId = null,
    ) {}

    public function collection(): Collection
    {
        $report = app(RentRollService::class)->forLandlord($this->landlordId, $this->buildingId, $this->propertyId);

        return collect($report['rows'])->map(fn (array $r) => [
            'Property' => $r['property'] ?? 'N/A',
            'Building' => $r['building'] ?? 'N/A',
            'Unit' => $r['unit'],
            'Status' => ucfirst($r['status']),
            'Tenant' => $r['tenant'] ?? '-',
            'Rent' => $r['rent'],
            'Deposit Held' => $r['deposit_held'],
            'Wallet Credit' => $r['wallet_credit'],
            'Outstanding' => $r['outstanding'],
            'Lease Start' => $r['lease_start'] ?? '-',
            'Lease End' => $r['lease_end'] ?? '-',
        ]);
    }

    public function headings(): array
    {
        return [
            'Property',
            'Building',
            'Unit',
            'Status',
            'Tenant',
            "Rent ({$this->currencyCode})",
            "Deposit Held ({$this->currencyCode})",
            "Wallet Credit ({$this->currencyCode})",
            "Outstanding ({$this->currencyCode})",
            'Lease Start',
            'Lease End',
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
        return 'Rent Roll';
    }
}
