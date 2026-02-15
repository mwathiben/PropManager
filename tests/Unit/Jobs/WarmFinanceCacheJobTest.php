<?php

declare(strict_types=1);

namespace Tests\Unit\Jobs;

use App\Jobs\WarmFinanceCacheJob;
use App\Services\FinanceStatsService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WarmFinanceCacheJobTest extends TestCase
{
    #[Test]
    public function job_implements_should_queue(): void
    {
        $job = new WarmFinanceCacheJob(1);

        $this->assertInstanceOf(ShouldQueue::class, $job);
    }

    #[Test]
    public function job_implements_should_be_unique(): void
    {
        $job = new WarmFinanceCacheJob(1);

        $this->assertInstanceOf(ShouldBeUnique::class, $job);
    }

    #[Test]
    public function job_unique_id_includes_landlord(): void
    {
        $job = new WarmFinanceCacheJob(42);

        $this->assertSame('warm-finance-cache:42', $job->uniqueId());
    }

    #[Test]
    public function job_unique_for_is_ten_seconds(): void
    {
        $job = new WarmFinanceCacheJob(1);

        $this->assertSame(10, $job->uniqueFor);
    }

    #[Test]
    public function job_has_single_try(): void
    {
        $job = new WarmFinanceCacheJob(1);

        $this->assertSame(1, $job->tries);
    }

    #[Test]
    public function job_calls_all_five_stat_methods(): void
    {
        /** @var MockInterface&FinanceStatsService $stats */
        $stats = Mockery::mock(FinanceStatsService::class);

        $stats->shouldReceive('getHubStats')->once()->with(5)->andReturn([]);
        $stats->shouldReceive('getDepositStats')->once()->with(5)->andReturn([]);
        $stats->shouldReceive('getLateFeeStats')->once()->with(5)->andReturn([]);
        $stats->shouldReceive('getExpenseStats')->once()->with(5)->andReturn([]);
        $stats->shouldReceive('getMonthlyTrend')->once()->with(5)->andReturn([]);

        $job = new WarmFinanceCacheJob(5);
        $job->handle($stats);
    }
}
