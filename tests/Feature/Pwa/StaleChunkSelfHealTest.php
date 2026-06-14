<?php

declare(strict_types=1);

namespace Tests\Feature\Pwa;

use Tests\TestCase;

/**
 * Regression guard: lazy page/route chunks are hashed dynamic imports, so after
 * a deploy (or when the PWA service worker serves a stale precache) an old chunk
 * can 404 and leave the user on a silently-broken page. app.js must catch Vite's
 * `vite:preloadError` and reload to fetch the fresh manifest — with a cooldown
 * guard so a genuine outage surfaces the error instead of looping the tab.
 */
class StaleChunkSelfHealTest extends TestCase
{
    private function appJs(): string
    {
        return (string) file_get_contents(resource_path('js/app.js'));
    }

    public function test_app_reloads_when_a_lazy_chunk_fails_to_load(): void
    {
        $appJs = $this->appJs();

        $this->assertStringContainsString(
            "addEventListener('vite:preloadError'",
            $appJs,
            'app.js must listen for vite:preloadError so a failed lazy-chunk import self-heals.',
        );
        $this->assertStringContainsString(
            'window.location.reload()',
            $appJs,
            'app.js must reload on a failed lazy-chunk import to pull the fresh manifest.',
        );
    }

    public function test_chunk_reload_is_guarded_against_a_loop(): void
    {
        $appJs = $this->appJs();

        $this->assertMatchesRegularExpression(
            '/CHUNK_RELOAD_COOLDOWN_MS|chunk-reload/',
            $appJs,
            'the chunk-reload must be rate-guarded so a genuine outage cannot loop the tab.',
        );
    }
}
