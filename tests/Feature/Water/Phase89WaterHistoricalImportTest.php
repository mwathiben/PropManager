<?php

declare(strict_types=1);

namespace Tests\Feature\Water;

use App\Models\Import;
use App\Models\Invoice;
use App\Models\User;
use App\Models\WaterReading;
use App\Services\ImportService;
use App\Services\InvoiceService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * Phase-89 WATER-HISTORICAL-IMPORT: backfill historical readings that are
 * meter-linked, never re-billed, idempotent, and Excel-capable.
 */
class Phase89WaterHistoricalImportTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    private User $landlord;

    private $units;

    protected function setUp(): void
    {
        parent::setUp();
        $setup = $this->createLandlordWithFullSetup();
        $this->landlord = $setup['landlord'];
        $this->units = $setup['units'];
        $setup['building']->update(['water_billing_type' => 'consumption', 'water_unit_rate' => 150]);
        $this->actingAs($this->landlord->fresh());
    }

    private function importCsv(string $content, string $name = 'water.csv'): Import
    {
        Storage::tenant()->put("imports/{$name}", $content);
        $import = Import::create([
            'landlord_id' => $this->landlord->id,
            'imported_by' => $this->landlord->id,
            'type' => 'water_readings',
            'file_name' => $name,
            'file_path' => "imports/{$name}",
            'status' => 'pending',
        ]);
        app(ImportService::class)->processImport($import);

        return $import->fresh();
    }

    public function test_imported_reading_is_meter_linked_and_never_rebills(): void
    {
        $unit = $this->units->get(0);
        $this->importCsv("Unit Number,Reading Date,Previous Reading,Current Reading\n{$unit->unit_number},2024-01-15,0,25\n");

        $reading = WaterReading::where('unit_id', $unit->id)->firstOrFail();
        $this->assertTrue($reading->is_invoiced, 'imported reading must be marked already-billed');
        $this->assertNotNull($reading->meter_id);
        $this->assertEquals(25, (float) $reading->consumption);
        $this->assertEquals(3750, (float) $reading->cost); // 25 * 150

        // Never re-bills: an invoice for this lease does not pick the reading up.
        ['lease' => $lease] = Model::withoutEvents(fn () => $this->createTenantWithActiveLease($this->landlord, $unit));
        $invoice = Model::withoutEvents(fn () => app(InvoiceService::class)->generateInvoiceForLease($lease->fresh(), now()));
        $this->assertEquals(0, (float) Invoice::find($invoice->id)->water_due);
    }

    public function test_reimport_is_idempotent(): void
    {
        $unit = $this->units->get(1);
        $csv = "Unit Number,Reading Date,Previous Reading,Current Reading\n{$unit->unit_number},2024-01-15,0,25\n";

        $this->importCsv($csv, 'first.csv');
        $second = $this->importCsv($csv, 'second.csv');

        $this->assertSame(1, WaterReading::where('unit_id', $unit->id)->count());
        $this->assertSame(1, (int) ($second->summary['skipped_duplicates'] ?? 0));
    }

    public function test_explicit_cost_is_preserved(): void
    {
        $unit = $this->units->get(2);
        $this->importCsv("Unit Number,Reading Date,Previous Reading,Current Reading,Cost\n{$unit->unit_number},2024-01-15,0,25,9999\n");

        $this->assertEquals(9999, (float) WaterReading::where('unit_id', $unit->id)->firstOrFail()->cost);
    }

    public function test_xlsx_import_works(): void
    {
        $unit = $this->units->get(3);

        $spreadsheet = new Spreadsheet;
        $spreadsheet->getActiveSheet()->fromArray([
            ['Unit Number', 'Reading Date', 'Previous Reading', 'Current Reading'],
            [$unit->unit_number, '2024-02-15', '0', '30'],
        ], null, 'A1');
        $tmp = tempnam(sys_get_temp_dir(), 'wimp').'.xlsx';
        (new Xlsx($spreadsheet))->save($tmp);
        Storage::tenant()->put('imports/water.xlsx', file_get_contents($tmp));
        @unlink($tmp);

        $import = Import::create([
            'landlord_id' => $this->landlord->id,
            'imported_by' => $this->landlord->id,
            'type' => 'water_readings',
            'file_name' => 'water.xlsx',
            'file_path' => 'imports/water.xlsx',
            'status' => 'pending',
        ]);
        app(ImportService::class)->processImport($import);

        $reading = WaterReading::where('unit_id', $unit->id)->firstOrFail();
        $this->assertEquals(30, (float) $reading->consumption);
        $this->assertTrue($reading->is_invoiced);
    }
}
