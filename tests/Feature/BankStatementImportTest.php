<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Imports\BankStatementImport;
use App\Models\BankReconciliationQueue;
use App\Models\Building;
use App\Models\Invoice;
use App\Models\Lease;
use App\Models\Property;
use App\Models\Unit;
use App\Models\User;
use App\Services\Banking\BankReconciliationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BankStatementImportTest extends TestCase
{
    use RefreshDatabase;

    private User $landlord;

    private Property $property;

    private Building $building;

    private Unit $unit;

    private Lease $lease;

    private Invoice $invoice;

    protected function setUp(): void
    {
        parent::setUp();

        $this->landlord = User::factory()->create(['role' => 'landlord']);

        $this->property = Property::create([
            'name' => 'Test Property',
            'address' => '123 Test St',
            'type' => 'apartment',
            'landlord_id' => $this->landlord->id,
        ]);

        $this->building = Building::create([
            'property_id' => $this->property->id,
            'name' => 'Block A',
            'floors' => 1,
            'units_per_floor' => 1,
            'landlord_id' => $this->landlord->id,
        ]);

        $this->unit = Unit::create([
            'building_id' => $this->building->id,
            'unit_number' => 'A101',
            'floor_number' => 1,
            'status' => 'occupied',
            'target_rent' => 25000,
            'landlord_id' => $this->landlord->id,
        ]);

        $tenant = User::factory()->create([
            'role' => 'tenant',
            'landlord_id' => $this->landlord->id,
        ]);

        $this->lease = Lease::create([
            'unit_id' => $this->unit->id,
            'tenant_id' => $tenant->id,
            'landlord_id' => $this->landlord->id,
            'rent_amount' => 20000,
            'deposit_amount' => 20000,
            'start_date' => now()->subMonths(3),
            'is_active' => true,
        ]);

        $this->invoice = Invoice::create([
            'lease_id' => $this->lease->id,
            'landlord_id' => $this->landlord->id,
            'invoice_number' => 'INV-202601-0001',
            'due_date' => now()->subDays(5),
            'billing_period_start' => now()->subMonth(),
            'rent_due' => 20000,
            'water_due' => 500,
            'arrears' => 0,
            'wallet_applied' => 0,
            'total_due' => 20500,
            'amount_paid' => 0,
            'status' => 'overdue',
        ]);
    }

    public function test_can_import_csv_bank_statement(): void
    {
        $this->actingAs($this->landlord);

        Storage::fake('local');

        $csv = "reference,amount,date,description\n";
        $csv .= "TXN001,20500,2026-01-10,Rent payment INV-202601-0001\n";
        $csv .= "TXN002,15000,2026-01-11,Another payment\n";

        $file = UploadedFile::fake()->createWithContent('statement.csv', $csv);

        $response = $this->post(route('reconciliation.import'), [
            'file' => $file,
            'bank_code' => 'equity',
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('bank_reconciliation_queue', [
            'landlord_id' => $this->landlord->id,
            'bank_code' => 'equity',
            'transaction_reference' => 'TXN001',
            'amount' => 20500,
            'status' => 'pending',
        ]);

        $this->assertDatabaseHas('bank_reconciliation_queue', [
            'landlord_id' => $this->landlord->id,
            'transaction_reference' => 'TXN002',
            'amount' => 15000,
        ]);
    }

    public function test_duplicate_transactions_are_skipped(): void
    {
        BankReconciliationQueue::create([
            'landlord_id' => $this->landlord->id,
            'bank_code' => 'equity',
            'transaction_reference' => 'TXN001',
            'amount' => 20500,
            'status' => 'pending',
        ]);

        $import = new BankStatementImport($this->landlord->id, 'equity');

        $rows = collect([
            ['reference' => 'TXN001', 'amount' => '20500', 'date' => '2026-01-10', 'description' => 'Test'],
            ['reference' => 'TXN002', 'amount' => '15000', 'date' => '2026-01-11', 'description' => 'New'],
        ]);

        $import->collection($rows);

        $results = $import->getResults();

        $this->assertEquals(1, $results['imported']);
        $this->assertEquals(1, $results['skipped']);

        $this->assertEquals(2, BankReconciliationQueue::count());
    }

    public function test_auto_match_by_invoice_number(): void
    {
        $queueItem = BankReconciliationQueue::create([
            'landlord_id' => $this->landlord->id,
            'bank_code' => 'equity',
            'transaction_reference' => 'TXN001',
            'amount' => 20500,
            'status' => 'pending',
            'raw_payload' => [
                'reference' => 'TXN001',
                'description' => 'Rent payment INV-202601-0001',
            ],
        ]);

        $service = app(BankReconciliationService::class);
        $results = $service->processQueueForLandlord($this->landlord->id);

        $this->assertEquals(1, $results['matched']);

        $queueItem->refresh();
        $this->assertEquals('matched', $queueItem->status);
        $this->assertEquals($this->invoice->id, $queueItem->matched_invoice_id);

        $this->invoice->refresh();
        $this->assertEquals(InvoiceStatus::Paid, $this->invoice->status);
    }

    public function test_auto_match_by_amount(): void
    {
        $queueItem = BankReconciliationQueue::create([
            'landlord_id' => $this->landlord->id,
            'bank_code' => 'equity',
            'transaction_reference' => 'TXN002',
            'amount' => 20500,
            'status' => 'pending',
            'raw_payload' => [
                'reference' => 'TXN002',
                'description' => 'Bank transfer',
            ],
        ]);

        $service = app(BankReconciliationService::class);
        $results = $service->processQueueForLandlord($this->landlord->id);

        $this->assertEquals(1, $results['matched']);

        $queueItem->refresh();
        $this->assertEquals('matched', $queueItem->status);
    }

    public function test_unmatched_item_stays_in_queue(): void
    {
        Invoice::where('id', $this->invoice->id)->update(['status' => 'paid']);

        $queueItem = BankReconciliationQueue::create([
            'landlord_id' => $this->landlord->id,
            'bank_code' => 'equity',
            'transaction_reference' => 'TXN003',
            'amount' => 99999,
            'status' => 'pending',
            'raw_payload' => [
                'reference' => 'TXN003',
                'description' => 'Unknown payment',
            ],
        ]);

        $service = app(BankReconciliationService::class);
        $results = $service->processQueueForLandlord($this->landlord->id);

        $this->assertEquals(0, $results['matched']);
        $this->assertEquals(1, $results['failed']);

        $queueItem->refresh();
        $this->assertEquals('unmatched', $queueItem->status);
    }

    public function test_column_mapping_works(): void
    {
        $import = new BankStatementImport($this->landlord->id, 'kcb', [
            'reference' => 'trans_id',
            'amount' => 'credit',
            'date' => 'trans_date',
            'description' => 'narration',
        ]);

        $rows = collect([
            [
                'trans_id' => 'KCB123',
                'credit' => '25000',
                'trans_date' => '2026-01-12',
                'narration' => 'Payment received',
            ],
        ]);

        $import->collection($rows);

        $results = $import->getResults();

        $this->assertEquals(1, $results['imported']);

        $this->assertDatabaseHas('bank_reconciliation_queue', [
            'transaction_reference' => 'KCB123',
            'amount' => 25000,
            'bank_code' => 'kcb',
        ]);
    }

    public function test_tenant_isolation_on_import(): void
    {
        $otherLandlord = User::factory()->create(['role' => 'landlord']);

        Property::create([
            'name' => 'Other Property',
            'address' => '456 Other St',
            'type' => 'apartment',
            'landlord_id' => $otherLandlord->id,
        ]);

        $this->actingAs($otherLandlord);

        Storage::fake('local');

        $csv = "reference,amount,date,description\nTXN001,10000,2026-01-10,Test\n";
        $file = UploadedFile::fake()->createWithContent('statement.csv', $csv);

        $response = $this->post(route('reconciliation.import'), [
            'file' => $file,
            'bank_code' => 'equity',
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('bank_reconciliation_queue', [
            'landlord_id' => $otherLandlord->id,
            'transaction_reference' => 'TXN001',
        ]);

        $this->assertDatabaseMissing('bank_reconciliation_queue', [
            'landlord_id' => $this->landlord->id,
            'transaction_reference' => 'TXN001',
        ]);
    }

    public function test_import_requires_authentication(): void
    {
        Storage::fake('local');

        $csv = "reference,amount,date,description\nTXN001,10000,2026-01-10,Test\n";
        $file = UploadedFile::fake()->createWithContent('statement.csv', $csv);

        $response = $this->post(route('reconciliation.import'), [
            'file' => $file,
            'bank_code' => 'equity',
        ]);

        $response->assertRedirect(route('login'));
    }

    public function test_process_queue_endpoint(): void
    {
        $this->actingAs($this->landlord);

        BankReconciliationQueue::create([
            'landlord_id' => $this->landlord->id,
            'bank_code' => 'equity',
            'transaction_reference' => 'TXN001',
            'amount' => 20500,
            'status' => 'pending',
            'raw_payload' => [
                'reference' => 'TXN001',
                'description' => 'INV-202601-0001 payment',
            ],
        ]);

        $response = $this->post(route('reconciliation.process-queue'));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('bank_reconciliation_queue', [
            'transaction_reference' => 'TXN001',
            'status' => 'matched',
        ]);
    }

    public function test_import_validates_file_type(): void
    {
        $this->actingAs($this->landlord);

        Storage::fake('local');

        $file = UploadedFile::fake()->create('statement.pdf', 100);

        $response = $this->post(route('reconciliation.import'), [
            'file' => $file,
            'bank_code' => 'equity',
        ]);

        $response->assertSessionHasErrors('file');
    }

    public function test_import_requires_bank_code(): void
    {
        $this->actingAs($this->landlord);

        Storage::fake('local');

        $csv = "reference,amount,date,description\nTXN001,10000,2026-01-10,Test\n";
        $file = UploadedFile::fake()->createWithContent('statement.csv', $csv);

        $response = $this->post(route('reconciliation.import'), [
            'file' => $file,
        ]);

        $response->assertSessionHasErrors('bank_code');
    }
}
