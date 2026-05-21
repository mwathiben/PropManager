<?php

declare(strict_types=1);

namespace Tests\Feature\Wallet;

use App\Enums\Currency;
use App\Models\Invoice;
use App\Models\Lease;
use App\Models\User;
use App\Services\Wallet\WalletService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * Phase-76 TENANT-APPLY: tenant self-service wallet apply (own invoices only,
 * same-currency only).
 */
class Phase76TenantApplyTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    private User $landlord;

    private User $tenant;

    private Lease $lease;

    private WalletService $wallet;

    protected function setUp(): void
    {
        parent::setUp();
        $setup = $this->createLandlordWithFullSetup();
        $this->landlord = $setup['landlord'];
        ['tenant' => $this->tenant, 'lease' => $this->lease] = Model::withoutEvents(
            fn () => $this->createTenantWithActiveLease($this->landlord, $setup['units']->first()),
        );
        $this->walletUnits = $setup['units'];
        $this->wallet = app(WalletService::class);
    }

    /** @var \Illuminate\Support\Collection */
    private $walletUnits;

    private function creditLease(Lease $lease, float $amount, ?Currency $currency = null): void
    {
        $this->actingAs($this->landlord);
        DB::transaction(fn () => $this->wallet->credit($lease, $amount, null, null, $currency));
    }

    private function invoice(Lease $lease, float $total, string $currency = 'KES'): Invoice
    {
        return Model::withoutEvents(fn () => Invoice::factory()->forLease($lease)->create([
            'currency' => $currency,
            'status' => 'sent',
            'total_due' => $total,
            'amount_paid' => 0,
            'due_date' => now()->subDays(5)->toDateString(),
        ]));
    }

    public function test_tenant_applies_own_wallet_to_own_invoice(): void
    {
        $this->creditLease($this->lease, 1000.0);
        $invoice = $this->invoice($this->lease, 1000.0);

        $this->actingAs($this->tenant)
            ->post(route('tenant.wallet.apply'), ['invoice_id' => $invoice->id])
            ->assertRedirect();

        $this->assertEqualsWithDelta(1000.0, (float) $invoice->fresh()->amount_paid, 0.001);
        $this->assertEqualsWithDelta(0.0, (float) $this->lease->fresh()->wallet_balance, 0.001);
    }

    public function test_tenant_cannot_apply_to_another_tenants_invoice(): void
    {
        $this->creditLease($this->lease, 1000.0);

        ['lease' => $otherLease] = Model::withoutEvents(
            fn () => $this->createTenantWithActiveLease($this->landlord, $this->walletUnits->get(1)),
        );
        $otherInvoice = $this->invoice($otherLease, 1000.0);

        $this->actingAs($this->tenant)
            ->post(route('tenant.wallet.apply'), ['invoice_id' => $otherInvoice->id])
            ->assertForbidden();

        $this->assertEqualsWithDelta(0.0, (float) $otherInvoice->fresh()->amount_paid, 0.001);
    }

    public function test_cross_currency_application_is_rejected(): void
    {
        $this->creditLease($this->lease, 500.0, Currency::USD);
        $kesInvoice = $this->invoice($this->lease, 1000.0, 'KES');

        $this->actingAs($this->tenant)
            ->post(route('tenant.wallet.apply'), ['invoice_id' => $kesInvoice->id])
            ->assertSessionHasErrors('wallet');

        $this->assertEqualsWithDelta(0.0, (float) $kesInvoice->fresh()->amount_paid, 0.001);
        $this->assertEqualsWithDelta(500.0, $this->wallet->balanceFor($this->lease, Currency::USD), 0.001);
    }

    public function test_index_shows_balances_and_outstanding_invoices(): void
    {
        $this->creditLease($this->lease, 1000.0);
        $this->invoice($this->lease, 1000.0);

        $response = $this->actingAs($this->tenant)->get(route('tenant.wallet.index'));
        $response->assertOk();

        $props = $response->viewData('page')['props'];
        $this->assertTrue($props['hasLease']);
        $this->assertNotEmpty($props['balances']);
        $this->assertNotEmpty($props['invoices']);
    }
}
