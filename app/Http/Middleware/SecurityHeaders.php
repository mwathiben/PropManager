<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    /**
     * Security headers to protect against common web vulnerabilities.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $config = config('security.headers');

        // Generate CSP nonce BEFORE processing request
        // This makes it available to @vite directive which auto-adds nonce attributes
        if ($config['csp_enabled']) {
            Vite::useCspNonce();
        }

        $response = $next($request);

        // Prevent clickjacking attacks
        if ($config['x_frame_options']) {
            $response->headers->set('X-Frame-Options', $config['x_frame_options']);
        }

        // Prevent MIME type sniffing
        if ($config['x_content_type_options']) {
            $response->headers->set('X-Content-Type-Options', $config['x_content_type_options']);
        }

        // XSS Protection (legacy but still useful for older browsers)
        if ($config['x_xss_protection']) {
            $response->headers->set('X-XSS-Protection', $config['x_xss_protection']);
        }

        // Referrer Policy
        if ($config['referrer_policy']) {
            $response->headers->set('Referrer-Policy', $config['referrer_policy']);
        }

        // Permissions Policy (formerly Feature-Policy)
        if ($config['permissions_policy']) {
            $response->headers->set('Permissions-Policy', $config['permissions_policy']);
        }

        // HSTS - HTTP Strict Transport Security (only for HTTPS)
        if ($config['hsts_enabled'] && $request->secure()) {
            $hsts = 'max-age='.$config['hsts_max_age'];

            if ($config['hsts_include_subdomains']) {
                $hsts .= '; includeSubDomains';
            }

            if ($config['hsts_preload']) {
                $hsts .= '; preload';
            }

            $response->headers->set('Strict-Transport-Security', $hsts);
        }

        // Content Security Policy with nonce support
        if ($config['csp_enabled']) {
            $csp = $this->buildCspHeader();
            $response->headers->set('Content-Security-Policy', $csp);
        }

        // Additional security headers
        $response->headers->set('X-Permitted-Cross-Domain-Policies', 'none');
        $response->headers->set('Cross-Origin-Embedder-Policy', 'unsafe-none');
        $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin');
        $response->headers->set('Cross-Origin-Resource-Policy', 'same-origin');

        return $response;
    }

    /**
     * Build CSP header with nonce and dynamic Vite dev server origin.
     */
    private function buildCspHeader(): string
    {
        $nonce = Vite::cspNonce();

        // Phase-15 FRONT-2: split style-src into element vs attribute.
        // style-src 'self' 'nonce-X' covers <style nonce> blocks
        // (tightened — drops legacy 'unsafe-inline' for those).
        // style-src-attr 'unsafe-inline' covers Vue's `:style=`
        // bindings (which emit inline style attributes at runtime).
        // CSP3 browsers honour the split; older browsers fall back to
        // style-src which still works.
        //
        // Phase-15 FRONT-3: js.paystack.co was preemptively allowlisted
        // but no <script src=> for it exists in the codebase today.
        // Removed from script-src. If a future feature loads Paystack
        // inline.js, add it back WITH an integrity SRI hash + document
        // the rotation procedure in docs/runbooks/.
        //
        // Phase-15 FRONT-5: img-src https: was wide-open. Now restricted
        // to explicit origins. Vue/Vite emit data: and blob: URIs for
        // small inline assets so those remain.
        //
        // BuildingMap (Leaflet) explicit origins. The PWA service worker
        // intercepts these requests, so the OpenStreetMap raster tiles +
        // cdnjs marker icons need BOTH img-src (the <img> render) AND
        // connect-src (the SW's fetch-to-cache — a fetch() is governed by
        // connect-src, not img-src). Nominatim geocoding + the Figtree
        // webfont CSS are fetches too (connect-src); the @font-face the
        // webfont returns is already covered by style-src/font-src.
        $mapOrigins = 'https://*.tile.openstreetmap.org https://cdnjs.cloudflare.com';

        // Slice-2 PR-2.4b-ii: the owner signs in an embedded Documenso iframe, so the
        // self-hosted Documenso origin must be an allowed frame source — default-src
        // 'self' (the frame-src fallback) would otherwise block it. Scoped to the
        // configured host; 'none' when Documenso is not configured.
        $documensoOrigin = rtrim((string) config('documenso.base_url', ''), '/');
        $frameSrc = $documensoOrigin !== '' ? "frame-src {$documensoOrigin}" : "frame-src 'none'";

        $directives = [
            "default-src 'self'",
            "script-src 'self' 'nonce-{$nonce}'",
            "style-src 'self' 'nonce-{$nonce}' https://fonts.bunny.net",
            "style-src-attr 'unsafe-inline'",
            "img-src 'self' data: blob: https://imgs.paystack.co {$mapOrigins}",
            "font-src 'self' data: https://fonts.bunny.net",
            "connect-src 'self' ws: wss: https://api.paystack.co https://nominatim.openstreetmap.org https://fonts.bunny.net {$mapOrigins}",
            $frameSrc,
            "frame-ancestors 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            // Phase-15 FRONT-6: report violations to the in-app
            // endpoint so ops can see what's being blocked. Defaults
            // to /api/v1/csp-reports; rate-limited there.
            'report-uri '.config('observability.csp.report_uri', '/api/v1/csp-reports'),
        ];

        // In development, allow Vite dev server origin + relax
        // restrictions that would block the dev server's hot module
        // reload.
        if (app()->environment('local', 'testing') && file_exists(public_path('hot'))) {
            $viteOrigin = $this->getViteOrigin();

            if ($viteOrigin) {
                $directives[1] = "script-src 'self' 'nonce-{$nonce}' {$viteOrigin}";
                $directives[6] = "connect-src 'self' ws: wss: {$viteOrigin} https://api.paystack.co https://nominatim.openstreetmap.org https://fonts.bunny.net {$mapOrigins}";
            }
        }

        return implode('; ', $directives);
    }

    /**
     * Extract Vite dev server origin from hot file.
     */
    private function getViteOrigin(): ?string
    {
        $hotFile = file_get_contents(public_path('hot'));
        $viteUrl = trim($hotFile);

        $parsed = parse_url($viteUrl);

        if (! $parsed) {
            return null;
        }

        $origin = ($parsed['scheme'] ?? 'http').'://'.($parsed['host'] ?? 'localhost');

        if (isset($parsed['port'])) {
            $origin .= ':'.$parsed['port'];
        }

        return $origin;
    }
}
