<?php

declare(strict_types=1);

namespace Tests\Feature\Integrations;

use App\Events\DepositRefundPaid;
use App\Models\DepositRefundRequest;
use App\Models\MpesaB2cRequest;
use App\Models\User;
use App\Services\Mpesa\DepositRefundPayoutService;
use App\Services\MpesaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Mockery;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

class Phase30MpesaB2cTest extends TestCase
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

    public function test_payout_creates_row_and_calls_initiate_b2c(): void
    {
        $refund = $this->approvedRefund();

        $mpesa = Mockery::mock(MpesaService::class);
        $mpesa->shouldReceive('withConfig')->andReturnSelf();
        $mpesa->shouldReceive('initiateB2C')->once()->andReturn([
            'ConversationID' => 'AG_20260516_X',
            'OriginatorConversationID' => 'orig-1',
            'ResponseDescription' => 'Accepted',
        ]);
        $this->app->instance(MpesaService::class, $mpesa);

        $service = app(DepositRefundPayoutService::class);
        $row = $service->payout($refund, '+254712345678');

        $this->assertSame(MpesaB2cRequest::STATUS_SENT, $row->status);
        $this->assertSame('AG_20260516_X', $row->conversation_id);
        $this->assertNotNull($row->sent_at);
        $this->assertSame($this->landlord->id, $row->landlord_id);
    }

    public function test_payout_is_idempotent_for_same_refund(): void
    {
        $refund = $this->approvedRefund();

        $mpesa = Mockery::mock(MpesaService::class);
        $mpesa->shouldReceive('withConfig')->andReturnSelf();
        $mpesa->shouldReceive('initiateB2C')->once()->andReturn([
            'ConversationID' => 'AG_X',
        ]);
        $this->app->instance(MpesaService::class, $mpesa);

        $service = app(DepositRefundPayoutService::class);
        $first = $service->payout($refund, '+254712345678');
        $second = $service->payout($refund, '+254712345678');

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, MpesaB2cRequest::query()->withoutGlobalScopes()->where('source_id', $refund->id)->count());
    }

    public function test_payout_marks_failed_when_initiate_returns_null(): void
    {
        $refund = $this->approvedRefund();

        $mpesa = Mockery::mock(MpesaService::class);
        $mpesa->shouldReceive('withConfig')->andReturnSelf();
        $mpesa->shouldReceive('initiateB2C')->once()->andReturn(null);
        $this->app->instance(MpesaService::class, $mpesa);

        $service = app(DepositRefundPayoutService::class);
        $row = $service->payout($refund, '+254712345678');

        $this->assertSame(MpesaB2cRequest::STATUS_FAILED, $row->status);
        $this->assertStringContainsString('unreachable', strtolower((string) $row->failure_reason));
    }

    public function test_payout_rejects_non_approved_refund(): void
    {
        $refund = DepositRefundRequest::create([
            'landlord_id' => $this->landlord->id,
            'tenant_id' => $this->tenant->id,
            'lease_id' => $this->lease->id,
            'requested_amount_cents' => 2_000_000,
            'payment_method' => 'mpesa',
            'payment_details' => ['phone' => '+254712345678'],
            'status' => DepositRefundRequest::STATUS_SUBMITTED,
            'submitted_at' => now(),
        ]);

        $service = app(DepositRefundPayoutService::class);
        $this->expectException(\DomainException::class);
        $service->payout($refund, '+254712345678');
    }

    public function test_controller_pay_via_mpesa_routes_to_service(): void
    {
        $refund = $this->approvedRefund();

        $mpesa = Mockery::mock(MpesaService::class);
        $mpesa->shouldReceive('withConfig')->andReturnSelf();
        $mpesa->shouldReceive('initiateB2C')->once()->andReturn(['ConversationID' => 'AG_C']);
        $this->app->instance(MpesaService::class, $mpesa);

        $this->actingAs($this->landlord)
            ->post(route('finance.deposit-refunds.pay-mpesa', ['refund' => $refund->id]), [
                'phone' => '+254712345678',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('mpesa_b2c_requests', [
            'source_id' => $refund->id,
            'status' => MpesaB2cRequest::STATUS_SENT,
        ]);
    }

    public function test_other_landlord_cannot_pay_via_mpesa(): void
    {
        $refund = $this->approvedRefund();
        $other = User::factory()->create(['role' => 'landlord']);

        $this->actingAs($other)
            ->post(route('finance.deposit-refunds.pay-mpesa', ['refund' => $refund->id]), [
                'phone' => '+254712345678',
            ])
            ->assertForbidden();
    }

    public function test_reconcile_status_flips_to_succeeded_and_confirms_refund(): void
    {
        Event::fake([DepositRefundPaid::class]);
        $refund = $this->approvedRefund();

        $row = MpesaB2cRequest::create([
            'landlord_id' => $this->landlord->id,
            'source_type' => DepositRefundRequest::class,
            'source_id' => $refund->id,
            'phone' => '+254712345678',
            'amount_cents' => 2_500_000,
            'reference' => 'DRR-'.$refund->id,
            'status' => MpesaB2cRequest::STATUS_SENT,
            'originator_conversation_id' => 'orig-'.$refund->id,
            'conversation_id' => 'AG_RECONCILE_1',
            'sent_at' => now()->subMinutes(10),
        ]);

        \App\Models\PaymentConfiguration::create([
            'landlord_id' => $this->landlord->id,
            'mpesa_environment' => 'sandbox',
            'mpesa_consumer_key' => 'ck',
            'mpesa_consumer_secret' => 'cs',
            'mpesa_b2c_shortcode' => '600999',
            'mpesa_b2c_initiator' => 'testapi',
            'mpesa_b2c_security_credential' => 'cred',
        ]);

        $mpesa = Mockery::mock(MpesaService::class);
        $mpesa->shouldReceive('withConfig')->andReturnSelf();
        $mpesa->shouldReceive('queryTransactionStatus')->once()->andReturn([
            'Result' => [
                'ResultCode' => '0',
                'TransactionID' => 'NLJ7RT61SV',
            ],
        ]);
        $this->app->instance(MpesaService::class, $mpesa);

        $this->artisan('mpesa:reconcile-status')->assertSuccessful();

        $row->refresh();
        $this->assertSame(MpesaB2cRequest::STATUS_SUCCEEDED, $row->status);
        $this->assertSame('NLJ7RT61SV', $row->transaction_id);

        $refund->refresh();
        $this->assertSame(DepositRefundRequest::STATUS_PAID, $refund->status);
        Event::assertDispatched(DepositRefundPaid::class);
    }

    private function approvedRefund(): DepositRefundRequest
    {
        return DepositRefundRequest::create([
            'landlord_id' => $this->landlord->id,
            'tenant_id' => $this->tenant->id,
            'lease_id' => $this->lease->id,
            'requested_amount_cents' => 2_500_000,
            'final_amount_cents' => 2_500_000,
            'payment_method' => 'mpesa',
            'payment_details' => ['phone' => '+254712345678'],
            'status' => DepositRefundRequest::STATUS_APPROVED,
            'submitted_at' => now(),
            'reviewed_at' => now(),
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
