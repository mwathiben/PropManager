<?php

declare(strict_types=1);

namespace Tests\Feature\Commands;

use App\Models\Lease;
use App\Models\Property;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase-12 RETAIN-4: soft-deleted rows past the grace window are
 * force-deleted. --confirm is required; --dry-run is the default.
 */
class SoftDeletedPurgeTest extends TestCase
{
    use RefreshDatabase;

    public function test_dry_run_reports_candidates_without_force_delete(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);

        $stale = Property::factory()->create(['landlord_id' => $landlord->id]);
        $stale->delete();
        Carbon::setTestNow(now()->subDays(60));
        $stale->refresh();
        $stale->forceFill(['deleted_at' => now()])->save();
        Carbon::setTestNow();

        $fresh = Property::factory()->create(['landlord_id' => $landlord->id]);
        $fresh->delete();

        $this->artisan('soft-deleted:purge', ['--grace-days' => 30])
            ->assertExitCode(0)
            ->expectsOutputToContain('DRY RUN');

        $this->assertSame(2, Property::withTrashed()->count());
    }

    public function test_confirm_force_deletes_stale_rows(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);

        $stale = Property::factory()->create(['landlord_id' => $landlord->id]);
        $stale->delete();
        $stale->forceFill(['deleted_at' => now()->subDays(60)])->save();

        $fresh = Property::factory()->create(['landlord_id' => $landlord->id]);
        $fresh->delete();

        $this->artisan('soft-deleted:purge', [
            '--grace-days' => 30,
            '--confirm' => true,
        ])->assertExitCode(0);

        $this->assertSame(1, Property::withTrashed()->count());
        $this->assertNotNull(Property::withTrashed()->find($fresh->id));
        $this->assertNull(Property::withTrashed()->find($stale->id));
    }

    public function test_negative_grace_days_is_rejected(): void
    {
        $this->artisan('soft-deleted:purge', [
            '--grace-days' => -1,
            '--confirm' => true,
        ])->assertExitCode(2);
    }

    public function test_sweeps_multiple_model_types_in_one_run(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord']);

        $staleProp = Property::factory()->create(['landlord_id' => $landlord->id]);
        $staleProp->delete();
        $staleProp->forceFill(['deleted_at' => now()->subDays(60)])->save();

        $tenant = User::factory()->create(['role' => 'tenant', 'landlord_id' => $landlord->id]);
        $staleLease = Lease::factory()->create([
            'landlord_id' => $landlord->id,
            'tenant_id' => $tenant->id,
        ]);
        $staleLease->delete();
        $staleLease->forceFill(['deleted_at' => now()->subDays(60)])->save();

        $this->artisan('soft-deleted:purge', [
            '--grace-days' => 30,
            '--confirm' => true,
        ])->assertExitCode(0);

        $this->assertNull(Property::withTrashed()->find($staleProp->id));
        $this->assertNull(Lease::withTrashed()->find($staleLease->id));
    }
}
