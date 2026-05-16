<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Models\ChartOfAccount;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use App\Services\Accounting\AccountingExportService;
use App\Services\Accounting\AccountMappingService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

class Phase30AccountingExportTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    private User $landlord;

    private $tenant;

    private $lease;

    protected function setUp(): void
    {
        parent::setUp();
        $setup = $this->createLandlordWithFullSetup();
        $this->landlord = $setup['landlord'];
        ['tenant' => $this->tenant, 'lease' => $this->lease] = $this->createTenantWithActiveLease(
            $this->landlord,
            $setup['units']->first(),
        );
    }

    public function test_mapper_returns_exact_match_when_invoice_type_is_mapped(): void
    {
        $invoiceType = \App\Models\InvoiceType::create([
            'code' => 'phase30-rent',
            'name' => 'Phase-30 Rent',
            'is_system' => false,
            'is_credit' => false,
        ]);

        $account = ChartOfAccount::create([
            'landlord_id' => $this->landlord->id,
            'account_code' => '4100',
            'account_name' => 'Rent Income',
            'account_type' => ChartOfAccount::TYPE_INCOME,
            'source_kind' => ChartOfAccount::SOURCE_INVOICE_TYPE,
            'source_key' => (string) $invoiceType->id,
        ]);

        $invoice = $this->createInvoiceForLease($this->lease, 'sent');
        $invoice->update(['invoice_type_id' => $invoiceType->id]);

        $mapper = app(AccountMappingService::class);
        $resolved = $mapper->accountForInvoice($invoice->fresh());

        $this->assertSame($account->id, $resolved->id);
        $this->assertSame('4100', $resolved->account_code);
    }

    public function test_mapper_falls_back_to_synthetic_when_nothing_is_mapped(): void
    {
        $invoiceType = \App\Models\InvoiceType::create([
            'code' => 'phase30-unmapped',
            'name' => 'Phase-30 Unmapped',
            'is_system' => false,
            'is_credit' => false,
        ]);

        $invoice = $this->createInvoiceForLease($this->lease, 'sent');
        $invoice->update(['invoice_type_id' => $invoiceType->id]);

        $mapper = app(AccountMappingService::class);
        $resolved = $mapper->accountForInvoice($invoice->fresh());

        $this->assertNull($resolved->id);
        $this->assertSame('4000', $resolved->account_code);
        $this->assertSame(ChartOfAccount::TYPE_INCOME, $resolved->account_type);
    }

    public function test_mapping_diagnostics_count_unmapped(): void
    {
        ExpenseCategory::create([
            'landlord_id' => $this->landlord->id,
            'name' => 'Repairs',
        ]);

        $mapper = app(AccountMappingService::class);
        $diag = $mapper->mappingDiagnostics($this->landlord->id);

        $this->assertGreaterThanOrEqual(1, $diag['expense_categories_unmapped']);
        $this->assertTrue($diag['missing_default_income']);
        $this->assertTrue($diag['missing_default_expense']);
    }

    public function test_quickbooks_iif_export_streams_zero_summed_transactions(): void
    {
        ChartOfAccount::create([
            'landlord_id' => $this->landlord->id,
            'account_code' => '4100',
            'account_name' => 'Rent Income',
            'account_type' => ChartOfAccount::TYPE_INCOME,
            'source_kind' => ChartOfAccount::SOURCE_DEFAULT_INCOME,
        ]);

        $this->createInvoiceForLease($this->lease, 'sent');

        $service = app(AccountingExportService::class);
        $response = $service->export(
            landlordId: $this->landlord->id,
            from: CarbonImmutable::now()->subDay(),
            to: CarbonImmutable::now()->addDay(),
            format: AccountingExportService::FORMAT_QUICKBOOKS_IIF,
        );

        ob_start();
        $response->sendContent();
        $body = ob_get_clean();

        $this->assertStringContainsString("!ACCNT\tNAME\tACCNTTYPE", $body);
        $this->assertStringContainsString("!TRNS\tTRNSTYPE", $body);
        $this->assertStringContainsString("TRNS\tINVOICE", $body);
        $this->assertStringContainsString("SPL\tINVOICE", $body);
        $this->assertStringContainsString("ENDTRNS", $body);
    }

    public function test_sage_csv_export_emits_csv_header_and_invoice_row(): void
    {
        ChartOfAccount::create([
            'landlord_id' => $this->landlord->id,
            'account_code' => '4100',
            'account_name' => 'Rent Income',
            'account_type' => ChartOfAccount::TYPE_INCOME,
            'source_kind' => ChartOfAccount::SOURCE_DEFAULT_INCOME,
        ]);

        $this->createInvoiceForLease($this->lease, 'sent');

        $service = app(AccountingExportService::class);
        $response = $service->export(
            landlordId: $this->landlord->id,
            from: CarbonImmutable::now()->subDay(),
            to: CarbonImmutable::now()->addDay(),
            format: AccountingExportService::FORMAT_SAGE_CSV,
        );

        ob_start();
        $response->sendContent();
        $body = ob_get_clean();

        $this->assertStringContainsString('Account Code', $body);
        $this->assertStringContainsString('SI,4100', $body);
    }

    public function test_export_rejects_unknown_format(): void
    {
        $service = app(AccountingExportService::class);
        $this->expectException(\InvalidArgumentException::class);
        $service->export(
            landlordId: $this->landlord->id,
            from: CarbonImmutable::now()->subDay(),
            to: CarbonImmutable::now(),
            format: 'xero_xml',
        );
    }

    public function test_controller_index_returns_diagnostics(): void
    {
        $this->actingAs($this->landlord)
            ->get(route('finances.accounting.export.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Finances/Accounting/Export')
                ->has('diagnostics')
                ->has('accountCount')
                ->has('formats')
            );
    }

    public function test_controller_download_validates_dates_and_format(): void
    {
        $this->actingAs($this->landlord)
            ->get(route('finances.accounting.export.download', [
                'from' => 'not-a-date',
                'to' => '2026-01-01',
                'format' => 'iif',
            ]))
            ->assertStatus(302);
    }

    public function test_other_landlord_cannot_download_export_of_first_landlord_data(): void
    {
        ChartOfAccount::create([
            'landlord_id' => $this->landlord->id,
            'account_code' => '4100',
            'account_name' => 'Rent Income',
            'account_type' => ChartOfAccount::TYPE_INCOME,
            'source_kind' => ChartOfAccount::SOURCE_DEFAULT_INCOME,
        ]);

        $this->createInvoiceForLease($this->lease, 'sent');

        $other = User::factory()->create(['role' => 'landlord']);

        $this->actingAs($other)
            ->get(route('finances.accounting.export.download', [
                'from' => CarbonImmutable::now()->subDay()->toDateString(),
                'to' => CarbonImmutable::now()->addDay()->toDateString(),
                'format' => 'iif',
            ]))
            ->assertOk();

        // Other landlord's export must not contain $this->landlord's invoice.
        $service = app(AccountingExportService::class);
        $response = $service->export(
            landlordId: $other->id,
            from: CarbonImmutable::now()->subDay(),
            to: CarbonImmutable::now()->addDay(),
            format: AccountingExportService::FORMAT_QUICKBOOKS_IIF,
        );
        ob_start();
        $response->sendContent();
        $body = ob_get_clean();
        $this->assertStringNotContainsString('Invoice '.Invoice::query()->withoutGlobalScopes()->where('landlord_id', $this->landlord->id)->first()->invoice_number, $body);
    }
}
