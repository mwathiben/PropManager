<?php

declare(strict_types=1);

namespace Tests\Feature\Wallet;

use App\Enums\Currency;
use App\Exceptions\CurrencyMismatchException;
use App\Models\Lease;
use App\Models\LeaseWalletBalance;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\Wallet\WalletService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * Phase-76 WALLET-CORE (CREDIT-WALLET-1 + MULTI-CCY): WalletService boundary +
 * per-currency balances (default currency in the Lease scalar, non-default in
 * lease_wallet_balances) + same-currency apply guard.
 */
class Phase76WalletCoreTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    private User $landlord;

    private Lease $lease;

    private WalletService $wallet;

    protected function setUp(): void
    {
        parent::setUp();
        $setup = $this->createLandlordWithFullSetup();
        $this->landlord = $setup['landlord'];
        $this->lease = Model::withoutEvents(
            fn () => $this->createTenantWithActiveLease($this->landlord, $setup['units']->first())['lease'],
        );
        $this->actingAs($this->landlord);
        $this->wallet = app(WalletService::class);
    }

    private function tx(callable $fn): mixed
    {
        return DB::transaction($fn);
    }

    public function test_default_currency_credit_moves_the_lease_scalar_and_creates_no_row(): void
    {
        $this->tx(fn () => $this->wallet->credit($this->lease, 1000.0, 'overpay'));

        $this->assertEqualsWithDelta(1000.0, (float) $this->lease->fresh()->wallet_balance, 0.001);
        $this->assertSame(0, LeaseWalletBalance::where('lease_id', $this->lease->id)->count());
    }

    public function test_non_default_currency_credit_creates_a_row_and_leaves_the_scalar(): void
    {
        $this->tx(fn () => $this->wallet->credit($this->lease, 500.0, 'usd overpay', null, Currency::USD));

        $this->assertEqualsWithDelta(0.0, (float) $this->lease->fresh()->wallet_balance, 0.001);
        $row = LeaseWalletBalance::where('lease_id', $this->lease->id)->where('currency', 'USD')->first();
        $this->assertNotNull($row);
        $this->assertEqualsWithDelta(500.0, (float) $row->balance, 0.001);
    }

    public function test_balances_for_returns_non_zero_balances_per_currency(): void
    {
        $this->tx(fn () => $this->wallet->credit($this->lease, 1000.0));
        $this->tx(fn () => $this->wallet->credit($this->lease, 500.0, null, null, Currency::USD));

        $balances = $this->wallet->balancesFor($this->lease->fresh());

        $this->assertEqualsWithDelta(1000.0, $balances['KES'], 0.001);
        $this->assertEqualsWithDelta(500.0, $balances['USD'], 0.001);
    }

    public function test_apply_caps_at_balance_default_currency(): void
    {
        $this->tx(fn () => $this->wallet->credit($this->lease, 1000.0));

        $drawn = $this->tx(fn () => $this->wallet->apply($this->lease->fresh(), 1500.0, 'invoice'));

        $this->assertEqualsWithDelta(1000.0, $drawn, 0.001);
        $this->assertEqualsWithDelta(0.0, (float) $this->lease->fresh()->wallet_balance, 0.001);
    }

    public function test_apply_non_default_currency_draws_from_the_row(): void
    {
        $this->tx(fn () => $this->wallet->credit($this->lease, 500.0, null, null, Currency::USD));

        $drawn = $this->tx(fn () => $this->wallet->apply($this->lease, 200.0, 'invoice', null, Currency::USD));

        $this->assertEqualsWithDelta(200.0, $drawn, 0.001);
        $this->assertEqualsWithDelta(300.0, $this->wallet->balanceFor($this->lease, Currency::USD), 0.001);
    }

    public function test_assert_same_currency_rejects_a_mismatch(): void
    {
        $this->expectException(CurrencyMismatchException::class);
        $this->wallet->assertSameCurrency(Currency::USD, Currency::KES);
    }

    public function test_credit_stamps_the_currency_on_the_transaction(): void
    {
        $this->tx(fn () => $this->wallet->credit($this->lease, 500.0, null, null, Currency::USD));

        $txn = WalletTransaction::where('lease_id', $this->lease->id)->where('currency', 'USD')->first();
        $this->assertNotNull($txn);
        $this->assertTrue($txn->isCredit());
        $this->assertSame(Currency::USD, $txn->currency);
    }

    public function test_ledger_filters_by_currency(): void
    {
        $this->tx(fn () => $this->wallet->credit($this->lease, 1000.0));
        $this->tx(fn () => $this->wallet->credit($this->lease, 500.0, null, null, Currency::USD));

        $this->assertSame(1, $this->wallet->ledger($this->lease, Currency::USD)->count());
        $this->assertSame(2, $this->wallet->ledger($this->lease)->count());
    }
}
