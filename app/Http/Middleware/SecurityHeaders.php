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

        // Base directives with nonce for scripts and styles
        $directives = [
            "default-src 'self'",
            "script-src 'self' 'nonce-{$nonce}' https://js.paystack.co",
            "style-src 'self' 'unsafe-inline' https://fonts.bunny.net",
            "img-src 'self' data: blob: https:",
            "font-src 'self' data: https://fonts.bunny.net",
            "connect-src 'self' ws: wss: https://api.paystack.co",
            "frame-ancestors 'none'",
            "base-uri 'self'",
            "form-action 'self'",
        ];

        // In development, allow Vite dev server origin
        if (app()->environment('local', 'testing') && file_exists(public_path('hot'))) {
            $viteOrigin = $this->getViteOrigin();

            if ($viteOrigin) {
                // Add Vite origin to script-src and connect-src
                $directives[1] = "script-src 'self' 'nonce-{$nonce}' {$viteOrigin} https://js.paystack.co";
                $directives[5] = "connect-src 'self' ws: wss: {$viteOrigin} https://api.paystack.co";
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
