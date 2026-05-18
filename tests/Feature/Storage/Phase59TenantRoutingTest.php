<?php

declare(strict_types=1);

namespace Tests\Feature\Storage;

use App\Services\Storage\PrefixedDisk;
use App\Services\Storage\TenantDiskResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Phase-59 TENANT-ROUTING-1/2/3: PrefixedDisk decorator + opt-in
 * template via filesystems.tenant_disk_prefix_template. Default null
 * preserves Phase-58 behaviour bit-for-bit; setting the template
 * shards future writes under a per-landlord prefix.
 */
class Phase59TenantRoutingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('filesystems.tenant_disk_prefix_template', null);
    }

    public function test_default_template_returns_underlying_disk_without_decorator(): void
    {
        Storage::fake('local');
        $disk = app(TenantDiskResolver::class)->resolve(42);

        $this->assertNotInstanceOf(PrefixedDisk::class, $disk);
    }

    public function test_template_with_landlord_id_returns_prefixed_disk(): void
    {
        Storage::fake('local');
        config()->set('filesystems.tenant_disk_prefix_template', '{landlord_id}/');

        $disk = app(TenantDiskResolver::class)->resolve(42);

        $this->assertInstanceOf(PrefixedDisk::class, $disk);
    }

    public function test_template_without_landlord_id_does_not_apply_prefix(): void
    {
        Storage::fake('local');
        config()->set('filesystems.tenant_disk_prefix_template', '{landlord_id}/');

        $disk = app(TenantDiskResolver::class)->resolve(null);

        $this->assertNotInstanceOf(PrefixedDisk::class, $disk);
    }

    public function test_prefixed_disk_shards_writes_under_landlord_prefix(): void
    {
        Storage::fake('local');
        config()->set('filesystems.tenant_disk_prefix_template', '{landlord_id}/');

        $disk = app(TenantDiskResolver::class)->resolve(42);
        $disk->put('a.txt', 'sample');

        // Caller passed 'a.txt'; the physical path on disk is '42/a.txt'
        $this->assertTrue(Storage::disk('local')->exists('42/a.txt'));
        $this->assertFalse(Storage::disk('local')->exists('a.txt'));
    }

    public function test_prefixed_disk_isolates_landlords_from_each_other(): void
    {
        Storage::fake('local');
        config()->set('filesystems.tenant_disk_prefix_template', '{landlord_id}/');

        $diskA = app(TenantDiskResolver::class)->resolve(1);
        $diskB = app(TenantDiskResolver::class)->resolve(2);

        $diskA->put('shared-name.txt', 'belongs-to-1');
        $diskB->put('shared-name.txt', 'belongs-to-2');

        $this->assertSame('belongs-to-1', $diskA->get('shared-name.txt'));
        $this->assertSame('belongs-to-2', $diskB->get('shared-name.txt'));
        $this->assertTrue(Storage::disk('local')->exists('1/shared-name.txt'));
        $this->assertTrue(Storage::disk('local')->exists('2/shared-name.txt'));
    }

    public function test_prefixed_disk_round_trip_through_decorator(): void
    {
        Storage::fake('local');
        config()->set('filesystems.tenant_disk_prefix_template', 'tenants/{landlord_id}/');

        $disk = app(TenantDiskResolver::class)->resolve(99);
        $disk->put('docs/file.txt', 'payload');

        $this->assertTrue($disk->exists('docs/file.txt'));
        $this->assertSame('payload', $disk->get('docs/file.txt'));
        $this->assertTrue(Storage::disk('local')->exists('tenants/99/docs/file.txt'));

        $disk->delete('docs/file.txt');

        $this->assertFalse($disk->exists('docs/file.txt'));
        $this->assertFalse(Storage::disk('local')->exists('tenants/99/docs/file.txt'));
    }

    public function test_prefixed_disk_files_listing_returns_unprefixed_paths(): void
    {
        Storage::fake('local');
        config()->set('filesystems.tenant_disk_prefix_template', '{landlord_id}/');

        $disk = app(TenantDiskResolver::class)->resolve(7);
        $disk->put('reports/a.txt', '1');
        $disk->put('reports/b.txt', '2');

        $files = $disk->files('reports');

        sort($files);
        $this->assertSame(['reports/a.txt', 'reports/b.txt'], $files);
    }
}
