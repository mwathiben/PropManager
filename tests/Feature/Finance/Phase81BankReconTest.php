<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Models\BankReconciliationQueue;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * Phase-81 BANK-RECON: the previously-stubbed import + process-queue endpoints
 * now drive the real BankStatementImport / BankReconciliationService.
 */
class Phase81BankReconTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    private User $landlord;

    protected function setUp(): void
    {
        parent::setUp();
        $this->landlord = $this->createLandlordWithFullSetup()['landlord'];
    }

    private function csv(): UploadedFile
    {
        $content = "reference,amount,date,description\n"
            ."TXN-1001,5000,2026-05-01,Rent payment\n"
            ."TXN-1002,7500,2026-05-02,Rent payment\n";

        return UploadedFile::fake()->createWithContent('statement.csv', $content);
    }

    public function test_import_enqueues_rows(): void
    {
        $this->actingAs($this->landlord)
            ->post(route('finances.reconciliation.import'), ['file' => $this->csv(), 'bank_code' => 'EQUITY'])
            ->assertRedirect();

        $this->assertSame(2, BankReconciliationQueue::where('landlord_id', $this->landlord->id)->count());
    }

    public function test_import_dedupes_on_re_import(): void
    {
        $this->actingAs($this->landlord)
            ->post(route('finances.reconciliation.import'), ['file' => $this->csv(), 'bank_code' => 'EQUITY'])
            ->assertRedirect();
        $this->actingAs($this->landlord)
            ->post(route('finances.reconciliation.import'), ['file' => $this->csv(), 'bank_code' => 'EQUITY'])
            ->assertRedirect();

        $this->assertSame(2, BankReconciliationQueue::where('landlord_id', $this->landlord->id)->count());
    }

    public function test_process_queue_endpoint_runs_the_matcher(): void
    {
        BankReconciliationQueue::create([
            'landlord_id' => $this->landlord->id,
            'bank_code' => 'EQUITY',
            'transaction_reference' => 'NO-MATCH-9999',
            'amount' => 1234.56,
            'status' => 'pending',
            'raw_payload' => ['reference' => 'NO-MATCH-9999'],
        ]);

        $this->actingAs($this->landlord)
            ->post(route('finances.reconciliation.process-queue'))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        // The unmatchable item was processed (no longer 'pending').
        $this->assertSame(0, BankReconciliationQueue::where('landlord_id', $this->landlord->id)->where('status', 'pending')->count());
    }
}
