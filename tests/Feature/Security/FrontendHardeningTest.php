<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Models\SecurityLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase-15 Phase 1 coverage: FRONT-1/2/3 + the FRONT-5/6 changes
 * that came along for free (same SecurityHeaders middleware).
 *
 * FRONT-1 (DOMPurify on Help/Show.vue) is a frontend-only change;
 * the assertion lives in the JS bundle and gets exercised by the
 * Dusk Browser tests. PHP tests assert the surrounding wiring.
 */
class FrontendHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_csp_header_no_longer_carries_unsafe_inline_on_style_src(): void
    {
        $response = $this->get('/');

        $csp = $response->headers->get('Content-Security-Policy') ?? '';
        $this->assertStringNotContainsString("style-src 'self' 'unsafe-inline'", $csp);
        $this->assertStringContainsString("style-src 'self' 'nonce-", $csp);
        // style-src-attr is the explicit allow for Vue :style bindings.
        $this->assertStringContainsString("style-src-attr 'unsafe-inline'", $csp);
    }

    public function test_csp_header_no_longer_allowlists_paystack_script(): void
    {
        $response = $this->get('/');

        $csp = $response->headers->get('Content-Security-Policy') ?? '';
        $this->assertStringNotContainsString('https://js.paystack.co', $csp);
    }

    public function test_csp_header_carries_report_uri(): void
    {
        $response = $this->get('/');

        $csp = $response->headers->get('Content-Security-Policy') ?? '';
        $this->assertStringContainsString('report-uri /api/v1/csp-reports', $csp);
    }

    public function test_csp_img_src_no_longer_wide_open(): void
    {
        $response = $this->get('/');

        $csp = $response->headers->get('Content-Security-Policy') ?? '';
        // The wide-open standalone `https:` token (matches any HTTPS
        // origin) is gone. `https://imgs.paystack.co` (a specific
        // origin) remains. Regex anchors on the trailing space/
        // semicolon so the assertion isn't fooled by https://-style
        // explicit origins.
        $this->assertDoesNotMatchRegularExpression('/img-src[^;]*\bhttps:(?!\/\/)/', $csp);
        $this->assertStringContainsString("img-src 'self' data: blob:", $csp);
    }

    public function test_csp_allowlists_building_map_and_webfont_origins(): void
    {
        // The BuildingMap (Leaflet) loads OpenStreetMap raster tiles +
        // cdnjs marker icons as <img>, geocodes via Nominatim (fetch), and
        // the service worker fetches the Figtree webfont CSS. Each needs an
        // explicit CSP origin or the Architect's Location tab / app font
        // silently break. Regression guard for those exact origins.
        $csp = $this->get('/')->headers->get('Content-Security-Policy') ?? '';

        $directives = explode('; ', $csp);
        $imgSrc = array_values(array_filter($directives, fn ($d) => str_starts_with($d, 'img-src ')))[0] ?? '';
        $this->assertStringContainsString('https://*.tile.openstreetmap.org', $imgSrc);
        $this->assertStringContainsString('https://cdnjs.cloudflare.com', $imgSrc);

        $connectSrc = array_values(array_filter($directives, fn ($d) => str_starts_with($d, 'connect-src ')))[0] ?? '';
        $this->assertStringContainsString('https://nominatim.openstreetmap.org', $connectSrc);
        $this->assertStringContainsString('https://fonts.bunny.net', $connectSrc);
        // The PWA service worker fetch()es tiles + marker icons to cache them,
        // and a fetch() is governed by connect-src — so these must be here too,
        // not only in img-src, or the SW blocks the map.
        $this->assertStringContainsString('https://*.tile.openstreetmap.org', $connectSrc);
        $this->assertStringContainsString('https://cdnjs.cloudflare.com', $connectSrc);
    }

    public function test_csp_report_endpoint_records_security_log(): void
    {
        $payload = [
            'csp-report' => [
                'document-uri' => 'https://propmanager.test/dashboard',
                'violated-directive' => 'script-src',
                'blocked-uri' => 'https://attacker.example/evil.js',
                'effective-directive' => 'script-src',
                'source-file' => 'https://propmanager.test/build/app.js',
                'line-number' => 42,
                'status-code' => 200,
            ],
        ];

        $response = $this->postJson('/api/v1/csp-reports', $payload);

        $response->assertStatus(204);
        $log = SecurityLog::where('event_type', 'csp_violation')->first();
        $this->assertNotNull($log);
        $this->assertSame('script-src', $log->metadata['violated_directive']);
        $this->assertSame('https://attacker.example/evil.js', $log->metadata['blocked_uri']);
    }

    public function test_csp_report_endpoint_handles_missing_payload(): void
    {
        $response = $this->postJson('/api/v1/csp-reports', []);

        // Empty payload must not crash — silently 204.
        $response->assertStatus(204);
    }

    public function test_csp_report_endpoint_records_auth_user_when_present(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/v1/csp-reports', [
            'csp-report' => [
                'violated-directive' => 'style-src',
                'blocked-uri' => 'inline',
            ],
        ]);

        $log = SecurityLog::where('event_type', 'csp_violation')->first();
        $this->assertSame($user->id, $log->user_id);
    }
}
