<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\LateFee;
use App\Models\LateFeePolicy;
use App\Models\Refund;
use App\Models\User;
use App\Services\FinanceCacheService;
use App\Services\FinanceStatsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

class FinanceCacheTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    protected User $landlord;

    protected array $setupData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setupData = $this->createLandlordWithFullSetup();
        $this->landlord = $this->setupData['landlord'];
        Cache::flush();
    }

    public function test_finance_stats_are_cached(): void
    {
        $service = app(FinanceStatsService::class);

        $firstCall = $service->getHubStats($this->landlord->id);
        $cachedCall = $service->getHubStats($this->landlord->id);

        $this->assertEquals($firstCall, $cachedCall);

        $cacheKey = FinanceCacheService::statsKey('hub', $this->landlord->id);
        $this->assertTrue(Cache::has($cacheKey));
    }

    public function test_expense_creation_invalidates_cache(): void
    {
        $service = app(FinanceStatsService::class);

        $service->getExpenseStats($this->landlord->id);
        $cacheKey = FinanceCacheService::statsKey('expenses', $this->landlord->id);
        $this->assertTrue(Cache::has($cacheKey));

        $category = ExpenseCategory::create([
            'landlord_id' => $this->landlord->id,
            'name' => 'Test Category',
            'color' => '#FF0000',
        ]);

        Expense::create([
            'landlord_id' => $this->landlord->id,
            'category_id' => $category->id,
            'description' => 'Test expense',
            'amount' => 5000,
            'expense_date' => now(),
            'payment_method' => 'cash',
        ]);

        $this->assertFalse(Cache::has($cacheKey));
    }

    public function test_late_fee_creation_invalidates_cache(): void
    {
        $service = app(FinanceStatsService::class);

        $service->getLateFeeStats($this->landlord->id);
        $cacheKey = FinanceCacheService::statsKey('latefees', $this->landlord->id);
        $this->assertTrue(Cache::has($cacheKey));

        $unit = $this->setupData['units']->first();
        ['lease' => $lease] = $this->createTenantWithActiveLease($this->landlord, $unit);
        $invoice = $this->createInvoiceForLease($lease, 'overdue');

        $policy = LateFeePolicy::create([
            'landlord_id' => $this->landlord->id,
            'name' => 'Test Policy',
            'grace_period_days' => 5,
            'fee_type' => 'flat_amount',
            'fee_amount' => 500,
            'is_active' => true,
        ]);

        LateFee::create([
            'invoice_id' => $invoice->id,
            'late_fee_policy_id' => $policy->id,
            'landlord_id' => $this->landlord->id,
            'fee_amount' => 500,
            'cumulative_total' => 500,
            'applied_date' => now(),
            'days_overdue' => 10,
            'is_waived' => false,
        ]);

        $this->assertFalse(Cache::has($cacheKey));
    }

    public function test_late_fee_policy_change_invalidates_cache(): void
    {
        $service = app(FinanceStatsService::class);

        $policy = LateFeePolicy::create([
            'landlord_id' => $this->landlord->id,
            'name' => 'Test Policy',
            'grace_period_days' => 5,
            'fee_type' => 'flat_amount',
            'fee_amount' => 500,
            'is_active' => true,
        ]);

        $service->getLateFeeStats($this->landlord->id);
        $cacheKey = FinanceCacheService::statsKey('latefees', $this->landlord->id);
        $this->assertTrue(Cache::has($cacheKey));

        $policy->update(['is_active' => false]);

        $this->assertFalse(Cache::has($cacheKey));
    }

    public function test_refund_creation_invalidates_cache(): void
    {
        $service = app(FinanceStatsService::class);

        $unit = $this->setupData['units']->first();
        ['lease' => $lease] = $this->createTenantWithActiveLease($this->landlord, $unit);
        ['payment' => $payment, 'invoice' => $invoice] = $this->createPaymentWithInvoice($lease, 5000);

        $service->getHubStats($this->landlord->id);
        $cacheKey = FinanceCacheService::statsKey('hub', $this->landlord->id);
        $this->assertTrue(Cache::has($cacheKey));

        Refund::create([
            'payment_id' => $payment->id,
            'invoice_id' => $invoice->id,
            'landlord_id' => $this->landlord->id,
            'amount' => 1000,
            'status' => 'pending',
            'reason' => 'Test refund',
            'payment_method' => 'cash',
            'initiated_by' => $this->landlord->id,
        ]);

        $this->assertFalse(Cache::has($cacheKey));
    }

    public function test_lease_deposit_change_invalidates_cache(): void
    {
        $service = app(FinanceStatsService::class);

        $unit = $this->setupData['units']->first();
        ['lease' => $lease] = $this->createTenantWithActiveLease($this->landlord, $unit);

        $service->getDepositStats($this->landlord->id);
        $cacheKey = FinanceCacheService::statsKey('deposits', $this->landlord->id);
        $this->assertTrue(Cache::has($cacheKey));

        $lease->update(['deposit_status' => 'refunded']);

        $this->assertFalse(Cache::has($cacheKey));
    }

    public function test_lease_non_deposit_change_does_not_invalidate_cache(): void
    {
        $service = app(FinanceStatsService::class);

        $unit = $this->setupData['units']->first();
        ['lease' => $lease] = $this->createTenantWithActiveLease($this->landlord, $unit);

        $service->getDepositStats($this->landlord->id);
        $cacheKey = FinanceCacheService::statsKey('deposits', $this->landlord->id);
        $this->assertTrue(Cache::has($cacheKey));

        $lease->update(['rent_amount' => 30000]);

        $this->assertTrue(Cache::has($cacheKey));
    }

    public function test_warm_finance_cache_command(): void
    {
        $this->artisan('finance:warm-cache', ['--landlord' => $this->landlord->id])
            ->expectsOutput('Warming finance cache for 1 landlord(s)...')
            ->assertSuccessful();

        $this->assertTrue(Cache::has(FinanceCacheService::statsKey('hub', $this->landlord->id)));
        $this->assertTrue(Cache::has(FinanceCacheService::statsKey('deposits', $this->landlord->id)));
        $this->assertTrue(Cache::has(FinanceCacheService::statsKey('latefees', $this->landlord->id)));
        $this->assertTrue(Cache::has(FinanceCacheService::statsKey('expenses', $this->landlord->id)));
        $this->assertTrue(Cache::has(FinanceCacheService::statsKey('trend', $this->landlord->id)));
    }

    public function test_warm_cache_command_handles_invalid_landlord(): void
    {
        $this->artisan('finance:warm-cache', ['--landlord' => 999999])
            ->expectsOutput('Landlord with ID 999999 not found.')
            ->assertSuccessful();
    }

    public function test_cached_response_is_faster_than_uncached(): void
    {
        $service = app(FinanceStatsService::class);

        $unit = $this->setupData['units']->first();
        ['lease' => $lease] = $this->createTenantWithActiveLease($this->landlord, $unit);

        for ($i = 0; $i < 5; $i++) {
            $this->createInvoiceForLease($lease, 'sent');
        }

        $start = microtime(true);
        $service->getHubStats($this->landlord->id);
        $firstCallMs = (microtime(true) - $start) * 1000;

        $start = microtime(true);
        $service->getHubStats($this->landlord->id);
        $cachedCallMs = (microtime(true) - $start) * 1000;

        $this->assertLessThan($firstCallMs, $cachedCallMs);

        $this->assertLessThan(50, $cachedCallMs, 'Cached call should complete in under 50ms');
    }
}
