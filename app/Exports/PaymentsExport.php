<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PaymentsExport implements FromCollection, ShouldAutoSize, WithHeadings, WithStyles
{
    public function __construct(
        protected Collection $payments,
        protected array $dateRange,
        protected string $currencyCode = 'KES'
    ) {}

    public function collection(): Collection
    {
        return $this->payments->map(function ($p) {
            $recipient = $p->invoice?->recipientLabel() ?? ['name' => null, 'context' => null];

            return [
                'Date' => $p->payment_date->format('Y-m-d'),
                'Reference' => $p->reference,
                'Tenant' => $p->lease?->tenant?->name ?? $recipient['name'] ?? 'N/A',
                'Unit' => $p->lease?->unit?->unit_number ?? $recipient['context'] ?? 'N/A',
                'Building' => $p->lease?->unit?->building?->name ?? 'N/A',
                'Amount' => $p->amount,
                'Method' => ucfirst(str_replace('_', ' ', $p->payment_method)),
                'Invoice' => $p->invoice?->invoice_number ?? 'N/A',
                'Status' => $p->invoice?->status ?? 'N/A',
            ];
        });
    }

    public function headings(): array
    {
        return ['Date', 'Reference', 'Tenant', 'Unit', 'Building', "Amount ({$this->currencyCode})", 'Method', 'Invoice', 'Status'];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
