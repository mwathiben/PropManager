<?php

declare(strict_types=1);

namespace Tests\Feature\Wallet;

use App\Enums\Currency;
use App\Models\Invoice;
use App\Models\LandlordWalletSetting;
use App\Models\Lease;
use App\Models\User;
use App\Services\Wallet\WalletAutoApplyResolver;
use App\Services\Wallet\WalletService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * Phase-76 AUTO-APPLY: per-landlord auto-apply resolver + wallet:auto-apply
 * sweep cron + settings persistence.
 */
class Phase76AutoApplyTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    private User $landlord;

    private Lease $lease;

    private WalletService $wallet;

    private WalletAutoApplyResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $setup = $this->createLandlordWithFullSetup();
        $this->landlord = $setup['landlord'];
        $this->lease = Model::withoutEvents(
            fn () => $this->createTenantWithActiveLease($this->landlord, $setup['units']->first())['lease'],
        );
        $this->wallet = app(WalletService::class);
        $this->resolver = app(WalletAutoApplyResolver::class);
    }

    private function setMode(string $mode): void
    {
        LandlordWalletSetting::updateOrCreate(['landlord_id' => $this->landlord->id], ['auto_apply_mode' => $mode]);
        $this->resolver->flush($this->landlord->id);
    }

    private function invoice(float $total, string $dueDate): Invoice
    {
        return Model::withoutEvents(fn () => Invoice::factory()->forLease($this->lease)->create([
            'currency' => 'KES',
            'status' => 'sent',
            'total_due' => $total,
            'amount_paid' => 0,
            'due_date' => $dueDate,
        ]));
    }

    public function test_resolver_returns_override_else_config_default(): void
    {
        $this->assertSame('on_invoice_create', $this->resolver->mode($this->landlord->id));

        $this->setMode(LandlordWalletSetting::MODE_OFF);
        $this->assertSame('off', $this->resolver->mode($this->landlord->id));
    }

    public function test_sweep_applies_standing_credit_oldest_first(): void
    {
        $this->actingAs($this->landlord);
        DB::transaction(fn () => $this->wallet->credit($this->lease, 1500.0));
        $this->setMode(LandlordWalletSetting::MODE_OLDEST_FIRST_SWEEP);

        $older = $this->invoice(1000.0, now()->subDays(20)->toDateString());
        $newer = $this->invoice(1000.0, now()->subDays(5)->toDateString());

        $this->artisan('wallet:auto-apply')->assertSuccessful();

        $this->assertEqualsWithDelta(1000.0, (float) $older->fresh()->amount_paid, 0.001);
        $this->assertEqualsWithDelta(500.0, (float) $newer->fresh()->amount_paid, 0.001);
        $this->assertEqualsWithDelta(0.0, (float) $this->lease->fresh()->wallet_balance, 0.001);
    }

    public function test_sweep_skips_cross_currency_invoices(): void
    {
        $this->actingAs($this->landlord);
        DB::transaction(fn () => $this->wallet->credit($this->lease, 500.0, null, null, Currency::USD));
        $this->setMode(LandlordWalletSetting::MODE_OLDEST_FIRST_SWEEP);

        $kesInvoice = $this->invoice(1000.0, now()->subDays(10)->toDateString());

        $this->artisan('wallet:auto-apply')->assertSuccessful();

        // USD credit must not touch a KES invoice.
        $this->assertEqualsWithDelta(0.0, (float) $kesInvoice->fresh()->amount_paid, 0.001);
        $this->assertEqualsWithDelta(500.0, $this->wallet->balanceFor($this->lease, Currency::USD), 0.001);
    }

    public function test_off_mode_sweep_is_a_no_op(): void
    {
        $this->actingAs($this->landlord);
        DB::transaction(fn () => $this->wallet->credit($this->lease, 1000.0));
        $this->setMode(LandlordWalletSetting::MODE_OFF);

        $invoice = $this->invoice(1000.0, now()->subDays(10)->toDateString());

        $this->artisan('wallet:auto-apply')->assertSuccessful();

        $this->assertEqualsWithDelta(0.0, (float) $invoice->fresh()->amount_paid, 0.001);
        $this->assertEqualsWithDelta(1000.0, (float) $this->lease->fresh()->wallet_balance, 0.001);
    }

    public function test_settings_update_persists_mode(): void
    {
        $this->actingAs($this->landlord);

        $this->put(route('wallet.settings.update'), ['auto_apply_mode' => 'off'])
            ->assertRedirect();

        $this->assertSame('off', $this->resolver->mode($this->landlord->id));
        $this->assertDatabaseHas('landlord_wallet_settings', [
            'landlord_id' => $this->landlord->id,
            'auto_apply_mode' => 'off',
        ]);
    }

    public function test_settings_update_rejects_an_invalid_mode(): void
    {
        $this->actingAs($this->landlord);

        $this->put(route('wallet.settings.update'), ['auto_apply_mode' => 'bogus'])
            ->assertSessionHasErrors('auto_apply_mode');
    }
}
