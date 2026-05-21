<?php

declare(strict_types=1);

namespace Tests\Feature\Wallet;

use App\Models\CreditNote;
use App\Models\Invoice;
use App\Models\Lease;
use App\Models\User;
use App\Services\Tenant\StatementService;
use App\Services\Wallet\WalletService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * Phase-76 STATEMENT-WALLET: credit-note applications reduce the statement
 * running balance (once); wallet movements appear as informational rows that do
 * NOT alter charges−payments−credits (no double-count).
 */
class Phase76StatementWalletTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    private User $landlord;

    private User $tenant;

    private Lease $lease;

    private CarbonImmutable $from;

    private CarbonImmutable $to;

    protected function setUp(): void
    {
        parent::setUp();
        $setup = $this->createLandlordWithFullSetup();
        $this->landlord = $setup['landlord'];
        ['tenant' => $this->tenant, 'lease' => $this->lease] = Model::withoutEvents(
            fn () => $this->createTenantWithActiveLease($this->landlord, $setup['units']->first()),
        );
        $this->from = CarbonImmutable::now()->startOfMonth();
        $this->to = CarbonImmutable::now()->endOfMonth();
    }

    private function invoice(Lease $lease, float $total): Invoice
    {
        return Model::withoutEvents(fn () => Invoice::factory()->forLease($lease)->create([
            'currency' => 'KES',
            'status' => 'sent',
            'total_due' => $total,
            'amount_paid' => 0,
            'billing_period_start' => CarbonImmutable::now()->toDateString(),
        ]));
    }

    private function rows(): \Illuminate\Support\Collection
    {
        return app(StatementService::class)->forTenant($this->tenant, $this->from, $this->to);
    }

    public function test_credit_note_application_reduces_the_running_balance(): void
    {
        $invoice = $this->invoice($this->lease, 1000.0);

        Model::withoutEvents(fn () => CreditNote::factory()->create([
            'landlord_id' => $this->landlord->id,
            'lease_id' => $this->lease->id,
            'tenant_id' => $this->tenant->id,
            'invoice_id' => $invoice->id,
            'applied_to_invoice_id' => $invoice->id,
            'amount' => 300.0,
            'applied_amount' => 300.0,
            'applied_at' => CarbonImmutable::now(),
            'status' => CreditNote::STATUS_APPLIED,
        ]));

        $rows = $this->rows();

        $this->assertNotNull($rows->firstWhere('kind', 'credit_note'));
        $this->assertEqualsWithDelta(700.0, (float) $rows->firstWhere('kind', 'closing')['running_balance'], 0.001);
    }

    public function test_wallet_movements_are_informational_and_do_not_change_the_balance(): void
    {
        $this->invoice($this->lease, 1000.0);
        $this->actingAs($this->landlord);
        DB::transaction(fn () => app(WalletService::class)->credit($this->lease, 500.0, 'overpay'));

        $rows = $this->rows();

        $this->assertNotNull($rows->firstWhere('kind', 'wallet_credit'));
        // Account balance = charges − payments − credits; the wallet credit does NOT reduce it.
        $this->assertEqualsWithDelta(1000.0, (float) $rows->firstWhere('kind', 'closing')['running_balance'], 0.001);
    }

    public function test_another_tenants_wallet_and_credits_never_leak(): void
    {
        $this->invoice($this->lease, 1000.0);

        ['lease' => $otherLease] = Model::withoutEvents(
            fn () => $this->createTenantWithActiveLease($this->landlord, $this->createLandlordWithFullSetup()['units']->first()),
        );
        $this->actingAs($this->landlord);
        DB::transaction(fn () => app(WalletService::class)->credit($otherLease, 999.0, 'theirs'));

        $rows = $this->rows();

        $this->assertNull($rows->firstWhere('reason', 'theirs'));
        $this->assertNull($rows->first(fn ($r) => ($r['amount'] ?? null) === 999.0));
    }

    public function test_index_payload_includes_wallet_balances(): void
    {
        $this->actingAs($this->landlord);
        DB::transaction(fn () => app(WalletService::class)->credit($this->lease, 750.0));

        $response = $this->actingAs($this->tenant)->get(route('tenant.statement.index'));
        $response->assertOk();

        $balances = $response->viewData('page')['props']['walletBalances'];
        $this->assertNotEmpty($balances);
        $this->assertSame('KES', $balances[0]['currency']);
    }
}
