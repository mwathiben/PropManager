<?php

declare(strict_types=1);

namespace Tests\Feature\Commands;

use App\Mail\ReconciliationAlert;
use App\Models\PaymentConfiguration;
use App\Models\User;
use App\Services\Reconciliation\PaymentReconciliationService;
use App\ValueObjects\ReconciliationDiscrepancy;
use App\ValueObjects\ReconciliationResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Mockery;
use Tests\TestCase;

class DailyPaymentReconciliationCommandTest extends TestCase
{
    use RefreshDatabase;

    private function createLandlordWithPaystack(array $overrides = []): User
    {
        $landlord = User::factory()->create(array_merge([
            'role' => 'landlord',
            'is_archived' => false,
        ], $overrides));

        PaymentConfiguration::factory()
            ->forLandlord($landlord)
            ->withPaystack()
            ->create([
                'paystack_public_key' => 'pk_test_'.uniqid(),
                'paystack_secret_key' => 'sk_test_'.uniqid(),
            ]);

        return $landlord;
    }

    private function mockReconciliationService(ReconciliationResult $result): void
    {
        $mock = Mockery::mock(PaymentReconciliationService::class);
        $mock->shouldReceive('reconcilePaystack')->andReturn($result);
        $this->app->instance(PaymentReconciliationService::class, $mock);
    }

    private function cleanResult(): ReconciliationResult
    {
        return new ReconciliationResult(
            discrepancies: [],
            localCount: 10,
            remoteCount: 10,
            matchedCount: 10,
            reconciledAt: now()->toIso8601String(),
        );
    }

    private function resultWithDiscrepancies(int $count = 1): ReconciliationResult
    {
        $discrepancies = [];
        for ($i = 0; $i < $count; $i++) {
            $discrepancies[] = ReconciliationDiscrepancy::missingLocally(
                "REF_MISSING_{$i}",
                5000.00 + ($i * 1000),
                'KES',
                'success',
            );
        }

        return new ReconciliationResult(
            discrepancies: $discrepancies,
            localCount: 10,
            remoteCount: 10 + $count,
            matchedCount: 10,
            reconciledAt: now()->toIso8601String(),
        );
    }

    public function test_skips_landlords_without_paystack_config(): void
    {
        User::factory()->create(['role' => 'landlord', 'is_archived' => false]);

        $this->artisan('reconciliation:run-daily')
            ->assertSuccessful();

        $this->assertDatabaseCount('reconciliation_reports', 0);
    }

    public function test_stores_completed_report_for_configured_landlord(): void
    {
        $landlord = $this->createLandlordWithPaystack();
        $this->mockReconciliationService($this->cleanResult());
        Mail::fake();

        $this->artisan('reconciliation:run-daily')
            ->assertSuccessful();

        $this->assertDatabaseHas('reconciliation_reports', [
            'landlord_id' => $landlord->id,
            'provider' => 'paystack',
            'status' => 'completed',
            'local_count' => 10,
            'remote_count' => 10,
            'matched_count' => 10,
            'discrepancy_count' => 0,
        ]);
    }

    public function test_sends_alert_email_on_discrepancies(): void
    {
        Mail::fake();
        $landlord = $this->createLandlordWithPaystack();
        $this->mockReconciliationService($this->resultWithDiscrepancies(3));

        $this->artisan('reconciliation:run-daily')
            ->assertSuccessful();

        Mail::assertQueued(ReconciliationAlert::class, function (ReconciliationAlert $mail) use ($landlord) {
            return $mail->hasTo($landlord->email);
        });

        $this->assertDatabaseHas('reconciliation_reports', [
            'landlord_id' => $landlord->id,
            'discrepancy_count' => 3,
            'alert_sent' => true,
        ]);
    }

    public function test_does_not_send_alert_when_clean(): void
    {
        Mail::fake();
        $this->createLandlordWithPaystack();
        $this->mockReconciliationService($this->cleanResult());

        $this->artisan('reconciliation:run-daily')
            ->assertSuccessful();

        Mail::assertNotQueued(ReconciliationAlert::class);

        $this->assertDatabaseHas('reconciliation_reports', [
            'alert_sent' => false,
        ]);
    }

    public function test_stores_failed_report_on_api_error(): void
    {
        Mail::fake();
        $landlord = $this->createLandlordWithPaystack();

        $mock = Mockery::mock(PaymentReconciliationService::class);
        $mock->shouldReceive('reconcilePaystack')
            ->andThrow(new \RuntimeException('Paystack API timeout'));
        $this->app->instance(PaymentReconciliationService::class, $mock);

        $this->artisan('reconciliation:run-daily');

        $this->assertDatabaseHas('reconciliation_reports', [
            'landlord_id' => $landlord->id,
            'status' => 'failed',
            'error_message' => 'Paystack API timeout',
        ]);

        Mail::assertNotQueued(ReconciliationAlert::class);
    }

    public function test_continues_processing_after_single_landlord_failure(): void
    {
        Mail::fake();
        $failLandlord = $this->createLandlordWithPaystack();
        $successLandlord = $this->createLandlordWithPaystack();

        $callCount = 0;
        $mock = Mockery::mock(PaymentReconciliationService::class);
        $mock->shouldReceive('reconcilePaystack')
            ->andReturnUsing(function (int $landlordId) use ($failLandlord, &$callCount) {
                $callCount++;
                if ($landlordId === $failLandlord->id) {
                    throw new \RuntimeException('API failure');
                }

                return new ReconciliationResult(
                    discrepancies: [],
                    localCount: 5,
                    remoteCount: 5,
                    matchedCount: 5,
                    reconciledAt: now()->toIso8601String(),
                );
            });
        $this->app->instance(PaymentReconciliationService::class, $mock);

        $this->artisan('reconciliation:run-daily');

        $this->assertEquals(2, $callCount);
        $this->assertDatabaseCount('reconciliation_reports', 2);
        $this->assertDatabaseHas('reconciliation_reports', [
            'landlord_id' => $failLandlord->id,
            'status' => 'failed',
        ]);
        $this->assertDatabaseHas('reconciliation_reports', [
            'landlord_id' => $successLandlord->id,
            'status' => 'completed',
        ]);
    }

    public function test_dry_run_does_not_store_reports_or_send_alerts(): void
    {
        Mail::fake();
        $this->createLandlordWithPaystack();
        $this->mockReconciliationService($this->resultWithDiscrepancies(2));

        $this->artisan('reconciliation:run-daily --dry-run')
            ->assertSuccessful();

        $this->assertDatabaseCount('reconciliation_reports', 0);
        Mail::assertNotQueued(ReconciliationAlert::class);
    }

    public function test_landlord_option_filters_to_single_landlord(): void
    {
        Mail::fake();
        $target = $this->createLandlordWithPaystack();
        $other = $this->createLandlordWithPaystack();
        $this->mockReconciliationService($this->cleanResult());

        $this->artisan("reconciliation:run-daily --landlord={$target->id}")
            ->assertSuccessful();

        $this->assertDatabaseHas('reconciliation_reports', [
            'landlord_id' => $target->id,
        ]);
        $this->assertDatabaseMissing('reconciliation_reports', [
            'landlord_id' => $other->id,
        ]);
    }

    public function test_excludes_archived_landlords(): void
    {
        Mail::fake();
        $this->createLandlordWithPaystack(['is_archived' => true]);
        $this->mockReconciliationService($this->cleanResult());

        $this->artisan('reconciliation:run-daily')
            ->assertSuccessful();

        $this->assertDatabaseCount('reconciliation_reports', 0);
    }
}
