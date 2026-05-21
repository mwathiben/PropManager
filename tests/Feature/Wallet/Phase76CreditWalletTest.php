<?php

declare(strict_types=1);

namespace Tests\Feature\Wallet;

use App\Enums\Currency;
use App\Models\CreditNote;
use App\Models\Invoice;
use App\Models\Lease;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\CreditNoteService;
use App\Services\Wallet\WalletService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * Phase-76 CREDIT-WALLET-2/3: credit note -> tenant wallet + CreditNotePolicy.
 */
class Phase76CreditWalletTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    private User $landlord;

    private Lease $lease;

    private CreditNoteService $service;

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
        $this->service = app(CreditNoteService::class);
        $this->wallet = app(WalletService::class);
    }

    private function approvedNote(float $amount, ?int $invoiceId = null): CreditNote
    {
        return Model::withoutEvents(fn () => CreditNote::factory()->approved($this->landlord)->create([
            'landlord_id' => $this->landlord->id,
            'lease_id' => $this->lease->id,
            'tenant_id' => $this->lease->tenant_id,
            'invoice_id' => $invoiceId,
            'amount' => $amount,
            'applied_amount' => 0,
        ]));
    }

    public function test_apply_to_wallet_credits_the_lease_and_marks_applied(): void
    {
        $note = $this->approvedNote(1000.0);

        $credited = $this->service->applyToWallet($note);

        $this->assertEqualsWithDelta(1000.0, $credited, 0.001);
        $this->assertEqualsWithDelta(1000.0, (float) $this->lease->fresh()->wallet_balance, 0.001);

        $fresh = $note->fresh();
        $this->assertSame(CreditNote::STATUS_APPLIED, $fresh->status);
        $this->assertEqualsWithDelta(1000.0, (float) $fresh->applied_amount, 0.001);

        $this->assertSame(1, WalletTransaction::where('credit_note_id', $note->id)->count());
    }

    public function test_apply_to_wallet_is_idempotent(): void
    {
        $note = $this->approvedNote(1000.0);

        $this->service->applyToWallet($note);
        $second = $this->service->applyToWallet($note->fresh());

        $this->assertEqualsWithDelta(0.0, $second, 0.001);
        $this->assertEqualsWithDelta(1000.0, (float) $this->lease->fresh()->wallet_balance, 0.001);
        $this->assertSame(1, WalletTransaction::where('credit_note_id', $note->id)->count());
    }

    public function test_apply_to_wallet_uses_the_invoice_currency(): void
    {
        $invoice = Model::withoutEvents(
            fn () => Invoice::factory()->forLease($this->lease)->create(['currency' => 'USD', 'status' => 'sent']),
        );
        $note = $this->approvedNote(500.0, $invoice->id);

        $this->service->applyToWallet($note);

        $this->assertEqualsWithDelta(0.0, (float) $this->lease->fresh()->wallet_balance, 0.001);
        $this->assertEqualsWithDelta(500.0, $this->wallet->balanceFor($this->lease, Currency::USD), 0.001);
    }

    public function test_pending_note_cannot_be_applied_to_wallet(): void
    {
        $note = Model::withoutEvents(fn () => CreditNote::factory()->pending()->create([
            'landlord_id' => $this->landlord->id,
            'lease_id' => $this->lease->id,
            'tenant_id' => $this->lease->tenant_id,
            'invoice_id' => null,
            'amount' => 1000.0,
            'applied_amount' => 0,
        ]));

        $credited = $this->service->applyToWallet($note);

        $this->assertEqualsWithDelta(0.0, $credited, 0.001);
        $this->assertEqualsWithDelta(0.0, (float) $this->lease->fresh()->wallet_balance, 0.001);
    }

    public function test_policy_blocks_another_landlord_but_allows_the_owner(): void
    {
        $note = $this->approvedNote(1000.0);

        $other = Model::withoutEvents(fn () => $this->createLandlordWithFullSetup()['landlord']);

        $this->assertTrue(Gate::forUser($this->landlord)->allows('applyToWallet', $note));
        $this->assertFalse(Gate::forUser($other)->allows('applyToWallet', $note));
    }
}
