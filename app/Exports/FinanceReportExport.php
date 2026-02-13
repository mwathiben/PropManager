<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class FinanceReportExport implements WithMultipleSheets
{
    public function __construct(
        protected array $data,
        protected int $period,
        protected string $currencyCode = 'KES'
    ) {}

    public function sheets(): array
    {
        return [
            new RevenueSheet($this->data['revenue'], $this->currencyCode),
            new CollectionRateSheet($this->data['collection_rate'], $this->currencyCode),
            new OccupancySheet($this->data['occupancy']),
            new ArrearsAgingSheet($this->data['arrears_aging'], $this->currencyCode),
            new ExpensesByCategorySheet($this->data['expenses_by_category'], $this->currencyCode),
        ];
    }
}

class RevenueSheet implements FromArray, ShouldAutoSize, WithStyles, WithTitle
{
    public function __construct(protected array $data, protected string $currencyCode = 'KES') {}

    public function array(): array
    {
        $c = $this->currencyCode;
        $rows = [['Month', "Invoiced ({$c})", "Collected ({$c})", "Expenses ({$c})", "Net Income ({$c})"]];

        foreach ($this->data as $row) {
            $rows[] = [
                $row['month'],
                $row['invoiced'],
                $row['collected'],
                $row['expenses'],
                $row['net'],
            ];
        }

        $totals = array_reduce($this->data, function ($carry, $row) {
            $carry['invoiced'] += $row['invoiced'];
            $carry['collected'] += $row['collected'];
            $carry['expenses'] += $row['expenses'];
            $carry['net'] += $row['net'];

            return $carry;
        }, ['invoiced' => 0, 'collected' => 0, 'expenses' => 0, 'net' => 0]);

        $rows[] = ['Total', $totals['invoiced'], $totals['collected'], $totals['expenses'], $totals['net']];

        return $rows;
    }

    public function styles(Worksheet $sheet): array
    {
        $lastRow = count($this->data) + 2;

        return [
            1 => ['font' => ['bold' => true]],
            $lastRow => ['font' => ['bold' => true]],
        ];
    }

    public function title(): string
    {
        return 'Revenue';
    }
}

class CollectionRateSheet implements FromArray, ShouldAutoSize, WithStyles, WithTitle
{
    public function __construct(protected array $data, protected string $currencyCode = 'KES') {}

    public function array(): array
    {
        $c = $this->currencyCode;
        $rows = [['Month', "Invoiced ({$c})", "Collected ({$c})", 'Collection Rate (%)']];

        foreach ($this->data as $row) {
            $rows[] = [
                $row['month'],
                $row['invoiced'],
                $row['collected'],
                $row['rate'],
            ];
        }

        return $rows;
    }

    public function styles(Worksheet $sheet): array
    {
        return [1 => ['font' => ['bold' => true]]];
    }

    public function title(): string
    {
        return 'Collection Rate';
    }
}

class OccupancySheet implements FromArray, ShouldAutoSize, WithStyles, WithTitle
{
    public function __construct(protected array $data) {}

    public function array(): array
    {
        $rows = [['Building', 'Total Units', 'Occupied', 'Vacant', 'Occupancy Rate (%)']];

        foreach ($this->data['buildings'] ?? [] as $row) {
            $rows[] = [
                $row['building'],
                $row['total_units'],
                $row['occupied'],
                $row['vacant'],
                $row['occupancy_rate'],
            ];
        }

        if (isset($this->data['totals'])) {
            $rows[] = [
                'Total',
                $this->data['totals']['total_units'],
                $this->data['totals']['occupied'],
                $this->data['totals']['vacant'],
                $this->data['totals']['occupancy_rate'],
            ];
        }

        return $rows;
    }

    public function styles(Worksheet $sheet): array
    {
        $lastRow = count($this->data['buildings'] ?? []) + 2;

        return [
            1 => ['font' => ['bold' => true]],
            $lastRow => ['font' => ['bold' => true]],
        ];
    }

    public function title(): string
    {
        return 'Occupancy';
    }
}

class ArrearsAgingSheet implements FromArray, ShouldAutoSize, WithStyles, WithTitle
{
    public function __construct(protected array $data, protected string $currencyCode = 'KES') {}

    public function array(): array
    {
        $rows = [['Aging Bucket', 'Invoice Count', "Amount ({$this->currencyCode})"]];

        $buckets = [
            'current' => 'Current (Not Overdue)',
            '1-30' => '1-30 Days Overdue',
            '31-60' => '31-60 Days Overdue',
            '61-90' => '61-90 Days Overdue',
            '90+' => '90+ Days Overdue',
        ];

        $totalCount = 0;
        $totalAmount = 0;

        foreach ($buckets as $key => $label) {
            $count = $this->data[$key]['count'] ?? 0;
            $amount = $this->data[$key]['amount'] ?? 0;
            $rows[] = [$label, $count, $amount];
            $totalCount += $count;
            $totalAmount += $amount;
        }

        $rows[] = ['Total', $totalCount, $totalAmount];

        return $rows;
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
            7 => ['font' => ['bold' => true]],
        ];
    }

    public function title(): string
    {
        return 'Arrears Aging';
    }
}

class ExpensesByCategorySheet implements FromArray, ShouldAutoSize, WithStyles, WithTitle
{
    public function __construct(protected array $data, protected string $currencyCode = 'KES') {}

    public function array(): array
    {
        $rows = [['Category', 'Expense Count', "Amount ({$this->currencyCode})", 'Percentage (%)']];

        foreach ($this->data['categories'] ?? [] as $row) {
            $rows[] = [
                $row['category'],
                $row['count'],
                $row['amount'],
                $row['percentage'],
            ];
        }

        $rows[] = ['Total', '', $this->data['total'] ?? 0, '100'];

        return $rows;
    }

    public function styles(Worksheet $sheet): array
    {
        $lastRow = count($this->data['categories'] ?? []) + 2;

        return [
            1 => ['font' => ['bold' => true]],
            $lastRow => ['font' => ['bold' => true]],
        ];
    }

    public function title(): string
    {
        return 'Expenses by Category';
    }
}
