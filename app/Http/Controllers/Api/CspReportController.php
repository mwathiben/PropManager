<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SecurityLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Phase-15 FRONT-6: receive CSP violation reports. Browsers POST
 * here when the CSP header blocks a resource. Before this endpoint,
 * violations were invisible — the browser blocked the request but
 * ops never knew (CSP report-uri was not configured).
 *
 * The payload shape comes from the browser
 * (https://developer.mozilla.org/en-US/docs/Web/HTTP/CSP#violation_reporting).
 * We extract the interesting fields, write a SecurityLog row, and
 * 204 the response. The Phase-13 DPA-6 masking processor catches any
 * PII that landed in document-uri or original-policy.
 *
 * Rate-limited: one offender (compromised browser, extension, or
 * deliberate attacker) can spam this endpoint cheaply. The route
 * binds throttle:csp-report — 30 reports per IP per minute.
 */
class CspReportController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $payload = $request->json('csp-report') ?? $request->all();
        if (! is_array($payload)) {
            return response()->json(null, 204);
        }

        $blockedUri = (string) ($payload['blocked-uri'] ?? '');
        $documentUri = (string) ($payload['document-uri'] ?? '');
        $violatedDirective = (string) ($payload['violated-directive'] ?? '');

        SecurityLog::create([
            'user_id' => $request->user()?->id,
            'landlord_id' => null,
            'event_type' => 'csp_violation',
            'severity' => SecurityLog::SEVERITY_WARNING,
            'description' => sprintf(
                'CSP violation: %s blocked %s on %s',
                $violatedDirective ?: 'unknown-directive',
                $blockedUri ?: 'unknown-uri',
                $documentUri ?: 'unknown-page',
            ),
            'metadata' => [
                'blocked_uri' => $blockedUri,
                'document_uri' => $documentUri,
                'violated_directive' => $violatedDirective,
                'effective_directive' => (string) ($payload['effective-directive'] ?? ''),
                'source_file' => (string) ($payload['source-file'] ?? ''),
                'line_number' => (int) ($payload['line-number'] ?? 0),
                'status_code' => (int) ($payload['status-code'] ?? 0),
            ],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'is_suspicious' => false,
        ]);

        return response()->json(null, 204);
    }
}
