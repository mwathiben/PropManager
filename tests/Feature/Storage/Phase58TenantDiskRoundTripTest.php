<?php

declare(strict_types=1);

namespace Tests\Feature\Storage;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Phase-58 INTEGRATION-TESTS-1 watchdog. Verifies Storage::tenant()
 * supports the full put/get/exists/delete/path lifecycle identically
 * to the pre-Phase-58 Storage::disk('local') usage.
 *
 * Uses Storage::fake('local') so the round-trip touches no real disk.
 */
class Phase58TenantDiskRoundTripTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Ensure the tenant disk resolves to a faked filesystem.
        Storage::fake(config('filesystems.tenant_disk', 'local'));
    }

    public function test_put_and_get_round_trip(): void
    {
        $path = 'phase58/test.txt';
        $payload = 'Phase 58 SHARED-DISK-MIGRATION sample payload';

        Storage::tenant()->put($path, $payload);

        $this->assertTrue(Storage::tenant()->exists($path));
        $this->assertSame($payload, Storage::tenant()->get($path));
    }

    public function test_delete_removes_file(): void
    {
        $path = 'phase58/to-delete.txt';
        Storage::tenant()->put($path, 'will be deleted');

        Storage::tenant()->delete($path);

        $this->assertFalse(Storage::tenant()->exists($path));
    }

    public function test_make_directory_and_path_return_local_disk_paths(): void
    {
        $dir = 'phase58/nested/deep';
        Storage::tenant()->makeDirectory($dir);
        Storage::tenant()->put("{$dir}/file.txt", 'nested content');

        // Path is local-driver-only; we know we're on local because fake() forces it.
        $absolutePath = Storage::tenant()->path("{$dir}/file.txt");

        $this->assertIsString($absolutePath);
        $this->assertNotEmpty($absolutePath);
        $this->assertStringEndsWith('file.txt', $absolutePath);
    }

    public function test_config_knob_routes_to_alternate_disk(): void
    {
        config()->set('filesystems.tenant_disk', 'private');
        Storage::fake('private');

        Storage::tenant()->put('phase58/private.txt', 'on private');

        $this->assertTrue(Storage::tenant()->exists('phase58/private.txt'));
    }

    public function test_resolver_landlord_id_parameter_does_not_break_chain(): void
    {
        $path = 'phase58/landlord-tagged.txt';
        Storage::tenant(landlordId: 42)->put($path, 'tagged payload');

        $this->assertTrue(Storage::tenant(landlordId: 42)->exists($path));
    }
}
