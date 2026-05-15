<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Phase-25 API-VERSION-3 watchdog: the api:deprecations:audit command
 * keeps docs/api/deprecations.md honest with the route table.
 */
class Phase25DeprecationsAuditTest extends TestCase
{
    public function test_audit_passes_when_no_routes_are_deprecated(): void
    {
        // The repo currently has zero deprecated routes (Phase 25 ships
        // the contract). The audit must exit cleanly.
        $exit = Artisan::call('api:deprecations:audit');
        $this->assertSame(0, $exit, 'API-VERSION-3: audit must succeed on a clean route table.');
    }

    public function test_audit_fails_when_a_deprecated_route_is_undocumented(): void
    {
        Route::get('test/version-3-undocumented', fn () => 'ok')
            ->middleware('deprecated:2099-01-01');

        $exit = Artisan::call('api:deprecations:audit');
        $output = Artisan::output();

        $this->assertSame(1, $exit, 'API-VERSION-3: audit must FAIL when a route carries the deprecated middleware but is absent from deprecations.md.');
        $this->assertStringContainsString('undocumented', $output);
        $this->assertStringContainsString('test/version-3-undocumented', $output);
    }

    public function test_fix_flag_appends_skeleton_entry(): void
    {
        Route::get('test/version-3-skeleton', fn () => 'ok')
            ->middleware('deprecated:2099-12-31');

        $path = base_path('docs/api/deprecations.md');
        $original = (string) file_get_contents($path);

        try {
            $exit = Artisan::call('api:deprecations:audit', ['--fix' => true]);
            $this->assertSame(0, $exit);

            $updated = (string) file_get_contents($path);
            $this->assertStringContainsString('GET /test/version-3-skeleton', $updated);
            $this->assertStringContainsString('**sunset_at**: 2099-12-31', $updated);
            $this->assertStringContainsString('_TODO', $updated, 'API-VERSION-3: skeletons must flag the prose fields the operator still needs to author.');
        } finally {
            file_put_contents($path, $original);
        }
    }

    public function test_audit_passes_when_deprecated_route_is_listed(): void
    {
        Route::get('test/version-3-listed', fn () => 'ok')
            ->middleware('deprecated:2099-06-01');

        $path = base_path('docs/api/deprecations.md');
        $original = (string) file_get_contents($path);

        try {
            file_put_contents($path, $original."\n\n### GET /test/version-3-listed\n\n- sunset_at: 2099-06-01\n");
            $exit = Artisan::call('api:deprecations:audit');
            $this->assertSame(0, $exit, 'API-VERSION-3: audit must succeed when the doc covers every deprecated route.');
        } finally {
            file_put_contents($path, $original);
        }
    }
}
