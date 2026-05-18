<?php

declare(strict_types=1);

namespace Tests\Feature\Storage;

use App\Services\Storage\TenantDiskResolver;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Mockery;
use RuntimeException;
use Tests\TestCase;

/**
 * Phase-59 SIGNED-URLS-1/2/3: TenantDiskResolver::temporaryUrl with
 * local-driver fallback, and the FileLocalStreamController re-stream
 * route the fallback points at.
 *
 * Storage::fake() can't be used to exercise the local-driver
 * fallback path because Laravel's fake disk returns a fake temporary
 * URL instead of throwing. We mock the underlying disk via a partial
 * resolver to assert the contract directly.
 */
class Phase59SignedUrlsTest extends TestCase
{
    use RefreshDatabase;

    public function test_temporary_url_falls_back_to_signed_route_when_disk_throws(): void
    {
        $disk = Mockery::mock(Filesystem::class);
        $disk->shouldReceive('temporaryUrl')->andThrow(new RuntimeException('not supported'));

        $resolver = Mockery::mock(TenantDiskResolver::class)->makePartial();
        $resolver->shouldReceive('resolve')->andReturn($disk);

        $url = $resolver->temporaryUrl('phase59/a.txt');

        $this->assertStringContainsString('/files/local-stream', $url);
        $this->assertStringContainsString('path=', $url);
        $this->assertStringContainsString('signature=', $url);
    }

    public function test_temporary_url_returns_native_url_when_disk_supports_it(): void
    {
        $disk = Mockery::mock(Filesystem::class);
        $disk->shouldReceive('temporaryUrl')->andReturn('https://s3.example.com/bucket/p.pdf?X-Amz-Signature=abc');

        $resolver = Mockery::mock(TenantDiskResolver::class)->makePartial();
        $resolver->shouldReceive('resolve')->andReturn($disk);

        $url = $resolver->temporaryUrl('p.pdf');

        $this->assertSame('https://s3.example.com/bucket/p.pdf?X-Amz-Signature=abc', $url);
    }

    public function test_fallback_signed_url_includes_filename_and_disposition_when_provided(): void
    {
        $disk = Mockery::mock(Filesystem::class);
        $disk->shouldReceive('temporaryUrl')->andThrow(new RuntimeException('local'));

        $resolver = Mockery::mock(TenantDiskResolver::class)->makePartial();
        $resolver->shouldReceive('resolve')->andReturn($disk);

        $url = $resolver->temporaryUrl('phase59/b.pdf', null, 5, 'invoice.pdf', 'inline');

        $this->assertStringContainsString('filename=invoice.pdf', $url);
        $this->assertStringContainsString('disposition=inline', $url);
    }

    public function test_local_stream_route_serves_existing_file_via_signed_url(): void
    {
        Storage::fake('local');
        Storage::tenant()->put('phase59/c.txt', 'content-body');

        $url = URL::temporarySignedRoute(
            'files.local-stream',
            now()->addMinutes(5),
            ['path' => 'phase59/c.txt', 'filename' => 'c.txt'],
        );

        $response = $this->get($url);

        $response->assertOk();
    }

    public function test_local_stream_route_returns_404_for_missing_file(): void
    {
        Storage::fake('local');

        $url = URL::temporarySignedRoute(
            'files.local-stream',
            now()->addMinutes(5),
            ['path' => 'phase59/does-not-exist.txt'],
        );

        $response = $this->get($url);

        $response->assertNotFound();
    }

    public function test_local_stream_route_rejects_tampered_signature(): void
    {
        Storage::fake('local');
        Storage::tenant()->put('phase59/legit.txt', 'legit');
        Storage::tenant()->put('phase59/target.txt', 'target');

        $signed = URL::temporarySignedRoute(
            'files.local-stream',
            now()->addMinutes(5),
            ['path' => 'phase59/legit.txt'],
        );
        $tampered = str_replace('phase59%2Flegit.txt', 'phase59%2Ftarget.txt', $signed);

        $response = $this->get($tampered);

        $response->assertForbidden();
    }

    public function test_local_stream_route_400s_on_empty_path(): void
    {
        $url = URL::temporarySignedRoute(
            'files.local-stream',
            now()->addMinutes(5),
            ['path' => ''],
        );

        $response = $this->get($url);

        $response->assertStatus(400);
    }

    public function test_resolver_method_exists_with_documented_signature(): void
    {
        $this->assertTrue(method_exists(TenantDiskResolver::class, 'temporaryUrl'));

        $reflection = new \ReflectionMethod(TenantDiskResolver::class, 'temporaryUrl');
        $params = $reflection->getParameters();

        $this->assertSame('path', $params[0]->getName());
        $this->assertSame('landlordId', $params[1]->getName());
        $this->assertSame('expiresMinutes', $params[2]->getName());
        $this->assertSame('filename', $params[3]->getName());
        $this->assertSame('disposition', $params[4]->getName());
    }
}
