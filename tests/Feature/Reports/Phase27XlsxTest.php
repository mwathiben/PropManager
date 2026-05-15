<?php

declare(strict_types=1);

namespace Tests\Feature\Reports;

use App\Services\Reports\XlsxExportService;
use Carbon\CarbonImmutable;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

/**
 * Phase-27 BI-DELIVERY-1 watchdog: XlsxExportService writes a real
 * xlsx — numeric cells are numeric, currency carries the KES format
 * mask, dates carry a date format mask. The pre-Phase-27 CSV-with-
 * .xlsx-extension hack opens in Excel but every cell is text; this
 * test asserts the new path is genuine xlsx.
 */
class Phase27XlsxTest extends TestCase
{
    public function test_xlsx_writes_typed_cells_with_currency_and_date_masks(): void
    {
        $service = new XlsxExportService;
        $path = storage_path('app/tmp/phase27-xlsx-test.xlsx');
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0o755, true);
        }
        @unlink($path);

        $columns = [
            ['label' => 'Tenant', 'key' => 'tenant', 'type' => 'string'],
            ['label' => 'Amount', 'key' => 'amount', 'type' => 'currency'],
            ['label' => 'Count', 'key' => 'count', 'type' => 'integer'],
            ['label' => 'Due', 'key' => 'due', 'type' => 'date'],
        ];

        $rows = [
            ['tenant' => 'Alice', 'amount' => 25000.50, 'count' => 3, 'due' => CarbonImmutable::parse('2026-03-15')],
            ['tenant' => 'Bob', 'amount' => 18000.00, 'count' => 1, 'due' => '2026-03-20'],
        ];

        $service->write('Arrears Test', $columns, $rows, $path);

        $this->assertFileExists($path, 'BI-DELIVERY-1: XlsxExportService must produce an output file.');

        // Re-open the file and assert structure.
        $spreadsheet = IOFactory::load($path);
        $sheet = $spreadsheet->getActiveSheet();

        $this->assertSame('Tenant', $sheet->getCell('A1')->getValue());
        $this->assertSame('Amount', $sheet->getCell('B1')->getValue());

        $amountCell = $sheet->getCell('B2');
        $this->assertIsNumeric(
            $amountCell->getValue(),
            'BI-DELIVERY-1: amount column must contain numeric values (not strings). Got '.var_export($amountCell->getValue(), true),
        );

        $amountMask = $sheet->getStyle('B2')->getNumberFormat()->getFormatCode();
        $this->assertStringContainsString(
            'KES',
            $amountMask,
            "BI-DELIVERY-1: currency column must carry the KES format mask. Got '{$amountMask}'.",
        );

        $integerCell = $sheet->getCell('C2');
        $this->assertIsNumeric($integerCell->getValue(), 'BI-DELIVERY-1: integer column must be numeric.');

        $dateMask = $sheet->getStyle('D2')->getNumberFormat()->getFormatCode();
        $this->assertStringContainsString(
            'd',
            strtolower($dateMask),
            "BI-DELIVERY-1: date column must carry a date format mask. Got '{$dateMask}'.",
        );

        @unlink($path);
    }

    public function test_header_row_is_bold_with_brand_fill(): void
    {
        $service = new XlsxExportService;
        $path = storage_path('app/tmp/phase27-xlsx-header.xlsx');
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0o755, true);
        }
        @unlink($path);

        $service->write(
            'Header Test',
            [['label' => 'A', 'key' => 'a', 'type' => 'string']],
            [['a' => '1']],
            $path,
        );

        $spreadsheet = IOFactory::load($path);
        $sheet = $spreadsheet->getActiveSheet();
        $style = $sheet->getStyle('A1');

        $this->assertTrue($style->getFont()->getBold(), 'BI-DELIVERY-1: header row must be bold.');
        $this->assertSame(
            '1F2937',
            $style->getFill()->getStartColor()->getRGB(),
            'BI-DELIVERY-1: header fill must carry the brand theme color.',
        );

        @unlink($path);
    }
}
