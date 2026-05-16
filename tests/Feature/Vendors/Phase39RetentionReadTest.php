<?php

declare(strict_types=1);

namespace Tests\Feature\Vendors;

use App\Models\RehydratedProductEvent;
use App\Models\User;
use App\Services\Archive\ArchiveManifestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Phase-39 RETENTION-READ-1/2/3: archive:rehydrate command +
 * ArchiveManifestService + /ops/archive/search super_admin endpoint.
 */
class Phase39RetentionReadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('archive');
    }

    private function seedArchive(string $landlordId, string $monthYYYYMM, array $events): string
    {
        $jsonl = '';
        foreach ($events as $i => $ev) {
            $jsonl .= json_encode($ev + [
                'id' => $ev['id'] ?? ($i + 1),
                'landlord_id' => $landlordId,
            ])."\n";
        }
        $path = sprintf('product-events/%s/%s/events.jsonl.gz', $landlordId, $monthYYYYMM);
        Storage::disk('archive')->put($path, gzencode($jsonl, 9));

        return $path;
    }

    public function test_archive_rehydrate_inserts_rows_from_gzipped_jsonl(): void
    {
        $this->seedArchive('42', '2026-04', [
            ['user_id' => 1, 'event_name' => 'page_view', 'properties' => ['path' => '/']],
            ['user_id' => 2, 'event_name' => 'checkout_started', 'properties' => []],
            ['user_id' => 1, 'event_name' => 'checkout_completed', 'properties' => ['cents' => 1500]],
        ]);

        $this->artisan('archive:rehydrate', ['--landlord' => '42', '--month' => '2026-04'])
            ->assertExitCode(0);

        $count = RehydratedProductEvent::query()->withoutGlobalScopes()
            ->where('landlord_id', '42')->count();
        $this->assertSame(3, $count);

        $checkout = RehydratedProductEvent::query()->withoutGlobalScopes()
            ->where('event_name', 'checkout_completed')->first();
        $this->assertNotNull($checkout);
        $this->assertSame(['cents' => 1500], $checkout->properties);
    }

    public function test_archive_rehydrate_clear_first_purges_old_rows_for_same_path(): void
    {
        $path = $this->seedArchive('42', '2026-04', [
            ['user_id' => 1, 'event_name' => 'a', 'properties' => []],
        ]);

        RehydratedProductEvent::query()->insert([
            'original_id' => 999,
            'user_id' => 99,
            'landlord_id' => 42,
            'event_name' => 'stale_event',
            'properties' => null,
            'rehydrated_at' => now()->subDay(),
            'source_path' => $path,
        ]);

        $this->artisan('archive:rehydrate', [
            '--landlord' => '42',
            '--month' => '2026-04',
            '--clear-first' => true,
        ])->assertExitCode(0);

        $this->assertSame(0, RehydratedProductEvent::query()->withoutGlobalScopes()
            ->where('event_name', 'stale_event')->count());
        $this->assertSame(1, RehydratedProductEvent::query()->withoutGlobalScopes()
            ->where('source_path', $path)->count());
    }

    public function test_archive_rehydrate_fails_when_month_missing(): void
    {
        $this->artisan('archive:rehydrate', ['--landlord' => '42'])
            ->assertExitCode(1);
    }

    public function test_archive_rehydrate_fails_when_file_missing(): void
    {
        $this->artisan('archive:rehydrate', ['--landlord' => '42', '--month' => '2099-12'])
            ->assertExitCode(1);
    }

    public function test_manifest_lists_available_months_for_landlord(): void
    {
        $this->seedArchive('42', '2026-03', [['event_name' => 'x']]);
        $this->seedArchive('42', '2026-04', [['event_name' => 'x']]);
        $this->seedArchive('42', '2026-05', [['event_name' => 'x']]);

        $months = app(ArchiveManifestService::class)->availableMonthsForLandlord('42');

        $this->assertCount(3, $months);
        $this->assertSame('2026-05', $months[0]['month']);
        $this->assertSame('2026-03', $months[2]['month']);
    }

    public function test_manifest_lists_available_landlords_for_month(): void
    {
        $this->seedArchive('42', '2026-04', [['event_name' => 'x']]);
        $this->seedArchive('99', '2026-04', [['event_name' => 'x']]);
        $this->seedArchive('42', '2026-05', [['event_name' => 'x']]);

        $landlords = app(ArchiveManifestService::class)->availableLandlordsForMonth('2026-04');
        $ids = array_column($landlords, 'landlord_id');
        sort($ids);
        $this->assertSame(['42', '99'], $ids);
    }

    public function test_ops_archive_search_blocks_landlord(): void
    {
        $landlord = User::factory()->create(['role' => 'landlord', 'email_verified_at' => now()]);
        $this->actingAs($landlord)->get(route('ops.archive.show'))->assertForbidden();
    }

    public function test_ops_archive_search_renders_for_super_admin(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'email_verified_at' => now()]);

        $response = $this->actingAs($admin)->get(route('ops.archive.show'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Ops/ArchiveSearch')
            ->has('summary')
            ->has('events'));
    }

    public function test_ops_archive_rehydrate_validates_input(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'email_verified_at' => now()]);

        $response = $this->actingAs($admin)->post(route('ops.archive.rehydrate'), [
            'landlord' => '42',
            'month' => 'not-a-month',
        ]);

        $response->assertSessionHasErrors(['month']);
    }
}
