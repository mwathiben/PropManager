<?php

declare(strict_types=1);

namespace Tests\Feature\Leases;

use App\Console\Commands\LeaseAutoRenew;
use App\Console\Commands\LeasePauseAutoResume;
use App\Events\LeaseTerminationInitiated;
use App\Events\LeaseTransferRequested;
use App\Exceptions\ShortNoticeException;
use App\Models\LeasePause;
use App\Models\LeaseTermination;
use App\Models\LeaseTransfer;
use App\Services\Lease\LeasePauseService;
use App\Services\Lease\LeaseRenewalAutoService;
use App\Services\Lease\LeaseTerminationService;
use App\Services\Lease\LeaseTransferService;
use App\Services\Lease\NoticePeriodValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Phase-61 LEASE-LIFECYCLE CI surface watchdog. Cross-category
 * presence map for every Phase 61 closure.
 */
class Phase61LeaseLifecycleSurfaceTest extends TestCase
{
    use RefreshDatabase;

    // -- TERMINATION -----------------------------------------------------

    public function test_termination_service_event_and_model_exist(): void
    {
        $this->assertTrue(class_exists(LeaseTerminationService::class));
        $this->assertTrue(class_exists(LeaseTermination::class));
        $this->assertTrue(class_exists(LeaseTerminationInitiated::class));
        $this->assertTrue(Schema::hasTable('lease_terminations'));
    }

    public function test_termination_route_registered(): void
    {
        $this->assertNotNull(Route::getRoutes()->getByName('leases.terminate'));
    }

    // -- TRANSFER --------------------------------------------------------

    public function test_transfer_service_event_and_model_exist(): void
    {
        $this->assertTrue(class_exists(LeaseTransferService::class));
        $this->assertTrue(class_exists(LeaseTransfer::class));
        $this->assertTrue(class_exists(LeaseTransferRequested::class));
        $this->assertTrue(Schema::hasTable('lease_transfers'));
    }

    public function test_transfer_route_registered(): void
    {
        $this->assertNotNull(Route::getRoutes()->getByName('leases.transfer'));
    }

    // -- PAUSE -----------------------------------------------------------

    public function test_pause_service_command_and_model_exist(): void
    {
        $this->assertTrue(class_exists(LeasePauseService::class));
        $this->assertTrue(class_exists(LeasePause::class));
        $this->assertTrue(class_exists(LeasePauseAutoResume::class));
        $this->assertTrue(Schema::hasTable('lease_pauses'));
    }

    public function test_pause_route_and_cron_at_0600(): void
    {
        $this->assertNotNull(Route::getRoutes()->getByName('leases.pause'));

        $events = collect(Schedule::events());
        $entry = $events->first(fn ($e) => str_contains((string) $e->command, 'lease-pause:auto-resume'));
        $this->assertNotNull($entry);
        $this->assertSame('0 6 * * *', $entry->expression);
    }

    // -- RENEWAL-AUTO ----------------------------------------------------

    public function test_renewal_auto_service_and_command_exist(): void
    {
        $this->assertTrue(class_exists(LeaseRenewalAutoService::class));
        $this->assertTrue(class_exists(LeaseAutoRenew::class));
        $this->assertTrue(Schema::hasColumn('leases', 'auto_renew'));
        $this->assertTrue(Schema::hasColumn('leases', 'renewed_from_lease_id'));
    }

    public function test_renewal_auto_route_and_cron_at_0700(): void
    {
        $this->assertNotNull(Route::getRoutes()->getByName('leases.auto-renew'));

        $events = collect(Schedule::events());
        $entry = $events->first(fn ($e) => str_contains((string) $e->command, 'lease:auto-renew'));
        $this->assertNotNull($entry);
        $this->assertSame('0 7 * * *', $entry->expression);
    }

    // -- NOTICE-LIFECYCLE ------------------------------------------------

    public function test_notice_validator_and_exception_exist(): void
    {
        $this->assertTrue(class_exists(NoticePeriodValidator::class));
        $this->assertTrue(class_exists(ShortNoticeException::class));
        $this->assertIsArray(config('lease.notice_periods'));
        $this->assertSame(30, config('lease.notice_periods.termination'));
    }

    // -- DOCS ------------------------------------------------------------

    public function test_lease_runbook_exists_with_phase_61_section(): void
    {
        $path = base_path('docs/runbooks/lease.md');
        $this->assertFileExists($path);
        $body = (string) file_get_contents($path);
        $this->assertStringContainsString('Phase 61', $body);
        $this->assertStringContainsString('LEASE-LIFECYCLE', $body);
    }

    public function test_alert_thresholds_md_lists_phase_61_gauges(): void
    {
        $body = (string) file_get_contents(base_path('docs/runbooks/alert-thresholds.md'));
        $this->assertStringContainsString('lease_pause_resumed_count', $body);
        $this->assertStringContainsString('lease_auto_renewed_count', $body);
        $this->assertStringContainsString('lease_termination_pending_count', $body);
    }
}
