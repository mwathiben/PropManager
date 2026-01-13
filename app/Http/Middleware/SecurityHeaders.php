<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
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
        $response = $next($request);

        $config = config('security.headers');

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

        // Content Security Policy
        if ($config['csp_enabled'] && $config['csp']) {
            $response->headers->set('Content-Security-Policy', $config['csp']);
        }

        // Additional security headers
        $response->headers->set('X-Permitted-Cross-Domain-Policies', 'none');
        $response->headers->set('Cross-Origin-Embedder-Policy', 'require-corp');
        $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin');
        $response->headers->set('Cross-Origin-Resource-Policy', 'same-origin');

        return $response;
    }
}
