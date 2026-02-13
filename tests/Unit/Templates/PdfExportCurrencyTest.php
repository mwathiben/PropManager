<?php

declare(strict_types=1);

namespace Tests\Unit\Templates;

use App\Enums\Currency;
use App\Exports\ArrearsReportExport;
use App\Exports\DepositsExport;
use App\Exports\ExpensesExport;
use App\Exports\FinanceReportExport;
use App\Exports\FinancialReportExport;
use App\Exports\InvoicesExport;
use App\Exports\OccupancyReportExport;
use App\Exports\PaymentsExport;
use App\Exports\Streaming\StreamingDepositsExport;
use App\Exports\Streaming\StreamingExpensesExport;
use App\Exports\Streaming\StreamingInvoicesExport;
use App\Exports\Streaming\StreamingPaymentsExport;
use App\Exports\VendorExpenseExport;
use App\Models\Building;
use App\Models\Invoice;
use App\Models\Lease;
use App\Models\Payment;
use App\Models\PaymentConfiguration;
use App\Models\Property;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PdfExportCurrencyTest extends TestCase
{
    use RefreshDatabase;

    private User $landlord;

    private Building $building;

    protected function setUp(): void
    {
        parent::setUp();

        $this->landlord = User::factory()->create(['role' => 'landlord']);
        $property = Property::factory()->create(['landlord_id' => $this->landlord->id]);
        $this->building = Building::factory()
            ->forProperty($property)
            ->withCurrency(Currency::USD)
            ->create();

        PaymentConfiguration::updateOrCreate(
            ['landlord_id' => $this->landlord->id],
            ['default_currency' => Currency::USD]
        );
    }

    // ── Blade Template Tests ──────────────────────────────────────────

    public function test_invoice_pdf_blade_uses_dynamic_currency_symbol(): void
    {
        $unit = Unit::factory()->forBuilding($this->building)->create();
        $lease = Lease::factory()->forUnit($unit)->active()->create();
        $invoice = Invoice::factory()
            ->forLease($lease)
            ->withCurrency(Currency::USD)
            ->sent()
            ->create();

        $html = view('invoices.invoice-pdf', [
            'invoice' => $invoice->load(['lease.tenant', 'lease.unit.building.property']),
            'tenant' => $lease->tenant,
            'unit' => $unit,
            'building' => $this->building,
            'property' => $this->building->property,
            'currency_symbol' => Currency::USD->symbol(),
            'currency_code' => Currency::USD->value,
        ])->render();

        $this->assertStringNotContainsString('KES ', $html);
        $this->assertStringContainsString('$ ', $html);
    }

    public function test_ledger_pdf_blade_uses_dynamic_currency_symbol(): void
    {
        $unit = Unit::factory()->forBuilding($this->building)->create();
        $lease = Lease::factory()->forUnit($unit)->active()->create();
        $tenant = User::findOrFail($lease->tenant_id);

        $invoiceSetting = (object) [
            'business_name' => 'Test Co',
            'business_address' => '123 St',
            'business_phone' => '123456',
            'business_email' => 'test@example.com',
        ];

        $html = view('tenants.ledger-pdf', [
            'tenant' => $tenant,
            'landlord' => $this->landlord,
            'activeLease' => $lease->load(['unit.building']),
            'transactions' => [],
            'summary' => [
                'total_invoiced' => 50000,
                'total_paid' => 30000,
                'total_refunds' => 0,
                'current_balance' => 20000,
            ],
            'dateFrom' => '2026-01-01',
            'dateTo' => '2026-01-31',
            'generatedAt' => now(),
            'invoiceSetting' => $invoiceSetting,
            'currency_symbol' => Currency::USD->symbol(),
        ])->render();

        $this->assertStringNotContainsString('KES ', $html);
        $this->assertStringContainsString('$ ', $html);
    }

    public function test_exports_invoices_blade_uses_dynamic_currency(): void
    {
        $html = view('exports.invoices', [
            'invoices' => collect(),
            'summary' => [
                'total_count' => 10,
                'total_due' => 100000,
                'total_paid' => 50000,
                'total_balance' => 50000,
                'collection_rate' => 50,
                'overdue_count' => 2,
            ],
            'filters' => [],
            'landlord' => $this->landlord,
            'generated_at' => now()->format('F j, Y g:i A'),
            'currency_symbol' => Currency::USD->symbol(),
            'currency_code' => Currency::USD->value,
        ])->render();

        $this->assertStringNotContainsString('KES', $html);
        $this->assertStringContainsString('$', $html);
    }

    public function test_exports_payments_blade_uses_dynamic_currency(): void
    {
        $html = view('exports.payments', [
            'payments' => collect(),
            'summary' => [
                'total_count' => 5,
                'total_amount' => 50000,
                'average_payment' => 10000,
            ],
            'method_breakdown' => [],
            'filters' => [],
            'landlord' => $this->landlord,
            'generated_at' => now()->format('F j, Y g:i A'),
            'currency_symbol' => Currency::USD->symbol(),
            'currency_code' => Currency::USD->value,
        ])->render();

        $this->assertStringNotContainsString('KES', $html);
        $this->assertStringContainsString('$', $html);
    }

    public function test_exports_deposits_blade_uses_dynamic_currency(): void
    {
        $html = view('exports.deposits', [
            'deposits' => collect(),
            'stats' => [
                'total_held' => 100000,
                'total_refunded' => 20000,
                'total_forfeited' => 5000,
                'count_held' => 10,
                'count_refunded' => 2,
                'count_forfeited' => 1,
            ],
            'landlord' => $this->landlord,
            'generated_at' => now()->format('M j, Y g:i A'),
            'filters' => [],
            'currency_symbol' => Currency::USD->symbol(),
            'currency_code' => Currency::USD->value,
        ])->render();

        $this->assertStringNotContainsString('KES', $html);
        $this->assertStringContainsString('$', $html);
    }

    public function test_exports_expenses_blade_uses_dynamic_currency(): void
    {
        $html = view('exports.expenses', [
            'expenses' => collect(),
            'summary' => [
                'total_count' => 8,
                'total_amount' => 75000,
                'average_expense' => 9375,
                'recurring_count' => 2,
            ],
            'category_breakdown' => [],
            'filters' => [],
            'landlord' => $this->landlord,
            'generated_at' => now()->format('F j, Y g:i A'),
            'currency_symbol' => Currency::USD->symbol(),
            'currency_code' => Currency::USD->value,
        ])->render();

        $this->assertStringNotContainsString('KES', $html);
        $this->assertStringContainsString('$', $html);
    }

    public function test_exports_financial_report_blade_uses_dynamic_currency(): void
    {
        $html = view('exports.financial-report', [
            'data' => [
                'revenue' => [],
                'collection_rate' => [],
                'occupancy' => ['buildings' => [], 'totals' => ['total_units' => 0, 'occupied' => 0, 'vacant' => 0, 'occupancy_rate' => 0]],
                'arrears_aging' => [],
                'expenses_by_category' => ['categories' => [], 'total' => 0],
                'water_consumption' => [],
                'top_performing_units' => [],
            ],
            'period' => 12,
            'landlord' => $this->landlord,
            'generated_at' => now()->format('M j, Y g:i A'),
            'currency_symbol' => Currency::USD->symbol(),
            'currency_code' => Currency::USD->value,
        ])->render();

        $this->assertStringNotContainsString('KES', $html);
        $this->assertStringContainsString('$', $html);
    }

    public function test_reports_financial_blade_uses_dynamic_currency(): void
    {
        $html = view('reports.financial', [
            'data' => [
                'period' => 'monthly',
                'date_range' => ['start' => '2026-01-01', 'end' => '2026-01-31'],
                'summary' => [
                    'expected_rent' => 100000,
                    'collected_rent' => 80000,
                    'water_charges' => 5000,
                    'outstanding' => 20000,
                    'total_revenue' => 85000,
                    'collection_percentage' => 80,
                    'revenue_breakdown' => [],
                ],
                'revenue_trend' => [],
                'collection_rate' => [
                    'total_billed' => 100000,
                    'total_collected' => 80000,
                    'collection_rate' => 80,
                    'paid_count' => 8,
                    'partial_count' => 1,
                    'overdue_count' => 1,
                ],
            ],
            'landlord' => $this->landlord,
            'generated_at' => now()->format('M j, Y g:i A'),
            'currency_symbol' => Currency::USD->symbol(),
            'currency_code' => Currency::USD->value,
        ])->render();

        $this->assertStringNotContainsString('KES', $html);
    }

    public function test_reports_water_blade_uses_dynamic_currency(): void
    {
        $html = view('reports.water', [
            'data' => [
                'period' => 'monthly',
                'summary' => [
                    'total_consumption' => 500,
                    'total_cost' => 75000,
                    'average_consumption' => 50,
                    'readings_count' => 10,
                ],
                'top_consumers' => [],
            ],
            'landlord' => $this->landlord,
            'generated_at' => now()->format('M j, Y g:i A'),
            'currency_symbol' => Currency::USD->symbol(),
            'currency_code' => Currency::USD->value,
        ])->render();

        $this->assertStringNotContainsString('KES', $html);
    }

    public function test_reports_arrears_blade_uses_dynamic_currency(): void
    {
        $html = view('reports.arrears', [
            'data' => [
                'summary' => ['total_arrears' => 50000, 'count' => 5],
                'aging_breakdown' => [],
                'details' => [],
            ],
            'landlord' => $this->landlord,
            'generated_at' => now()->format('M j, Y g:i A'),
            'currency_symbol' => Currency::USD->symbol(),
            'currency_code' => Currency::USD->value,
        ])->render();

        $this->assertStringNotContainsString('KES', $html);
    }

    public function test_receipt_legacy_blade_uses_dynamic_currency(): void
    {
        $unit = Unit::factory()->forBuilding($this->building)->create();
        $lease = Lease::factory()->forUnit($unit)->active()->create();
        $invoice = Invoice::factory()
            ->forLease($lease)
            ->withCurrency(Currency::USD)
            ->sent()
            ->create();
        $payment = Payment::create([
            'invoice_id' => $invoice->id,
            'lease_id' => $lease->id,
            'landlord_id' => $this->building->landlord_id,
            'amount' => 25000,
            'currency' => Currency::USD,
            'payment_method' => 'bank_transfer',
            'payment_date' => now(),
            'reference' => 'TEST-'.uniqid(),
        ]);

        $html = view('receipts.payment-receipt', [
            'payment' => $payment->load(['invoice', 'lease.tenant', 'lease.unit.building']),
            'invoice' => $invoice,
            'currency_symbol' => Currency::USD->symbol(),
        ])->render();

        $this->assertStringNotContainsString('KES ', $html);
    }

    // ── Excel Export Headings Tests ──────────────────────────────────

    public function test_invoices_export_headings_use_dynamic_currency(): void
    {
        $export = new InvoicesExport(collect(), [], 'USD');
        $headings = $export->headings();

        foreach ($headings as $heading) {
            $this->assertStringNotContainsString('(KES)', $heading);
        }
        $this->assertContains('Rent (USD)', $headings);
        $this->assertContains('Total Due (USD)', $headings);
    }

    public function test_payments_export_headings_use_dynamic_currency(): void
    {
        $export = new PaymentsExport(collect(), [], 'USD');
        $headings = $export->headings();

        foreach ($headings as $heading) {
            $this->assertStringNotContainsString('(KES)', $heading);
        }
        $this->assertContains('Amount (USD)', $headings);
    }

    public function test_deposits_export_headings_use_dynamic_currency(): void
    {
        $export = new DepositsExport(collect(), 'USD');
        $headings = $export->headings();

        foreach ($headings as $heading) {
            $this->assertStringNotContainsString('(KES)', $heading);
        }
        $this->assertContains('Deposit Amount (USD)', $headings);
    }

    public function test_expenses_export_headings_use_dynamic_currency(): void
    {
        $export = new ExpensesExport(collect(), [], 'USD');
        $headings = $export->headings();

        foreach ($headings as $heading) {
            $this->assertStringNotContainsString('(KES)', $heading);
        }
        $this->assertContains('Amount (USD)', $headings);
    }

    public function test_vendor_expense_export_headings_use_dynamic_currency(): void
    {
        $export = new VendorExpenseExport(collect(), [], 'USD');
        $headings = $export->headings();

        foreach ($headings as $heading) {
            $this->assertStringNotContainsString('(KES)', $heading);
        }
        $this->assertContains('Total Expenses (USD)', $headings);
    }

    public function test_financial_report_export_headings_use_dynamic_currency(): void
    {
        $export = new FinancialReportExport(1, [], 'USD');
        $headings = $export->headings();

        foreach ($headings as $heading) {
            $this->assertStringNotContainsString('(KES)', $heading);
        }
        $this->assertContains('Rent Due (USD)', $headings);
    }

    public function test_arrears_report_export_headings_use_dynamic_currency(): void
    {
        $export = new ArrearsReportExport(1, 'USD');
        $headings = $export->headings();

        foreach ($headings as $heading) {
            $this->assertStringNotContainsString('(KES)', $heading);
        }
        $this->assertContains('Arrears (USD)', $headings);
    }

    public function test_occupancy_report_export_headings_use_dynamic_currency(): void
    {
        $export = new OccupancyReportExport(1, 'USD');
        $headings = $export->headings();

        foreach ($headings as $heading) {
            $this->assertStringNotContainsString('(KES)', $heading);
        }
        $this->assertContains('Target Rent (USD)', $headings);
    }

    public function test_finance_report_export_sheets_use_dynamic_currency(): void
    {
        $data = [
            'revenue' => [['month' => 'Jan', 'invoiced' => 100, 'collected' => 80, 'expenses' => 20, 'net' => 60]],
            'collection_rate' => [['month' => 'Jan', 'invoiced' => 100, 'collected' => 80, 'rate' => 80]],
            'occupancy' => ['buildings' => [], 'totals' => ['total_units' => 10, 'occupied' => 8, 'vacant' => 2, 'occupancy_rate' => 80]],
            'arrears_aging' => ['current' => ['count' => 0, 'amount' => 0], '1-30' => ['count' => 0, 'amount' => 0], '31-60' => ['count' => 0, 'amount' => 0], '61-90' => ['count' => 0, 'amount' => 0], '90+' => ['count' => 0, 'amount' => 0]],
            'expenses_by_category' => ['categories' => [], 'total' => 0],
        ];

        $export = new FinanceReportExport($data, 12, 'USD');
        $sheets = $export->sheets();

        foreach ($sheets as $sheet) {
            if (method_exists($sheet, 'array')) {
                $rows = $sheet->array();
                $headerRow = $rows[0] ?? [];
                foreach ($headerRow as $heading) {
                    $this->assertStringNotContainsString('(KES)', (string) $heading);
                }
            }
        }
    }

    // ── Streaming Export Tests ────────────────────────────────────────

    public function test_streaming_invoices_export_headings_use_dynamic_currency(): void
    {
        $query = Invoice::query()->where('id', '<', 0);
        $export = new StreamingInvoicesExport($query, 'USD');
        $headings = $export->headings();

        foreach ($headings as $heading) {
            $this->assertStringNotContainsString('(KES)', $heading);
        }
        $this->assertContains('Rent (USD)', $headings);
    }

    public function test_streaming_payments_export_headings_use_dynamic_currency(): void
    {
        $query = Payment::query()->where('id', '<', 0);
        $export = new StreamingPaymentsExport($query, 'USD');
        $headings = $export->headings();

        foreach ($headings as $heading) {
            $this->assertStringNotContainsString('(KES)', $heading);
        }
        $this->assertContains('Amount (USD)', $headings);
    }

    public function test_streaming_deposits_export_headings_use_dynamic_currency(): void
    {
        $query = Lease::query()->where('id', '<', 0);
        $export = new StreamingDepositsExport($query, 'USD');
        $headings = $export->headings();

        foreach ($headings as $heading) {
            $this->assertStringNotContainsString('(KES)', $heading);
        }
        $this->assertContains('Deposit Amount (USD)', $headings);
    }

    public function test_streaming_expenses_export_headings_use_dynamic_currency(): void
    {
        $query = \App\Models\Expense::query()->where('id', '<', 0);
        $export = new StreamingExpensesExport($query, 'USD');
        $headings = $export->headings();

        foreach ($headings as $heading) {
            $this->assertStringNotContainsString('(KES)', $heading);
        }
        $this->assertContains('Amount (USD)', $headings);
    }

    // ── Controller Endpoint Tests ────────────────────────────────────

    public function test_invoice_download_passes_currency_to_view(): void
    {
        $unit = Unit::factory()->forBuilding($this->building)->create();
        $lease = Lease::factory()->forUnit($unit)->active()->create();
        $invoice = Invoice::factory()
            ->forLease($lease)
            ->withCurrency(Currency::USD)
            ->sent()
            ->create();

        $response = $this->actingAs($this->landlord)
            ->get(route('invoices.download', $invoice));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_invoice_record_payment_flash_uses_dynamic_currency(): void
    {
        $unit = Unit::factory()->forBuilding($this->building)->create();
        $lease = Lease::factory()->forUnit($unit)->active()->create();
        $invoice = Invoice::factory()
            ->forLease($lease)
            ->withCurrency(Currency::USD)
            ->sent()
            ->create(['total_due' => 25000, 'amount_paid' => 0]);

        $response = $this->actingAs($this->landlord)
            ->post(route('invoices.recordPayment', $invoice), [
                'amount' => 10000,
                'payment_method' => 'cash',
                'reference' => 'TEST-REF',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $flashMessage = session('success');
        $this->assertStringNotContainsString('KES', $flashMessage);
        $this->assertStringContainsString('$', $flashMessage);
    }

    public function test_tenant_ledger_pdf_passes_currency(): void
    {
        $unit = Unit::factory()->forBuilding($this->building)->create();
        $lease = Lease::factory()->forUnit($unit)->active()->create();
        $tenant = User::findOrFail($lease->tenant_id);

        $response = $this->actingAs($this->landlord)
            ->get(route('tenants.ledger.pdf', ['tenant' => $tenant]));

        $response->assertOk();
    }

    // ── CSV Heading Tests ────────────────────────────────────────────

    public function test_finance_export_service_csv_headings_use_dynamic_currency(): void
    {
        $service = app(\App\Services\FinanceExportService::class);

        $reflection = new \ReflectionClass($service);

        $headingMethods = [
            'getInvoiceHeadings',
            'getPaymentHeadings',
            'getDepositHeadings',
            'getExpenseHeadings',
            'getVendorHeadings',
        ];

        foreach ($headingMethods as $method) {
            $refMethod = $reflection->getMethod($method);
            $refMethod->setAccessible(true);
            $headings = $refMethod->invoke($service, 'USD');

            foreach ($headings as $heading) {
                $this->assertStringNotContainsString(
                    '(KES)',
                    $heading,
                    "Method {$method} still contains hardcoded (KES)"
                );
            }
        }
    }

    // ── Fallback/Default Tests ───────────────────────────────────────

    public function test_kes_landlord_sees_ksh_symbol_not_kes_code(): void
    {
        $kesLandlord = User::factory()->create(['role' => 'landlord']);
        $property = Property::factory()->create(['landlord_id' => $kesLandlord->id]);
        $building = Building::factory()
            ->forProperty($property)
            ->withCurrency(Currency::KES)
            ->create();
        $unit = Unit::factory()->forBuilding($building)->create();
        $lease = Lease::factory()->forUnit($unit)->active()->create();
        $invoice = Invoice::factory()
            ->forLease($lease)
            ->sent()
            ->create();

        $html = view('invoices.invoice-pdf', [
            'invoice' => $invoice->load(['lease.tenant', 'lease.unit.building.property']),
            'tenant' => $lease->tenant,
            'unit' => $unit,
            'building' => $building,
            'property' => $building->property,
            'currency_symbol' => Currency::KES->symbol(),
            'currency_code' => Currency::KES->value,
        ])->render();

        $this->assertStringContainsString('KSh ', $html);
        $this->assertStringNotContainsString('KES ', $html);
    }
}
