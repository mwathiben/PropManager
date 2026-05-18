<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\MetricsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Phase-53 VUE-TELEMETRY-1/2: collect client-side telemetry signals
 * the frontend cannot hold across navigation.
 *
 * The /vue-preview-poll-pause endpoint accepts the Phase-51
 * pollPauseCount ref from Scheduled.vue at beforeunload via
 * navigator.sendBeacon. The counter increments by the reported value
 * on the server side so vue_preview_poll_pause_count exists in the
 * Prometheus exposition. sendBeacon does NOT send CSRF tokens, so
 * this route MUST live in api.php (which is CSRF-exempt by default)
 * rather than web.php.
 *
 * No auth — sendBeacon does not reliably pass session cookies on
 * page-unload across browsers, and the gauge is route-scoped not
 * user-scoped. Per-IP throttling at the route layer is the only
 * abuse defence.
 */
class TelemetryController extends Controller
{
    public function __construct(
        private readonly MetricsService $metrics,
    ) {}

    public function vuePreviewPollPause(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'count' => ['required', 'integer', 'min:0', 'max:1000'],
            'route' => ['required', 'string', 'max:120'],
        ]);

        try {
            $this->metrics->increment(
                'vue_preview_poll_pause_count',
                (int) $payload['count'],
                ['route' => $this->sanitiseRouteLabel($payload['route'])],
            );
        } catch (\Throwable) {
            // best-effort — telemetry must never block the client.
        }

        return response()->json(null, 204);
    }

    /**
     * Strip characters that would break Prometheus label parsing.
     * Whitelist [a-z0-9._-] (lowercased); anything else collapses to
     * underscore. Caps at 60 chars to bound cardinality.
     */
    private function sanitiseRouteLabel(string $raw): string
    {
        $lower = strtolower($raw);
        $sanitised = preg_replace('/[^a-z0-9._-]/', '_', $lower) ?? 'unknown';

        return substr($sanitised, 0, 60);
    }
}
