<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Models\DepositTransaction;
use App\Models\Lease;
use App\Models\MoveOut;
use App\Models\MoveOutDeduction;
use App\Models\User;
use App\Services\Finance\DepositSettlementService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * Phase-81 DEPOSIT-SETTLEMENT: settle the deposit ledger at move-out.
 */
class Phase81DepositSettlementTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    private User $landlord;

    private Lease $lease;

    protected function setUp(): void
    {
        parent::setUp();
        $setup = $this->createLandlordWithFullSetup();
        $this->landlord = $setup['landlord'];
        $this->lease = Model::withoutEvents(
            fn () => $this->createTenantWithActiveLease($this->landlord, $setup['units']->get(0))['lease'],
        );
    }

    private function moveOut(array $attrs = []): MoveOut
    {
        return Model::withoutEvents(fn () => MoveOut::factory()
            ->forLease($this->lease)
            ->settlementPending()
            ->create(array_merge(['arrears_balance' => 0], $attrs)));
    }

    private function deduction(MoveOut $moveOut, float $amount): void
    {
        Model::withoutEvents(fn () => MoveOutDeduction::factory()->forMoveOut($moveOut)->create(['amount' => $amount]));
    }

    public function test_settlement_journals_deductions_arrears_and_partial_refund(): void
    {
        $held = (float) $this->lease->deposit_amount;
        $moveOut = $this->moveOut(['deposit_held' => $held, 'arrears_balance' => 1000]);
        $this->deduction($moveOut, 2000);

        $ran = app(DepositSettlementService::class)->settle($moveOut, $this->landlord);

        $this->assertTrue($ran);
        $this->assertSame('partial_refund', $this->lease->fresh()->deposit_status);
        $this->assertEqualsWithDelta($held - 3000, (float) $this->lease->fresh()->deposit_refund_amount, 0.01);
        $this->assertSame(2, DepositTransaction::where('lease_id', $this->lease->id)->where('type', DepositTransaction::TYPE_DEDUCTION)->count());
        $this->assertSame(1, DepositTransaction::where('lease_id', $this->lease->id)->where('type', DepositTransaction::TYPE_PARTIAL_REFUND)->count());
        $this->assertNotNull(DepositTransaction::where('lease_id', $this->lease->id)->first()->move_out_id);
    }

    public function test_settlement_full_refund_when_no_deductions(): void
    {
        $held = (float) $this->lease->deposit_amount;
        $moveOut = $this->moveOut(['deposit_held' => $held]);

        app(DepositSettlementService::class)->settle($moveOut, $this->landlord);

        $this->assertSame('refunded', $this->lease->fresh()->deposit_status);
        $this->assertSame(1, DepositTransaction::where('lease_id', $this->lease->id)->where('type', DepositTransaction::TYPE_FULL_REFUND)->count());
    }

    public function test_settlement_forfeits_when_fully_consumed(): void
    {
        $held = (float) $this->lease->deposit_amount;
        $moveOut = $this->moveOut(['deposit_held' => $held]);
        $this->deduction($moveOut, $held);

        app(DepositSettlementService::class)->settle($moveOut, $this->landlord);

        $this->assertSame('forfeited', $this->lease->fresh()->deposit_status);
        $this->assertSame(0, DepositTransaction::where('lease_id', $this->lease->id)->whereIn('type', [DepositTransaction::TYPE_PARTIAL_REFUND, DepositTransaction::TYPE_FULL_REFUND])->count());
    }

    public function test_settlement_is_idempotent(): void
    {
        $moveOut = $this->moveOut(['deposit_held' => (float) $this->lease->deposit_amount]);
        $service = app(DepositSettlementService::class);

        $this->assertTrue($service->settle($moveOut, $this->landlord));
        $countAfterFirst = DepositTransaction::where('lease_id', $this->lease->id)->count();
        $this->assertFalse($service->settle($moveOut->fresh(), $this->landlord));
        $this->assertSame($countAfterFirst, DepositTransaction::where('lease_id', $this->lease->id)->count());
    }

    public function test_completing_move_out_settles_the_deposit(): void
    {
        $moveOut = $this->moveOut(['deposit_held' => (float) $this->lease->deposit_amount]);
        $this->deduction($moveOut, 1500);

        $this->actingAs($this->landlord)
            ->post(route('move-outs.complete', $moveOut->id), ['settlement_method' => 'bank_transfer'])
            ->assertRedirect();

        $this->assertSame('partial_refund', $this->lease->fresh()->deposit_status);
        $this->assertTrue(DepositTransaction::where('lease_id', $this->lease->id)->where('move_out_id', $moveOut->id)->exists());
    }

    public function test_record_received_is_idempotent(): void
    {
        $service = app(DepositSettlementService::class);

        $this->assertTrue($service->recordReceived($this->lease));
        $this->assertFalse($service->recordReceived($this->lease->fresh()));
        $this->assertSame(1, DepositTransaction::where('lease_id', $this->lease->id)->where('type', DepositTransaction::TYPE_RECEIVED)->count());
    }

    public function test_backfill_received_command_creates_opening_entry(): void
    {
        $this->assertSame(0, DepositTransaction::where('type', DepositTransaction::TYPE_RECEIVED)->count());

        $this->artisan('deposits:backfill-received')->assertExitCode(0);

        $this->assertSame(1, DepositTransaction::where('lease_id', $this->lease->id)->where('type', DepositTransaction::TYPE_RECEIVED)->count());
    }
}
