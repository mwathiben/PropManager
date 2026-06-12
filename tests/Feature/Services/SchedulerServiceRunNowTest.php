<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Models\NotificationSchedule;
use App\Services\SchedulerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * Robustness regression for the manual "Run now" path. Unlike the batch
 * processSchedules() loop, runNow() previously called processSchedule()
 * with NO error handling, so a single malformed lease/tenant row (which
 * dereferences to an \Error in processArrearsNotices /
 * processLeaseExpiryReminders) propagated straight out and turned the
 * user-facing button into a 500. runNow() must now mirror the batch
 * path: log the failure and return 0 instead of throwing.
 */
class SchedulerServiceRunNowTest extends TestCase
{
    use CreatesTestData;
    use RefreshDatabase;

    public function test_run_now_returns_zero_and_logs_when_processing_throws_an_exception(): void
    {
        ['landlord' => $landlord] = $this->createLandlordWithFullSetup();
        $schedule = NotificationSchedule::factory()->rentReminder()->forLandlord($landlord)->create();

        Log::spy();

        $service = $this->partialMockSchedulerThrowing(new \RuntimeException('boom'));

        $count = $service->runNow($schedule);

        $this->assertSame(0, $count);
        Log::shouldHaveReceived('error')
            ->once()
            ->withArgs(fn (string $message, array $context): bool => $context['schedule_id'] === $schedule->id
                && $context['error'] === 'boom');
    }

    public function test_run_now_returns_zero_when_processing_throws_an_error(): void
    {
        ['landlord' => $landlord] = $this->createLandlordWithFullSetup();
        $schedule = NotificationSchedule::factory()->arrearsNotice()->forLandlord($landlord)->create();

        Log::spy();

        // PHP 8 dereferencing a null relation (the malformed-row case) raises
        // \Error, not \Exception — runNow must catch \Throwable to survive it.
        $service = $this->partialMockSchedulerThrowing(new \Error('null deref'));

        $this->assertSame(0, $service->runNow($schedule));
    }

    public function test_run_now_does_not_mark_schedule_as_run_when_processing_throws(): void
    {
        ['landlord' => $landlord] = $this->createLandlordWithFullSetup();
        $schedule = NotificationSchedule::factory()->rentReminder()->forLandlord($landlord)->create(['last_run_at' => null]);

        Log::spy();

        $this->partialMockSchedulerThrowing(new \RuntimeException('boom'))->runNow($schedule);

        $this->assertNull($schedule->fresh()->last_run_at);
    }

    public function test_run_now_marks_as_run_and_returns_count_on_success(): void
    {
        ['landlord' => $landlord] = $this->createLandlordWithFullSetup();
        $schedule = NotificationSchedule::factory()->rentReminder()->forLandlord($landlord)->create(['last_run_at' => null]);

        $service = Mockery::mock(SchedulerService::class)->makePartial();
        $service->shouldReceive('processSchedule')->once()->andReturn(3);

        $count = $service->runNow($schedule);

        $this->assertSame(3, $count);
        $this->assertNotNull($schedule->fresh()->last_run_at);
    }

    private function partialMockSchedulerThrowing(\Throwable $exception): SchedulerService
    {
        $service = Mockery::mock(SchedulerService::class)->makePartial();
        $service->shouldReceive('processSchedule')->once()->andThrow($exception);

        return $service;
    }
}
