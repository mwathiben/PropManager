<?php

declare(strict_types=1);

namespace Tests\Feature\Wallet;

use App\Services\Wallet\WalletService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * Phase-76 WALLET-DEEP surface watchdog — cross-category presence map a refactor
 * cannot silently regress. Behavioural tests live in the category sibling files.
 */
class Phase76WalletDeepSurfaceTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    // -- CREDIT-WALLET + MULTI-CCY -----------------------------------------

    public function test_wallet_service_and_schema_present(): void
    {
        $this->assertTrue(class_exists(\App\Services\Wallet\WalletService::class));
        $this->assertTrue(class_exists(\App\Models\LeaseWalletBalance::class));
        $this->assertTrue(class_exists(\App\Exceptions\CurrencyMismatchException::class));
        $this->assertTrue(Schema::hasTable('lease_wallet_balances'));
        $this->assertTrue(Schema::hasColumn('wallet_transactions', 'currency'));
        $this->assertTrue(Schema::hasColumn('wallet_transactions', 'credit_note_id'));
    }

    public function test_credit_note_policy_registered_and_route_present(): void
    {
        $gate = app(\Illuminate\Contracts\Auth\Access\Gate::class);
        $this->assertSame(
            \App\Policies\CreditNotePolicy::class,
            $gate->getPolicyFor(\App\Models\CreditNote::class)::class,
        );
        $this->assertTrue(Route::has('credit-notes.apply-to-wallet'));
    }

    // -- AUTO-APPLY --------------------------------------------------------

    public function test_auto_apply_settings_resolver_and_crons_present(): void
    {
        $this->assertTrue(Schema::hasTable('landlord_wallet_settings'));
        $this->assertTrue(class_exists(\App\Services\Wallet\WalletAutoApplyResolver::class));
        $this->assertTrue(Route::has('wallet.settings'));
        $this->assertTrue(Route::has('wallet.settings.update'));

        $commands = collect(Schedule::events())->map(fn ($e) => (string) $e->command)->implode(' ');
        $this->assertStringContainsString('wallet:auto-apply', $commands);
        $this->assertStringContainsString('wallet:rollup', $commands);
    }

    // -- TENANT-APPLY ------------------------------------------------------

    public function test_tenant_wallet_surface_present(): void
    {
        $this->assertTrue(Route::has('tenant.wallet.index'));
        $this->assertTrue(Route::has('tenant.wallet.apply'));
        $this->assertFileExists(base_path('resources/js/Pages/TenantFinances/Wallet.vue'));
        $this->assertFileExists(base_path('resources/js/Pages/Wallet/Settings.vue'));

        foreach (['en', 'sw', 'ar'] as $locale) {
            app()->setLocale($locale);
            $this->assertNotSame('tenant.wallet.title', __('tenant.wallet.title'));
            $this->assertNotSame('wallet.settings.title', __('wallet.settings.title'));
        }
    }

    // -- STATEMENT-WALLET --------------------------------------------------

    public function test_statement_wallet_tokens_present(): void
    {
        $vue = (string) file_get_contents(base_path('resources/js/Pages/Tenant/Statement.vue'));
        $this->assertStringContainsString('statement-wallet-balance', $vue);
        $this->assertStringContainsString('wallet_credit', $vue);
    }

    // -- CI ----------------------------------------------------------------

    public function test_rollup_gauges_emitted_and_runbook_present(): void
    {
        $source = (string) file_get_contents(base_path('app/Console/Commands/WalletRollup.php'));
        $this->assertStringContainsString('wallet_total_credit_balance', $source);
        $this->assertStringContainsString('credit_notes_pending_count', $source);

        $runbook = (string) file_get_contents(base_path('docs/runbooks/wallet.md'));
        $this->assertStringContainsString('Phase-76', $runbook);
        $this->assertStringContainsString('WALLET-DEEP', $runbook);
    }

    public function test_wallet_rollup_command_runs_with_seeded_data(): void
    {
        $setup = $this->createLandlordWithFullSetup();
        $landlord = $setup['landlord'];
        $lease = Model::withoutEvents(
            fn () => $this->createTenantWithActiveLease($landlord, $setup['units']->first())['lease'],
        );
        $this->actingAs($landlord);
        DB::transaction(fn () => app(WalletService::class)->credit($lease, 1000.0));

        $this->artisan('wallet:rollup')->assertSuccessful();
    }
}
