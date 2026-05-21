<?php

declare(strict_types=1);

namespace Tests\Feature\LegalHold;

use App\Models\Invoice;
use App\Models\LegalHold;
use App\Models\User;
use App\Services\Legal\BulkHoldService;
use App\Support\LegalHoldRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * Phase-65 BULK-HOLD watchdog: BulkHoldService transactional semantics
 * + endpoint cross-tenant rejection + tenant-litigation preset.
 */
class Phase65BulkHoldTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    private User $landlord;

    private User $otherLandlord;

    private BulkHoldService $service;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();

        $a = $this->createLandlordWithFullSetup();
        $this->landlord = $a['landlord'];

        $b = $this->createLandlordWithFullSetup();
        $this->otherLandlord = $b['landlord'];

        $this->service = app(BulkHoldService::class);
    }

    public function test_bulk_hold_mints_rows_inside_single_transaction(): void
    {
        $invoices = Invoice::factory()->count(20)->create(['landlord_id' => $this->landlord->id]);
        $ids = $invoices->pluck('id')->map(fn ($id) => (int) $id)->all();

        $holds = $this->service->holdAll(Invoice::class, $ids, $this->landlord, 'litigation reason long enough');

        $this->assertCount(20, $holds);
        $this->assertSame(20, LegalHold::query()->where('holdable_type', Invoice::class)->count());
    }

    public function test_bulk_hold_busts_cache_once(): void
    {
        $invoices = Invoice::factory()->count(5)->create(['landlord_id' => $this->landlord->id]);
        $ids = $invoices->pluck('id')->map(fn ($id) => (int) $id)->all();

        LegalHoldRegistry::heldIdsFor(Invoice::class);
        $cacheKey = 'legal_hold:ids:App_Models_Invoice';
        $this->assertNotNull(Cache::get($cacheKey));

        $this->service->holdAll(Invoice::class, $ids, $this->landlord, 'litigation reason long enough');

        $this->assertNull(Cache::get($cacheKey), 'Cache must be busted after bulk hold');
    }

    public function test_bulk_hold_rejects_cross_landlord_ids(): void
    {
        $mine = Invoice::factory()->create(['landlord_id' => $this->landlord->id]);
        $theirs = Invoice::factory()->create(['landlord_id' => $this->otherLandlord->id]);

        $this->expectException(InvalidArgumentException::class);

        $this->service->holdAll(
            Invoice::class,
            [(int) $mine->id, (int) $theirs->id],
            $this->landlord,
            'attempt to mix landlords',
        );
    }

    public function test_bulk_hold_rejects_disallowed_subject_class(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->service->holdAll(User::class, [$this->landlord->id], $this->landlord, 'reason long enough');
    }

    public function test_bulk_hold_rejects_empty_array(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->service->holdAll(Invoice::class, [], $this->landlord, 'reason long enough');
    }

    public function test_bulk_hold_caps_at_configured_max(): void
    {
        config(['legal_hold.bulk_max' => 5]);

        $invoices = Invoice::factory()->count(6)->create(['landlord_id' => $this->landlord->id]);

        $this->expectException(InvalidArgumentException::class);

        $this->service->holdAll(
            Invoice::class,
            $invoices->pluck('id')->map(fn ($id) => (int) $id)->all(),
            $this->landlord,
            'reason long enough',
        );
    }

    public function test_bulk_release_flips_released_at_in_one_update(): void
    {
        $invoices = Invoice::factory()->count(5)->create(['landlord_id' => $this->landlord->id]);
        $ids = $invoices->pluck('id')->map(fn ($id) => (int) $id)->all();

        $this->service->holdAll(Invoice::class, $ids, $this->landlord, 'reason long enough');

        $count = $this->service->releaseAll(Invoice::class, $ids, $this->landlord);

        $this->assertSame(5, $count);
        $this->assertSame(0, LegalHold::query()
            ->where('holdable_type', Invoice::class)
            ->whereNull('released_at')
            ->count());
    }

    public function test_bulk_endpoint_rejects_cross_landlord_via_policy(): void
    {
        $theirs = Invoice::factory()->create(['landlord_id' => $this->otherLandlord->id]);

        $response = $this->actingAs($this->landlord)->post(route('legal-holds.bulk.store'), [
            'subject_type' => Invoice::class,
            'subject_ids' => [$theirs->id],
            'reason' => 'cross-landlord attempt should fail',
        ]);

        $response->assertForbidden();
        $this->assertSame(0, LegalHold::query()->count());
    }

    public function test_bulk_endpoint_succeeds_with_owned_ids(): void
    {
        $invoices = Invoice::factory()->count(3)->create(['landlord_id' => $this->landlord->id]);

        $response = $this->actingAs($this->landlord)->post(route('legal-holds.bulk.store'), [
            'subject_type' => Invoice::class,
            'subject_ids' => $invoices->pluck('id')->toArray(),
            'reason' => 'court order preservation directive',
        ]);

        // Phase-72 COMMAND-CENTER: bulk store now lands on the flat list.
        $response->assertRedirect(route('legal-holds.list'));
        $this->assertSame(3, LegalHold::query()->count());
    }

    public function test_tenant_preset_holds_records_across_subjects_atomically(): void
    {
        $setup = $this->createTenantWithActiveLease(
            $this->landlord,
            \App\Models\Unit::query()->withoutGlobalScopes()->where('landlord_id', $this->landlord->id)->first(),
        );
        $tenant = $setup['tenant'];

        $invoice = Invoice::factory()->create([
            'landlord_id' => $this->landlord->id,
            'lease_id' => $setup['lease']->id,
        ]);

        $response = $this->actingAs($this->landlord)->post(route('tenants.legal-hold', $tenant), [
            'reason' => 'tenant litigation Smith v. Landlord — preserve all',
        ]);

        $response->assertRedirect();
        $this->assertGreaterThanOrEqual(1, LegalHold::query()->where('holdable_type', Invoice::class)->count());
    }
}
