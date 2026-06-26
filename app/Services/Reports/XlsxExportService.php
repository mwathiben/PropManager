<?php

declare(strict_types=1);

namespace App\Services\Reports;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as XlsxDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * Phase-27 BI-DELIVERY-1: real xlsx writer.
 *
 * Replaces the pre-Phase-27 "rename a CSV to .xlsx" hack with proper
 * PhpSpreadsheet output. Columns get typed cells:
 *   - currency  → numeric value + KES format mask
 *   - integer   → numeric value
 *   - date      → Excel-serial date + dd-mmm-yyyy format mask
 *   - string    → plain text
 *
 * The header row is bold + filled with the brand theme color. Columns
 * auto-fit to content width.
 *
 * Usage:
 *   $service->write(
 *       'Arrears report',
 *       [
 *           ['label' => 'Tenant', 'key' => 'tenant_name', 'type' => 'string'],
 *           ['label' => 'Amount', 'key' => 'amount_due', 'type' => 'currency'],
 *           ['label' => 'Due',    'key' => 'due_date',   'type' => 'date'],
 *       ],
 *       $rows, // array of associative arrays keyed by column 'key'
 *       $outputPath,
 *   );
 */
class XlsxExportService
{
    /** @var array<string, string> PhpSpreadsheet format masks per type */
    private const FORMAT_MASKS = [
        'currency' => '[$KES] #,##0.00',
        'integer' => '#,##0',
        'date' => 'dd-mmm-yyyy',
    ];

    /**
     * @param  list<array{label: string, key: string, type: 'string'|'currency'|'integer'|'date'}>  $columns
     * @param  list<array<string, mixed>>  $rows
     */
    public function write(string $title, array $columns, array $rows, string $outputPath): void
    {
        $this->writeMultiSheet(
            [['title' => $title, 'columns' => $columns, 'rows' => $rows]],
            $outputPath,
        );
    }

    /**
     * Phase-45 STATEMENT-DEPTH-1: multi-sheet output for rollup reports.
     * Each sheet has its own title + columns + rows. Use this when a
     * report has both a detail view (line items) and a roll-up view
     * (monthly subtotals) that belong in one workbook.
     *
     * @param  list<array{title: string, columns: list<array{label: string, key: string, type: 'string'|'currency'|'integer'|'date'}>, rows: list<array<string, mixed>>}>  $sheets
     */
    public function writeMultiSheet(array $sheets, string $outputPath): void
    {
        $spreadsheet = new Spreadsheet;

        // PhpSpreadsheet ships one default sheet; reuse it for the
        // first input sheet and createSheet() for the rest.
        $count = count($sheets);
        for ($i = 0; $i < $count; $i++) {
            $input = $sheets[$i];
            $sheet = $i === 0
                ? $spreadsheet->getActiveSheet()
                : $spreadsheet->createSheet();
            $sheet->setTitle(mb_substr($input['title'], 0, 31));

            if ($input['columns'] === []) {
                $sheet->setCellValue('A1', 'No rows.');

                continue;
            }

            $this->writeHeader($sheet, $input['columns']);
            $this->writeRows($sheet, $input['columns'], $input['rows']);
            $this->autoSizeColumns($sheet, $input['columns']);
        }

        $spreadsheet->setActiveSheetIndex(0);
        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->save($outputPath);
    }

    /**
     * @param  list<array{label: string, key: string, type: string}>  $columns
     */
    private function writeHeader(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, array $columns): void
    {
        $col = 1;
        foreach ($columns as $column) {
            $coord = $this->cellCoord($col, 1);
            $sheet->setCellValue($coord, $column['label']);
            $col++;
        }

        $lastColLetter = $this->columnLetter(count($columns));
        $headerRange = "A1:{$lastColLetter}1";
        $style = $sheet->getStyle($headerRange);
        $style->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $style->getFill()->setFillType(Fill::FILL_SOLID);
        $style->getFill()->getStartColor()->setRGB('1F2937');
        $style->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
    }

    /**
     * @param  list<array{label: string, key: string, type: string}>  $columns
     * @param  list<array<string, mixed>>  $rows
     */
    private function writeRows(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, array $columns, array $rows): void
    {
        $rowIndex = 2;
        foreach ($rows as $row) {
            $colIndex = 1;
            foreach ($columns as $column) {
                $coord = $this->cellCoord($colIndex, $rowIndex);
                $value = $row[$column['key']] ?? null;
                $this->writeTypedCell($sheet, $coord, $value, $column['type']);
                $colIndex++;
            }
            $rowIndex++;
        }
    }

    private function writeTypedCell(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, string $coord, mixed $value, string $type): void
    {
        if ($value === null) {
            $sheet->setCellValue($coord, null);

            return;
        }

        switch ($type) {
            case 'currency':
            case 'integer':
                $this->writeNumericCell($sheet, $coord, $value, $type);
                break;

            case 'date':
                $this->writeDateCell($sheet, $coord, $value);
                break;

            case 'string':
            default:
                $sheet->setCellValue($coord, (string) $value);
                break;
        }
    }

    private function writeNumericCell(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, string $coord, mixed $value, string $type): void
    {
        $sheet->setCellValue($coord, (float) $value);
        $sheet->getStyle($coord)->getNumberFormat()->setFormatCode(self::FORMAT_MASKS[$type]);
    }

    private function writeDateCell(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, string $coord, mixed $value): void
    {
        // Accept Carbon, DateTime, or ISO string.
        if ($value instanceof \DateTimeInterface) {
            $serial = XlsxDate::PHPToExcel($value);
        } else {
            $serial = XlsxDate::PHPToExcel(new \DateTimeImmutable((string) $value));
        }
        $sheet->setCellValue($coord, $serial);
        $sheet->getStyle($coord)->getNumberFormat()->setFormatCode(self::FORMAT_MASKS['date']);
    }

    /**
     * @param  list<array{label: string, key: string, type: string}>  $columns
     */
    private function autoSizeColumns(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, array $columns): void
    {
        $count = count($columns);
        for ($i = 1; $i <= $count; $i++) {
            $sheet->getColumnDimension($this->columnLetter($i))->setAutoSize(true);
        }
    }

    private function cellCoord(int $col, int $row): string
    {
        return $this->columnLetter($col).$row;
    }

    private function columnLetter(int $col): string
    {
        // Supports A..Z and AA..ZZ which covers ≥ 100 columns.
        $letter = '';
        while ($col > 0) {
            $remainder = ($col - 1) % 26;
            $letter = chr(65 + $remainder).$letter;
            $col = (int) (($col - $remainder - 1) / 26);
        }

        return $letter;
    }
}
