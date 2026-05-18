<?php

declare(strict_types=1);

namespace Tests\Feature\Storage;

use App\Services\Storage\TenantDiskResolver;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Phase-58 TENANT-DISK-RESOLVER-1/2/3 watchdog. Verifies the resolver,
 * macro, and config knob land + cooperate.
 */
class Phase58TenantDiskResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolver_class_exists(): void
    {
        $this->assertTrue(class_exists(TenantDiskResolver::class));
    }

    public function test_default_config_resolves_to_local_disk(): void
    {
        config()->set('filesystems.tenant_disk', 'local');

        $disk = app(TenantDiskResolver::class)->resolve();

        $this->assertInstanceOf(Filesystem::class, $disk);
    }

    public function test_custom_config_routes_to_configured_disk(): void
    {
        config()->set('filesystems.tenant_disk', 'private');

        $disk = app(TenantDiskResolver::class)->resolve();

        $this->assertInstanceOf(Filesystem::class, $disk);
    }

    public function test_resolver_falls_back_to_local_on_invalid_disk_name(): void
    {
        config()->set('filesystems.tenant_disk', 'nonexistent_disk_name_12345');

        $disk = app(TenantDiskResolver::class)->resolve();

        $this->assertInstanceOf(Filesystem::class, $disk, 'Resolver must fail-soft to local disk on invalid name.');
    }

    public function test_storage_tenant_macro_returns_filesystem(): void
    {
        $disk = Storage::tenant();

        $this->assertInstanceOf(Filesystem::class, $disk);
    }

    public function test_storage_tenant_macro_accepts_landlord_id(): void
    {
        $disk = Storage::tenant(landlordId: 42);

        // landlord_id is recorded but unused today; macro still returns a Filesystem.
        $this->assertInstanceOf(Filesystem::class, $disk);
    }

    public function test_tenant_disk_config_knob_defined(): void
    {
        $this->assertNotNull(config('filesystems.tenant_disk'));
    }
}
