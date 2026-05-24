<?php

namespace App\Exports;

use App\Services\PropertyPnlService;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Phase-100 REPORTS-DEPTH-3: per-property P&L xlsx, sourced from PropertyPnlService so
 * the spreadsheet matches the PDF/CSV/on-screen figures.
 */
class PropertyPnlExport implements FromCollection, ShouldAutoSize, WithHeadings, WithStyles, WithTitle
{
    public function __construct(
        protected int $landlordId,
        protected CarbonInterface $start,
        protected CarbonInterface $end,
        protected string $currencyCode = 'KES',
        protected ?int $propertyId = null,
    ) {}

    public function collection(): Collection
    {
        $report = app(PropertyPnlService::class)->forLandlord($this->landlordId, $this->start, $this->end, $this->propertyId);

        return collect($report['rows'])->map(fn (array $r) => [
            'Property' => $r['property'],
            'Collected' => $r['collected'],
            'Expenses' => $r['expenses'],
            'Net' => $r['net'],
            'Margin %' => $r['margin'],
        ]);
    }

    public function headings(): array
    {
        return [
            'Property',
            "Collected ({$this->currencyCode})",
            "Expenses ({$this->currencyCode})",
            "Net ({$this->currencyCode})",
            'Margin %',
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
        return 'Property P&L';
    }
}
